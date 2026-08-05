<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::with(['employee.user', 'employee.department']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->search) {
            $query->whereHas('employee.user', function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%");
            });
        }

        $contracts = $query->paginate($request->per_page ?? 15);

        return response()->json($contracts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'type' => 'required|in:cdi,cdd,stage,consultant,freelance',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'probation_end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'benefits' => 'nullable|array',
            'terms' => 'nullable|string',
        ]);

        $contract = Contract::create($request->all());

        return response()->json([
            'message' => 'Contrat créé avec succès',
            'contract' => $contract->load(['employee.user'])
        ], 201);
    }

    public function show(Contract $contract)
    {
        return response()->json([
            'contract' => $contract->load(['employee.user', 'employee.department'])
        ]);
    }

    public function update(Request $request, Contract $contract)
    {
        $request->validate([
            'type' => 'sometimes|in:cdi,cdd,stage,consultant,freelance',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'probation_end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'benefits' => 'nullable|array',
            'terms' => 'nullable|string',
            'status' => 'sometimes|in:active,expired,terminated,pending',
        ]);

        $contract->update($request->all());

        return response()->json([
            'message' => 'Contrat mis à jour avec succès',
            'contract' => $contract->fresh()->load(['employee.user'])
        ]);
    }

    public function destroy(Contract $contract)
    {
        $contract->delete();

        return response()->json([
            'message' => 'Contrat supprimé avec succès'
        ]);
    }


    public function stats()
    {
        $tenant = app('tenant');

        $stats = [
            'total' => Contract::where('tenant_id', $tenant->id)->count(),
            'active' => Contract::where('tenant_id', $tenant->id)->where('status', 'active')->count(),
            'expired' => Contract::where('tenant_id', $tenant->id)->where('status', 'expired')->count(),
            'pending' => Contract::where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
            'expiring_soon' => Contract::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('end_date', '<=', Carbon::now()->addDays(30))
                ->count(),
            'by_type' => Contract::where('tenant_id', $tenant->id)
                ->select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->get(),
        ];

        return response()->json($stats);
    }
}