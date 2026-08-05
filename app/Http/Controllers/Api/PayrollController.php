<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\SocialSecurityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
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

    public function process(Request $request, SocialSecurityService $socialSecurity)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $tenant = app('tenant');
        $month = $data['month'];

        $employees = Employee::with(['contracts' => function ($query) {
            $query->where('status', 'active')->latest('start_date');
        }])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();

        $processed = 0;

        DB::transaction(function () use ($employees, $tenant, $month, $socialSecurity, &$processed) {
            foreach ($employees as $employee) {
            $contract = $employee->contracts->first();

            if (! $contract) {
                continue;
            }

            $grossSalary = (float) $contract->base_salary;
            $contributions = $socialSecurity->calculate($grossSalary);
            $employeeSocialSecurity = $contributions['total_employee'];
            $netSalary = $grossSalary - $employeeSocialSecurity;

            Payroll::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'month' => $month,
                ],
                [
                    'base_salary' => $grossSalary,
                    'overtime_hours' => 0,
                    'overtime_amount' => 0,
                    'bonuses' => 0,
                    'deductions' => 0,
                    'taxes' => 0,
                    'social_security' => $employeeSocialSecurity,
                    'net_salary' => $netSalary,
                    'status' => 'processed',
                    'breakdown' => [
                        'gross_salary' => $grossSalary,
                        'employee_social_security' => $contributions['employee_contributions'],
                        'employer_social_security' => $contributions['employer_contributions'],
                        'income_tax' => 0,
                        'note' => 'L\'impôt sur les traitements et salaires doit être configuré selon le régime fiscal applicable.',
                    ],
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
            'payroll' => $payroll->load(['employee.user', 'employee.department']),
        ]);
    }

    public function download(Payroll $payroll)
    {
        // PDF generation is intentionally not faked: a PDF engine (for example
        // Dompdf) should be installed before exposing a real PDF download.
        return response()->json([
            'message' => 'Le bulletin est prêt à être imprimé.',
            'payroll' => $payroll->load(['employee.user', 'employee.department']),
            'format' => 'print',
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
