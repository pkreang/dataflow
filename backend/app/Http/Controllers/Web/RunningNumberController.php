<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentType;
use App\Models\RunningNumberConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RunningNumberController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'running_numbers_per_page');
        $configs = RunningNumberConfig::query()
            ->orderBy('document_type')
            ->paginate($perPage)
            ->withQueryString();

        // Group form names by document_type so the index can show "used by"
        // beside each running-number row. Running numbers are keyed per
        // document_type (not per form) — multiple forms with the same type
        // share one number lane, which is non-obvious without seeing it here.
        $formsByType = DocumentForm::query()
            ->orderBy('name')
            ->get(['name', 'document_type'])
            ->groupBy('document_type')
            ->map(fn ($forms) => $forms->pluck('name')->all())
            ->all();

        return view('settings.running-numbers.index', compact('configs', 'formsByType', 'perPage'));
    }

    public function create(): View
    {
        $usedTypes = RunningNumberConfig::pluck('document_type')->toArray();
        $documentTypes = DocumentType::allActive()->filter(fn ($dt) => ! in_array($dt->code, $usedTypes));

        return view('settings.running-numbers.create', compact('documentTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        RunningNumberConfig::create($validated);

        return redirect()->route('settings.running-numbers.index')->with('success', __('common.saved'));
    }

    public function edit(RunningNumberConfig $runningNumberConfig): View
    {
        $documentTypes = DocumentType::allActive();

        return view('settings.running-numbers.edit', compact('runningNumberConfig', 'documentTypes'));
    }

    public function update(Request $request, RunningNumberConfig $runningNumberConfig): RedirectResponse
    {
        $rules = $this->rules();
        $rules['document_type'] = "required|string|max:50|unique:running_number_configs,document_type,{$runningNumberConfig->id}";
        $validated = $request->validate($rules);

        $runningNumberConfig->update($validated);

        return redirect()->route('settings.running-numbers.index')->with('success', __('common.updated'));
    }

    public function destroy(RunningNumberConfig $runningNumberConfig): RedirectResponse
    {
        $runningNumberConfig->delete();

        return redirect()->route('settings.running-numbers.index')->with('success', __('common.deleted'));
    }

    public function reset(RunningNumberConfig $runningNumberConfig): RedirectResponse
    {
        $runningNumberConfig->update(['last_number' => 0, 'last_reset_at' => now()->toDateString()]);

        return redirect()->route('settings.running-numbers.index')->with('success', __('common.running_number_reset_done'));
    }

    private function rules(): array
    {
        return [
            'document_type' => 'required|string|max:50|unique:running_number_configs,document_type',
            'prefix' => 'required|string|max:20',
            'digit_count' => 'required|integer|min:1|max:10',
            'reset_mode' => 'required|in:none,yearly,monthly',
            'include_year' => 'nullable|boolean',
            'include_month' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }
}
