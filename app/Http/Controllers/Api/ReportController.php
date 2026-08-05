<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function employees(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'in:pdf,excel',
        ]);

        $tenant = app('tenant');

        $employees = Employee::with(['user', 'department', 'position'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('hire_date', [$request->start_date, $request->end_date])
            ->get();

        return response()->json([
            'employees' => $employees,
            'count' => $employees->count(),
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ]
        ]);
    }

    public function attendance(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'in:pdf,excel',
        ]);

        $tenant = app('tenant');

        $attendances = Attendance::with(['employee.user', 'employee.department'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        $summary = [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'late' => $attendances->where('status', 'late')->count(),
            'half_day' => $attendances->where('status', 'half_day')->count(),
            'holiday' => $attendances->where('status', 'holiday')->count(),
            'leave' => $attendances->where('status', 'leave')->count(),
            'total_hours' => $attendances->sum('total_hours'),
            'overtime_hours' => $attendances->sum('overtime_hours'),
        ];

        return response()->json([
            'attendances' => $attendances,
            'summary' => $summary,
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ]
        ]);
    }

    public function payroll(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'format' => 'in:pdf,excel',
        ]);

        $tenant = app('tenant');

        $payrolls = Payroll::with(['employee.user', 'employee.department'])
            ->where('tenant_id', $tenant->id)
            ->where('month', $request->month)
            ->get();

        $summary = [
            'total_employees' => $payrolls->count(),
            'total_gross' => $payrolls->sum('base_salary'),
            'total_bonuses' => $payrolls->sum('bonuses'),
            'total_deductions' => $payrolls->sum('deductions'),
            'total_taxes' => $payrolls->sum('taxes'),
            'total_social_security' => $payrolls->sum('social_security'),
            'total_net' => $payrolls->sum('net_salary'),
            'paid' => $payrolls->where('status', 'paid')->count(),
            'processed' => $payrolls->where('status', 'processed')->count(),
        ];

        return response()->json([
            'payrolls' => $payrolls,
            'summary' => $summary,
            'month' => $request->month,
        ]);
    }

    public function leaves(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'in:pdf,excel',
        ]);

        $tenant = app('tenant');

        $leaves = Leave::with(['employee.user', 'employee.department', 'approver'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('start_date', [$request->start_date, $request->end_date])
            ->get();

        $summary = [
            'total' => $leaves->count(),
            'pending' => $leaves->where('status', 'pending')->count(),
            'approved' => $leaves->where('status', 'approved')->count(),
            'rejected' => $leaves->where('status', 'rejected')->count(),
            'cancelled' => $leaves->where('status', 'cancelled')->count(),
            'total_days' => $leaves->sum('days'),
            'by_type' => $leaves->groupBy('type')->map(function($item) {
                return [
                    'count' => $item->count(),
                    'days' => $item->sum('days'),
                ];
            }),
        ];

        return response()->json([
            'leaves' => $leaves,
            'summary' => $summary,
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ]
        ]);
    }

}