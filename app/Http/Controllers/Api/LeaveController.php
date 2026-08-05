<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\Employee;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = Leave::with(['employee.user', 'approver']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->date_from) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('end_date', '<=', $request->date_to);
        }

        $leaves = $query->paginate($request->per_page ?? 15);

        return response()->json($leaves);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'type' => 'required|in:annual,sick,maternity,paternity,exceptional,unpaid,training',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
            'attachment' => 'nullable|file|max:5120', // 5MB
        ]);

        if ($request->user()->hasRole('employee')) {
            abort_unless($request->user()->employee, 403, 'Aucun dossier employé associé à ce compte.');
            $request->merge(['employee_id' => $request->user()->employee->id]);
        }

        $days = Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date)) + 1;

        // Vérifier le solde pour les congés annuels
        if ($request->type === 'annual') {
            $balance = LeaveBalance::firstOrCreate(
                [
                    'tenant_id' => app('tenant')->id,
                    'employee_id' => $request->employee_id,
                    'year' => Carbon::now()->year,
                ],
                [
                    'annual_entitled' => 24,
                    'annual_taken' => 0,
                    'annual_remaining' => 24,
                    'sick_entitled' => 10,
                    'sick_taken' => 0,
                    'sick_remaining' => 10,
                ]
            );

            if ($balance->annual_remaining < $days) {
                return response()->json([
                    'message' => 'Solde de congé insuffisant. Solde restant: ' . $balance->annual_remaining . ' jours'
                ], 422);
            }
        }

        $leave = Leave::create([
            'tenant_id' => app('tenant')->id,
            'employee_id' => $request->employee_id,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days' => $days,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Demande de congé créée avec succès',
            'leave' => $leave->load(['employee.user'])
        ], 201);
    }

    public function show(Leave $leave)
    {
        $this->assertEmployeeAccess($leave);

        return response()->json([
            'leave' => $leave->load(['employee.user', 'approver'])
        ]);
    }

    public function update(Request $request, Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Cette demande ne peut plus être modifiée'
            ], 422);
        }

        $request->validate([
            'type' => 'sometimes|in:annual,sick,maternity,paternity,exceptional,unpaid,training',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        if ($request->has('start_date') || $request->has('end_date')) {
            $start = $request->start_date ?? $leave->start_date;
            $end = $request->end_date ?? $leave->end_date;
            $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
            $request->merge(['days' => $days]);
        }

        $leave->update($request->all());

        return response()->json([
            'message' => 'Demande de congé mise à jour avec succès',
            'leave' => $leave->fresh()->load(['employee.user'])
        ]);
    }

    public function destroy(Leave $leave)
    {
        if ($leave->status === 'approved') {
            return response()->json([
                'message' => 'Impossible de supprimer un congé déjà approuvé'
            ], 422);
        }

        $leave->delete();

        return response()->json([
            'message' => 'Demande de congé supprimée avec succès'
        ]);
    }

    public function approve(Request $request, Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Cette demande a déjà été traitée',
            ], 422);
        }

        try {
            DB::transaction(function () use ($leave, $request) {
                if ($leave->type === 'annual') {
                    $balance = LeaveBalance::where('employee_id', $leave->employee_id)
                        ->where('year', Carbon::now()->year)
                        ->lockForUpdate()
                        ->first();

                    if (! $balance || $balance->annual_remaining < $leave->days) {
                        abort(422, 'Solde de congé annuel insuffisant.');
                    }

                    $balance->update([
                        'annual_taken' => $balance->annual_taken + $leave->days,
                        'annual_remaining' => $balance->annual_remaining - $leave->days,
                    ]);
                }

                $leave->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approval_date' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Demande de congé approuvée avec succès',
                'leave' => $leave->fresh()->load(['employee.user', 'approver']),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                    ? $e->getMessage()
                    : 'Une erreur interne est survenue lors de l\'approbation.',
            ], $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException ? $e->getStatusCode() : 500);
        }
    }


    public function reject(Request $request, Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Cette demande a déjà été traitée'
            ], 422);
        }

        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $leave->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => $request->user()->id,
            'approval_date' => now(),
        ]);

        return response()->json([
            'message' => 'Demande de congé rejetée',
            'leave' => $leave->fresh()->load(['employee.user', 'approver'])
        ]);
    }

    public function pending()
    {
        $leaves = Leave::with(['employee.user'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($leaves);
    }

    public function balance(Employee $employee)
    {
        $currentYear = Carbon::now()->year;

        $balance = LeaveBalance::firstOrCreate(
            [
                'tenant_id' => app('tenant')->id,
                'employee_id' => $employee->id,
                'year' => $currentYear,
            ],
            [
                'annual_entitled' => 24,
                'annual_taken' => 0,
                'annual_remaining' => 24,
                'sick_entitled' => 10,
                'sick_taken' => 0,
                'sick_remaining' => 10,
            ]
        );

        return response()->json([
            'balance' => $balance,
            'employee' => $employee->load('user')
        ]);
    }

    public function stats()
    {
        $tenant = app('tenant');

        $stats = [
            'pending' => Leave::where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
            'approved' => Leave::where('tenant_id', $tenant->id)->where('status', 'approved')->count(),
            'rejected' => Leave::where('tenant_id', $tenant->id)->where('status', 'rejected')->count(),
            'by_type' => Leave::where('tenant_id', $tenant->id)
                ->select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->get(),
            'by_month' => Leave::where('tenant_id', $tenant->id)
                ->whereYear('start_date', Carbon::now()->year)
                ->select(DB::raw('MONTH(start_date) as month'), DB::raw('COUNT(*) as count'))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ];

        return response()->json($stats);
    }
    private function assertEmployeeAccess(Leave $leave): void
    {
        $user = request()->user();

        if ($user?->hasRole('employee') && (int) $user->employee?->id !== (int) $leave->employee_id) {
            abort(403, 'Accès à cette demande de congé interdit.');
        }
    }

}