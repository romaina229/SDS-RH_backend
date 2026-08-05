<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Performance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PerformanceController extends Controller
{
    /**
     * Objectifs (goals) d'un ou plusieurs employés.
     */
    public function goalsIndex(Request $request)
    {
        $query = Goal::with(['employee.user']);

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $goals = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json($goals);
    }

    public function storeGoal(Request $request)
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'target' => 'nullable|numeric|min:0',
            'progress' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:not_started,in_progress,completed,cancelled',
            'key_results' => 'nullable|array',
        ]);

        $goal = Goal::create($data);

        return response()->json([
            'message' => 'Objectif créé avec succès',
            'goal' => $goal->load('employee.user'),
        ], 201);
    }

    public function updateGoal(Request $request, Goal $goal)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'target' => 'nullable|numeric|min:0',
            'progress' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:not_started,in_progress,completed,cancelled',
            'key_results' => 'nullable|array',
        ]);

        $goal->update($data);

        return response()->json([
            'message' => 'Objectif mis à jour avec succès',
            'goal' => $goal->fresh()->load('employee.user'),
        ]);
    }

    public function destroyGoal(Goal $goal)
    {
        $goal->delete();

        return response()->json(['message' => 'Objectif supprimé avec succès']);
    }

    /**
     * Évaluations de performance.
     */
    public function reviewsIndex(Request $request)
    {
        $query = Performance::with(['employee.user', 'reviewer.user']);

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $reviews = $query->latest('review_date')->paginate($request->per_page ?? 15);

        return response()->json($reviews);
    }

    public function storeReview(Request $request)
    {
        $tenant = app('tenant');

        // L'évaluateur est l'employé lié à l'utilisateur actuellement connecté
        // (un manager ou un admin doit avoir sa propre fiche employé).
        $reviewer = $request->user()->employee;

        if (! $reviewer) {
            return response()->json([
                'message' => "Aucune fiche employé n'est associée à votre compte, impossible de créer une évaluation.",
            ], 422);
        }

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
            ],
            'period' => 'required|string|max:50',
            'ratings' => 'nullable|array',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'achievements' => 'nullable|string',
            'goals_achieved' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'overall_score' => 'nullable|numeric|min:0|max:20',
            'status' => 'nullable|in:draft,submitted,reviewed,approved',
        ]);

        $data['reviewer_id'] = $reviewer->id;
        $data['review_date'] = $data['review_date'] ?? now();

        $review = Performance::create($data);

        return response()->json([
            'message' => 'Évaluation créée avec succès',
            'review' => $review->load(['employee.user', 'reviewer.user']),
        ], 201);
    }

    public function updateReview(Request $request, Performance $performance)
    {
        $data = $request->validate([
            'period' => 'sometimes|string|max:50',
            'ratings' => 'nullable|array',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'achievements' => 'nullable|string',
            'goals_achieved' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'overall_score' => 'nullable|numeric|min:0|max:20',
            'status' => 'sometimes|in:draft,submitted,reviewed,approved',
        ]);

        $performance->update($data);

        return response()->json([
            'message' => 'Évaluation mise à jour avec succès',
            'review' => $performance->fresh()->load(['employee.user', 'reviewer.user']),
        ]);
    }

    public function destroyReview(Performance $performance)
    {
        $performance->delete();

        return response()->json(['message' => 'Évaluation supprimée avec succès']);
    }

    /**
     * Statistiques du module Performance pour le tableau de bord.
     */
    public function stats()
    {
        $tenant = app('tenant');

        return response()->json([
            'goals' => [
                'total' => Goal::where('tenant_id', $tenant->id)->count(),
                'completed' => Goal::where('tenant_id', $tenant->id)->where('status', 'completed')->count(),
                'in_progress' => Goal::where('tenant_id', $tenant->id)->where('status', 'in_progress')->count(),
                'not_started' => Goal::where('tenant_id', $tenant->id)->where('status', 'not_started')->count(),
            ],
            'reviews' => [
                'total' => Performance::where('tenant_id', $tenant->id)->count(),
                'by_status' => Performance::where('tenant_id', $tenant->id)
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->groupBy('status')
                    ->get(),
                'average_score' => round((float) Performance::where('tenant_id', $tenant->id)->avg('overall_score'), 2),
            ],
        ]);
    }
}