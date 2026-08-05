<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['employee.user']);

        $this->restrictToCurrentEmployee($query);

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest('date')->paginate(min((int) $request->input('per_page', 50), 100))
        );
    }

    public function clockIn(Request $request)
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'method' => 'nullable|in:qr_code,face_recognition,manual,badge,mobile',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->assertEmployeeAccess((int) $data['employee_id']);

        return response()->json($this->recordClockIn(
            (int) $data['employee_id'],
            $data['method'] ?? 'manual',
            $data['notes'] ?? null,
            array_filter([
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ], static fn ($value) => $value !== null)
        ));
    }

    private function recordClockIn(int $employeeId, string $method, ?string $notes, array $locationData): array
    {
        $today = Carbon::today();

        $existing = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();

        if ($existing?->clock_in) {
            return [
                'message' => 'Vous avez déjà pointé aujourd\'hui',
                'attendance' => $existing,
            ];
        }

        $attendance = $existing ?? new Attendance([
            'employee_id' => $employeeId,
            'date' => $today,
        ]);

        $attendance->fill([
            'clock_in' => Carbon::now()->format('H:i:s'),
            'method' => $method,
            'status' => 'present',
            'notes' => $notes,
            'location_data' => $locationData,
        ]);
        $attendance->save();

        return [
            'message' => 'Pointage entrée enregistré avec succès',
            'attendance' => $attendance->load('employee.user'),
        ];
    }

    public function clockOut(Request $request)
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->assertEmployeeAccess((int) $data['employee_id']);

        $attendance = Attendance::where('employee_id', $data['employee_id'])
            ->whereDate('date', Carbon::today())
            ->first();

        if (! $attendance) {
            return response()->json([
                'message' => 'Aucun pointage entrée trouvé pour aujourd\'hui',
            ], 404);
        }

        if ($attendance->clock_out) {
            return response()->json([
                'message' => 'Vous avez déjà pointé sortie aujourd\'hui',
            ], 422);
        }

        $clockOut = Carbon::now();
        $clockIn = Carbon::createFromFormat('H:i:s', $attendance->clock_in);
        $totalHours = round($clockOut->diffInMinutes($clockIn) / 60, 2);

        $attendance->update([
            'clock_out' => $clockOut->format('H:i:s'),
            'total_hours' => $totalHours,
            'notes' => $data['notes'] ?? $attendance->notes,
        ]);

        return response()->json([
            'message' => 'Pointage sortie enregistré avec succès',
            'attendance' => $attendance->load('employee.user'),
        ]);
    }

    public function today(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::today();

        $query = Attendance::with(['employee.user'])
            ->whereDate('date', $date);

        $this->restrictToCurrentEmployee($query);

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $attendances = $query->orderBy('clock_in')->get();

        $stats = [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'late' => $attendances->where('status', 'late')->count(),
            'half_day' => $attendances->where('status', 'half_day')->count(),
            'holiday' => $attendances->where('status', 'holiday')->count(),
            'leave' => $attendances->where('status', 'leave')->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'attendances' => $attendances,
        ]);
    }

    public function history(Request $request, Employee $employee)
    {
        $this->assertEmployeeAccess($employee->id);

        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'] ?? Carbon::now()->format('Y-m'));

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'employee' => $employee->load('user'),
            'month' => $month->format('Y-m'),
            'stats' => [
                'present' => $attendances->where('status', 'present')->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'half_day' => $attendances->where('status', 'half_day')->count(),
                'total_hours' => $attendances->sum('total_hours'),
                'overtime_hours' => $attendances->sum('overtime_hours'),
            ],
            'attendances' => $attendances,
        ]);
    }

    public function generateQR(Request $request)
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
        ]);

        $this->assertEmployeeAccess((int) $data['employee_id']);

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(5);

        Cache::put(
            'attendance:qr:' . $token,
            (int) $data['employee_id'],
            $expiresAt
        );

        return response()->json([
            'qr_code' => $token,
            'scan_url' => url('/api/attendances/scan/' . $token),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function scanQR(string $qrCode)
    {
        $employeeId = Cache::pull('attendance:qr:' . $qrCode);

        if (! $employeeId) {
            return response()->json([
                'message' => 'QR code invalide ou expiré',
            ], 422);
        }

        $this->assertEmployeeAccess((int) $employeeId);

        $result = $this->recordClockIn((int) $employeeId, 'qr_code', null, []);

        if (isset($result['attendance'])) {
            return response()->json($result);
        }

        return response()->json($result, 422);
    }

    public function stats(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'] ?? Carbon::now()->format('Y-m'));
        $year = (int) ($data['year'] ?? $month->year);

        $base = Attendance::query()->where('tenant_id', app('tenant')->id);

        $user = request()->user();
        if ($user->hasRole('employee') && $user->employee) {
            $base->where('employee_id', $user->employee->id);
        }

        return response()->json([
            'daily' => (clone $base)
                ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->select('date')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
                ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
                ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late")
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'monthly' => (clone $base)
                ->whereYear('date', $year)
                ->selectRaw('MONTH(date) as month')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
                ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
                ->groupByRaw('MONTH(date)')
                ->orderBy('month')
                ->get(),
            'overall' => [
                'total' => (clone $base)->count(),
                'present' => (clone $base)->where('status', 'present')->count(),
                'absent' => (clone $base)->where('status', 'absent')->count(),
                'late' => (clone $base)->where('status', 'late')->count(),
                'avg_hours' => (clone $base)->avg('total_hours'),
            ],
        ]);
    }
    private function restrictToCurrentEmployee($query): void
    {
        $user = request()->user();

        if ($user?->hasRole('employee') && $user->employee) {
            $query->where('employee_id', $user->employee->id);
        }
    }

    private function assertEmployeeAccess(int $employeeId): void
    {
        $user = request()->user();

        if ($user?->hasRole('employee') && (int) $user->employee?->id !== $employeeId) {
            abort(403, 'Vous ne pouvez gérer que votre propre pointage.');
        }
    }

}
