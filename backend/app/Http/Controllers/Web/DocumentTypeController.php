<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentType;
use App\Support\IconCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'document_types_per_page');
        $documentTypes = DocumentType::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.document-types.index', compact('documentTypes', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.document-types.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:document_types,code'],
            'label_en' => 'required|string|max:255',
            'label_th' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => ['nullable', 'string', Rule::in(IconCatalog::names())],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        DocumentType::create([
            'code' => $validated['code'],
            'label_en' => $validated['label_en'],
            'label_th' => $validated['label_th'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.document-types.index')
            ->with('success', __('common.saved'));
    }

    public function edit(DocumentType $documentType): View
    {
        return view('settings.document-types.form', compact('documentType'));
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $documentType->update(['is_active' => ! $documentType->is_active]);

            return redirect()->route('settings.document-types.index')->with('success', __('common.saved'));
        }

        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('document_types', 'code')->ignore($documentType->id)],
            'label_en' => 'required|string|max:255',
            'label_th' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => ['nullable', 'string', Rule::in(IconCatalog::names())],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $documentType->update([
            'code' => $validated['code'],
            'label_en' => $validated['label_en'],
            'label_th' => $validated['label_th'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.document-types.index')
            ->with('success', __('common.updated'));
    }

    private function normalizeCode(mixed $raw): string
    {
        return strtolower(str_replace(' ', '_', (string) $raw));
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        $hasForm = DocumentForm::where('document_type', $documentType->code)->exists();
        $hasWorkflow = ApprovalWorkflow::where('document_type', $documentType->code)->exists();

        if ($hasForm || $hasWorkflow) {
            return redirect()->route('settings.document-types.index')
                ->with('error', __('common.cannot_delete_document_type'));
        }

        $documentType->delete();

        return redirect()->route('settings.document-types.index')->with('success', __('common.deleted'));
    }
}
