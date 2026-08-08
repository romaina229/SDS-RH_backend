<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function index(Request $request)
    {
        $query = Position::with(['department']);

        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->is_active !== null) {
            $query->where('is_active', $request->is_active);
        }

        $positions = $query->paginate($request->per_page ?? 15);
        return response()->json($positions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'corps' => 'nullable|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('positions', 'code')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'grade' => 'nullable|string|max:50',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'description' => 'nullable|string',
            'requirements' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $position = Position::create($request->all());

        return response()->json([
            'message' => 'Poste créé avec succès',
            'position' => $position->load('department')
        ], 201);
    }

    public function show(Position $position)
    {
        return response()->json([
            'position' => $position->load(['department', 'employees.user'])
        ]);
    }

    public function update(Request $request, Position $position)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'corps' => 'nullable|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('positions', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', app('tenant')->id))
                    ->ignore($position->id),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'grade' => 'nullable|string|max:50',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'description' => 'nullable|string',
            'requirements' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $position->update($request->all());

        return response()->json([
            'message' => 'Poste mis à jour avec succès',
            'position' => $position->fresh()->load('department')
        ]);
    }

    public function destroy(Position $position)
    {
        if ($position->employees()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce poste car il a des employés'
            ], 422);
        }

        $position->delete();
        return response()->json(['message' => 'Poste supprimé avec succès']);
    }
}