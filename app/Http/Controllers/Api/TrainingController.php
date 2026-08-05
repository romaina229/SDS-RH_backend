<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $query = Training::query();

        if ($request->user()->hasRole('employee')) {
            $query->withCount('participants');
        } else {
            $query->with(['participants.employee.user']);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $trainings = $query->paginate($request->per_page ?? 15);
        return response()->json($trainings);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:internal,external,online,workshop',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'trainer' => 'nullable|string|max:255',
            'cost' => 'required|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'status' => 'in:planned,ongoing,completed,cancelled',
            'objectives' => 'nullable|array',
        ]);

        $training = Training::create([
            'tenant_id' => app('tenant')->id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'location' => $request->location,
            'trainer' => $request->trainer,
            'cost' => $request->cost,
            'max_participants' => $request->max_participants,
            'status' => $request->status ?? 'planned',
            'objectives' => $request->objectives,
        ]);

        return response()->json([
            'message' => 'Formation créée avec succès',
            'training' => $training->load('participants')
        ], 201);
    }

    public function show(Training $training)
    {
        if (request()->user()->hasRole('employee')) {
            return response()->json([
                'training' => $training->loadCount('participants'),
            ]);
        }

        return response()->json([
            'training' => $training->load(['participants.employee.user'])
        ]);
    }

    public function update(Request $request, Training $training)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:internal,external,online,workshop',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'trainer' => 'nullable|string|max:255',
            'cost' => 'sometimes|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:planned,ongoing,completed,cancelled',
            'objectives' => 'nullable|array',
        ]);

        $training->update($request->all());

        return response()->json([
            'message' => 'Formation mise à jour avec succès',
            'training' => $training->fresh()->load('participants')
        ]);
    }

    public function destroy(Training $training)
    {
        $training->delete();
        return response()->json(['message' => 'Formation supprimée avec succès']);
    }

    public function enroll(Request $request, Training $training)
    {
        $request->validate([
            'employee_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
        ]);

        if ($request->user()->hasRole('employee')) {
            abort_unless($request->user()->employee, 403, 'Aucun dossier employé associé à ce compte.');
            $request->merge(['employee_id' => $request->user()->employee->id]);
        }

        $existing = TrainingParticipant::where('training_id', $training->id)
            ->where('employee_id', $request->employee_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'L\'employé est déjà inscrit à cette formation'
            ], 422);
        }

        if ($training->max_participants && 
            $training->participants()->where('status', 'enrolled')->count() >= $training->max_participants) {
            return response()->json([
                'message' => 'Nombre maximum de participants atteint'
            ], 422);
        }

        $participant = TrainingParticipant::create([
            'tenant_id' => app('tenant')->id,
            'training_id' => $training->id,
            'employee_id' => $request->employee_id,
            'status' => 'enrolled',
        ]);

        return response()->json([
            'message' => 'Inscription réussie',
            'participant' => $participant->load('employee.user')
        ]);
    }

    public function complete(Request $request, Training $training)
    {
        $request->validate([
            'employee_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'score' => 'nullable|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $participant = TrainingParticipant::where('training_id', $training->id)
            ->where('employee_id', $request->employee_id)
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'Participant non trouvé'
            ], 404);
        }

        $participant->update([
            'status' => 'completed',
            'score' => $request->score,
            'feedback' => $request->feedback,
            'completion_date' => now(),
        ]);

        return response()->json([
            'message' => 'Formation validée avec succès',
            'participant' => $participant
        ]);
    }

    public function stats()
    {
        $tenant = app('tenant');

        return response()->json([
            'total' => Training::where('tenant_id', $tenant->id)->count(),
            'planned' => Training::where('tenant_id', $tenant->id)->where('status', 'planned')->count(),
            'ongoing' => Training::where('tenant_id', $tenant->id)->where('status', 'ongoing')->count(),
            'completed' => Training::where('tenant_id', $tenant->id)->where('status', 'completed')->count(),
            'total_participants' => TrainingParticipant::where('tenant_id', $tenant->id)->count(),
            'total_cost' => Training::where('tenant_id', $tenant->id)->sum('cost'),
        ]);
    }
}