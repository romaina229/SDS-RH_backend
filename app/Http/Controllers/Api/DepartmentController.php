<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::with(['manager.user', 'parent', 'children']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->is_active !== null) {
            $query->where('is_active', $request->is_active);
        }

        $departments = $query->get();

        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'hierarchy_path' => 'nullable|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'description' => 'nullable|string',
            'manager_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'parent_department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'is_active' => 'boolean',
        ]);

        $department = Department::create($request->all());

        return response()->json([
            'message' => 'Département créé avec succès',
            'department' => $department->load(['manager.user', 'parent', 'children'])
        ], 201);
    }

    public function show(Department $department)
    {
        return response()->json([
            'department' => $department->load(['manager.user', 'parent', 'children', 'employees.user', 'positions'])
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'hierarchy_path' => 'nullable|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', app('tenant')->id))
                    ->ignore($department->id),
            ],
            'description' => 'nullable|string',
            'manager_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'parent_department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'is_active' => 'boolean',
        ]);

        $department->update($request->all());

        return response()->json([
            'message' => 'Département mis à jour avec succès',
            'department' => $department->fresh()->load(['manager.user', 'parent', 'children'])
        ]);
    }

    public function destroy(Department $department)
    {
        if ($department->children()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce département car il a des sous-départements'
            ], 422);
        }

        if ($department->employees()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce département car il a des employés'
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Département supprimé avec succès'
        ]);
    }

    public function tree()
    {
        $departments = Department::with(['children' => function($query) {
            $query->where('is_active', true);
        }])->whereNull('parent_department_id')->get();

        return response()->json($departments);
    }
}