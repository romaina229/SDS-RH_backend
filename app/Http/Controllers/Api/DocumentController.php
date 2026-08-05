<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with(['employee.user']);

        if ($request->user()->hasRole('employee') && $request->user()->employee) {
            $query->where('employee_id', $request->user()->employee->id);
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $documents = $query->paginate($request->per_page ?? 15);

        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', app('tenant')->id)),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:contract,diploma,id_card,pay_slip,certificate,cv,photo,medical,other',
            'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', // 10MB
            'expiry_date' => 'nullable|date|after:today',
            'is_confidential' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($request->user()->hasRole('employee')) {
            abort_unless($request->user()->employee, 403, 'Aucun dossier employé associé à ce compte.');
            $request->merge(['employee_id' => $request->user()->employee->id]);
        }

        $file = $request->file('document');
        $fileName = Str::slug($request->name) . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents/' . ($request->employee_id ?? 'general'), $fileName, 'local');

        $document = Document::create([
            'tenant_id' => app('tenant')->id,
            'employee_id' => $request->employee_id,
            'name' => $request->name,
            'type' => $request->type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'expiry_date' => $request->expiry_date,
            'is_confidential' => $request->is_confidential ?? false,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'message' => 'Document téléchargé avec succès',
            'document' => $document->load('employee.user')
        ], 201);
    }

    public function show(Document $document)
    {
        $this->assertEmployeeAccess($document);

        return response()->json([
            'document' => $document->load('employee.user')
        ]);
    }

    public function destroy(Document $document)
    {
        $this->assertEmployeeAccess($document);

        // Supprimer le fichier physique
        Storage::disk('local')->delete($document->file_path);

        $document->delete();

        return response()->json([
            'message' => 'Document supprimé avec succès'
        ]);
    }

    public function download(Document $document)
    {
        $this->assertEmployeeAccess($document);

        if (!Storage::disk('local')->exists($document->file_path)) {
            return response()->json([
                'message' => 'Fichier introuvable'
            ], 404);
        }

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->file_name
        );
    }

    public function employeeDocuments(Employee $employee)
    {
        $documents = Document::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'employee' => $employee->load('user'),
            'documents' => $documents
        ]);
    }

    public function types()
    {
        return response()->json([
            'types' => [
                'contract' => 'Contrat',
                'diploma' => 'Diplôme',
                'id_card' => 'Pièce d\'identité',
                'pay_slip' => 'Bulletin de paie',
                'certificate' => 'Certificat',
                'cv' => 'CV',
                'photo' => 'Photo',
                'medical' => 'Médical',
                'other' => 'Autre',
            ]
        ]);
    }
    private function assertEmployeeAccess(Document $document): void
    {
        $user = request()->user();

        if ($user?->hasRole('employee') && (int) $document->employee_id !== (int) $user->employee?->id) {
            abort(403, 'Accès à ce document interdit.');
        }
    }

}