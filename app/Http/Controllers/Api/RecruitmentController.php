<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recruitment;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; 

class RecruitmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Recruitment::with(['position', 'candidates']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $recruitments = $query->paginate($request->per_page ?? 15);
        return response()->json($recruitments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'requirements' => 'nullable|string',
            'position_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('positions', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'number_of_positions' => 'required|integer|min:1',
            'closing_date' => 'required|date|after:today',
            'status' => 'in:draft,published,closed,cancelled',
        ]);

        $recruitment = Recruitment::create([
            'tenant_id' => app('tenant')->id,
            'title' => $request->title,
            'description' => $request->description,
            'requirements' => $request->requirements,
            'position_id' => $request->position_id,
            'number_of_positions' => $request->number_of_positions,
            'posted_date' => Carbon::now(),
            'closing_date' => $request->closing_date,
            'status' => $request->status ?? 'draft',
        ]);

        return response()->json([
            'message' => 'Recrutement créé avec succès',
            'recruitment' => $recruitment->load(['position', 'candidates'])
        ], 201);
    }

    public function show(Recruitment $recruitment)
    {
        return response()->json([
            'recruitment' => $recruitment->load(['position', 'candidates'])
        ]);
    }

    public function update(Request $request, Recruitment $recruitment)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'requirements' => 'nullable|string',
            'position_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('positions', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'number_of_positions' => 'sometimes|integer|min:1',
            'closing_date' => 'sometimes|date|after:today',
            'status' => 'sometimes|in:draft,published,closed,cancelled',
        ]);

        $recruitment->update($request->all());

        return response()->json([
            'message' => 'Recrutement mis à jour avec succès',
            'recruitment' => $recruitment->fresh()->load(['position', 'candidates'])
        ]);
    }

    public function destroy(Recruitment $recruitment)
    {
        $recruitment->delete();
        return response()->json(['message' => 'Recrutement supprimé avec succès']);
    }

    public function publish(Recruitment $recruitment)
    {
        $recruitment->update([
            'status' => 'published',
            'posted_date' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Recrutement publié avec succès',
            'recruitment' => $recruitment
        ]);
    }

    public function addCandidate(Request $request, Recruitment $recruitment)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:candidates,email,NULL,id,recruitment_id,' . $recruitment->id,
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string',
            'expected_salary' => 'nullable|numeric|min:0',
            'available_from' => 'nullable|date',
        ]);

        $candidate = Candidate::create([
            'tenant_id' => app('tenant')->id,
            'recruitment_id' => $recruitment->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'cover_letter' => $request->cover_letter,
            'expected_salary' => $request->expected_salary,
            'available_from' => $request->available_from,
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'Candidat ajouté avec succès',
            'candidate' => $candidate
        ], 201);
    }

    public function updateCandidate(Request $request, Recruitment $recruitment, Candidate $candidate)
    {
        abort_unless($candidate->recruitment_id === $recruitment->id, 404);

        $request->validate([
            'status' => 'required|in:new,screened,interviewed,offered,hired,rejected',
            'feedback' => 'nullable|string',
        ]);

        $candidate->update($request->only(['status', 'feedback']));

        return response()->json([
            'message' => 'Candidat mis à jour avec succès',
            'candidate' => $candidate
        ]);
    }

    public function stats()
    {
        $tenant = app('tenant');

        return response()->json([
            'total' => Recruitment::where('tenant_id', $tenant->id)->count(),
            'published' => Recruitment::where('tenant_id', $tenant->id)->where('status', 'published')->count(),
            'closed' => Recruitment::where('tenant_id', $tenant->id)->where('status', 'closed')->count(),
            'total_candidates' => Candidate::where('tenant_id', $tenant->id)->count(),
            'by_status' => Candidate::where('tenant_id', $tenant->id)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get(),
        ]);
    }
}