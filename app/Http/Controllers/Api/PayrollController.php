<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayslipBuilderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayrollController extends Controller
{
    /**
     * Nombre légal d'heures mensuelles utilisé pour un taux horaire
     * indicatif (173.33h/mois, base 40h/semaine — Bénin). À rendre
     * configurable par tenant/pays dans une itération future.
     */
    private const LEGAL_MONTHLY_HOURS = 173.33;

    public function index(Request $request)
    {
        $query = Payroll::with(['employee.user']);

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate(min((int) $request->input('per_page', 15), 100))
        );
    }

    public function process(Request $request, PayslipBuilderService $payslipBuilder)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $tenant = app('tenant');
        $month = $data['month'];
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $workedDays = $monthStart->daysInMonth;

        $employees = Employee::with(['contracts' => function ($query) {
            $query->where('status', 'active')->latest('start_date');
        }])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();

        $processed = 0;

        DB::transaction(function () use ($employees, $tenant, $month, $workedDays, $payslipBuilder, &$processed) {
            foreach ($employees as $employee) {
                $contract = $employee->contracts->first();

                if (! $contract) {
                    continue;
                }

                $grossSalary = (float) $contract->base_salary;
                $hourlyRate = round($grossSalary / self::LEGAL_MONTHLY_HOURS, 0);

                $result = $payslipBuilder->build($employee, $tenant, $grossSalary);

                // Conserve le qr_token existant si le bulletin du mois existe
                // déjà (relance de traitement), sinon en génère un nouveau.
                $existingToken = Payroll::where('tenant_id', $tenant->id)
                    ->where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->value('qr_token');

                Payroll::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'employee_id' => $employee->id,
                        'month' => $month,
                    ],
                    [
                        'qr_token' => $existingToken ?: Str::random(40),
                        'worked_days' => $workedDays,
                        'hourly_rate' => $hourlyRate,
                        'base_salary' => $grossSalary,
                        'overtime_hours' => 0,
                        'overtime_amount' => 0,
                        'bonuses' => 0,
                        'deductions' => 0,
                        'taxes' => collect($result['items'])->firstWhere('code', '855C')['retenue'] ?? 0,
                        'social_security' => $result['total_retenue'],
                        'net_salary' => $result['net'],
                        'payment_method' => $employee->bank_details['bank_name'] ?? null
                            ? 'Virement bancaire'
                            : 'Espèces',
                        'status' => 'processed',
                        'breakdown' => $result['items'],
                    ]
                );

                $processed++;
            }
        });

        return response()->json([
            'message' => 'Paie traitée avec succès',
            'processed' => $processed,
            'month' => $month,
        ]);
    }

    public function show(Payroll $payroll)
    {
        return response()->json([
            'payroll' => $payroll->load(['employee.user', 'employee.department', 'employee.position']),
        ]);
    }

    public function download(Payroll $payroll)
    {
        // La génération PDF côté serveur (Dompdf/Browsershot) est
        // recommandée pour un fichier archivable, mais le frontend peut déjà
        // imprimer un bulletin conforme à partir de ces données structurées.
        return response()->json([
            'message' => 'Le bulletin est prêt à être imprimé.',
            'payroll' => $payroll->load([
                'employee.user',
                'employee.department',
                'employee.position',
                'tenant',
            ]),
            'format' => 'print',
        ]);
    }

    /**
     * Vérification publique d'un bulletin via son QR code, sans
     * authentification. N'expose que des informations minimales.
     */
    public function verify(string $qrToken)
    {
        $payroll = Payroll::withoutTenantScope()
            ->with('employee.user')
            ->where('qr_token', $qrToken)
            ->first();

        if (! $payroll) {
            return response()->json(['message' => 'Bulletin introuvable ou lien invalide'], 404);
        }

        return response()->json([
            'employee' => $payroll->employee?->full_name,
            'month' => $payroll->month,
            'net_salary' => $payroll->net_salary,
            'status' => $payroll->status,
            'verified' => true,
        ]);
    }

    public function pay(Payroll $payroll)
    {
        if ($payroll->status !== 'processed') {
            return response()->json([
                'message' => 'Seule une paie traitée peut être marquée comme payée.',
            ], 422);
        }

        $payroll->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Paie marquée comme payée avec succès',
            'payroll' => $payroll->fresh(),
        ]);
    }

    public function stats(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $month = $data['month'] ?? Carbon::now()->format('Y-m');

        $query = Payroll::where('tenant_id', app('tenant')->id)->where('month', $month);

        return response()->json([
            'total' => (clone $query)->count(),
            'processed' => (clone $query)->where('status', 'processed')->count(),
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'total_net' => (clone $query)->sum('net_salary'),
            'total_gross' => (clone $query)->sum('base_salary'),
            'total_bonuses' => (clone $query)->sum('bonuses'),
            'total_deductions' => (clone $query)->sum('deductions'),
        ]);
    }
}