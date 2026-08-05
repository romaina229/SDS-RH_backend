<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $user = request()->user();

        // Employees only see their own HR data. Managers/admins see
        // organization-level indicators.
        if ($user->hasRole('employee') && $user->employee) {
            $employee = $user->employee->load('department');

            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            $pendingLeaves = Leave::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->count();

            $activeContract = Contract::where('employee_id', $employee->id)
                ->where('status', 'active')
                ->first();

            return response()->json([
                'stats' => [
                    'total_employees' => 1,
                    'total_departments' => $employee->department ? 1 : 0,
                    'active_contracts' => $activeContract ? 1 : 0,
                    'present_today' => $attendance?->status === 'present' ? 1 : 0,
                    'absent_today' => $attendance?->status === 'absent' ? 1 : 0,
                    'pending_leaves' => $pendingLeaves,
                    'new_hires' => $employee->hire_date && $employee->hire_date->gte(now()->subDays(30)) ? 1 : 0,
                    'contracts_expiring' => $activeContract?->end_date?->lte(now()->addDays(30)) ? 1 : 0,
                ],
                'department_distribution' => $employee->department
                    ? [['name' => $employee->department->name, 'count' => 1]]
                    : [],
                'hiring_trend' => $employee->hire_date
                    ? [[
                        'month' => $employee->hire_date->translatedFormat('M Y'),
                        'count' => 1,
                    ]]
                    : [],
                'attendance_today' => $attendance ? [$attendance->status => 1] : [],
                'recent_activities' => $this->getRecentActivities($employee->id),
            ]);
        }

        $stats = [
            'total_employees' => Employee::where('status', 'active')->count(),
            'total_departments' => Department::count(),
            'active_contracts' => Contract::where('status', 'active')->count(),
            'present_today' => Attendance::whereDate('date', $today)->where('status', 'present')->count(),
            'absent_today' => Attendance::whereDate('date', $today)->where('status', 'absent')->count(),
            'pending_leaves' => Leave::where('status', 'pending')->count(),
            'new_hires' => Employee::where('hire_date', '>=', now()->subDays(30))->count(),
            'contracts_expiring' => Contract::where('status', 'active')
                ->whereNotNull('end_date')
                ->where('end_date', '<=', now()->addDays(30))
                ->count(),
        ];

        $departmentStats = Department::withCount(['employees' => function ($query) {
            $query->where('status', 'active');
        }])->get()->map(function ($dept) {
            return [
                'name' => $dept->name,
                'count' => (int) $dept->employees_count,
            ];
        });

        $monthExpression = DB::connection()->getDriverName() === 'pgsql'
            ? "TO_CHAR(hire_date, 'YYYY-MM-01')"
            : "DATE_FORMAT(hire_date, '%Y-%m-01')";

        $hiringTrend = Employee::selectRaw("{$monthExpression} as month, COUNT(*) as count")
            ->where('hire_date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupByRaw($monthExpression)
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::parse($item->month)->translatedFormat('M Y'),
                    'count' => (int) $item->count,
                ];
            });

        $attendanceToday = Attendance::whereDate('date', $today)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'stats' => $stats,
            'department_distribution' => $departmentStats,
            'hiring_trend' => $hiringTrend,
            'attendance_today' => $attendanceToday,
            'recent_activities' => $this->getRecentActivities(),
        ]);
    }

    private function getRecentActivities(?int $employeeId = null): array
    {
        $activities = [];

        $recentLeaves = Leave::with('employee.user')
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($leave) {
                return [
                    'type' => 'leave',
                    'message' => "{$leave->employee->full_name} a demandé un congé {$leave->type}",
                    'status' => $leave->status,
                    'date' => $leave->created_at->diffForHumans(),
                    '_timestamp' => $leave->created_at->timestamp,
                ];
            });

        $recentHires = Employee::with('user', 'department')
            ->when($employeeId, fn ($query) => $query->whereKey($employeeId))
            ->where('status', 'active')
            ->latest('hire_date')
            ->limit(5)
            ->get()
            ->map(function ($employee) {
                return [
                    'type' => 'hire',
                    'message' => "{$employee->full_name} a rejoint le département {$employee->department?->name}",
                    'status' => 'completed',
                    'date' => $employee->hire_date->diffForHumans(),
                    '_timestamp' => $employee->hire_date->timestamp,
                ];
            });

        $expiringContracts = Contract::with('employee.user')
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays(30))
            ->latest('end_date')
            ->limit(5)
            ->get()
            ->map(function ($contract) {
                return [
                    'type' => 'contract',
                    'message' => "Le contrat de {$contract->employee->full_name} expire dans {$contract->days_remaining} jours",
                    'status' => 'warning',
                    'date' => $contract->end_date->diffForHumans(),
                    '_timestamp' => $contract->end_date->timestamp,
                ];
            });

        $activities = array_merge(
            $recentLeaves->toArray(),
            $recentHires->toArray(),
            $expiringContracts->toArray()
        );

        usort($activities, fn ($a, $b) => $b['_timestamp'] <=> $a['_timestamp']);

        return array_map(function ($activity) {
            unset($activity['_timestamp']);
            return $activity;
        }, array_slice($activities, 0, 10));
    }
}
