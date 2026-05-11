<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HasPerPage;
use App\Models\DocumentForm;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Support\LookupRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LookupListController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'lookups_per_page');
        $lists = LookupList::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('label_en')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.lookups.index', compact('lists', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.lookups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        DB::transaction(function () use ($validated) {
            $list = LookupList::create([
                'key' => $validated['key'],
                'label_en' => $validated['label_en'],
                'label_th' => $validated['label_th'],
                'description' => $validated['description'] ?? null,
                'required_permission' => $validated['required_permission'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_system' => false,
            ]);
            $this->syncItems($list, $validated['items'] ?? []);
        });

        return redirect()->route('settings.lookups.index')->with('success', __('common.saved'));
    }

    public function edit(LookupList $lookup): View
    {
        $lookup->load('items');

        return view('settings.lookups.edit', ['lookup' => $lookup]);
    }

    public function update(Request $request, LookupList $lookup): RedirectResponse
    {
        $validated = $this->validatedPayload($request, $lookup);

        DB::transaction(function () use ($validated, $lookup) {
            // `key` is immutable for system lists — prevent renaming built-in references.
            $lookup->update([
                'key' => $lookup->is_system ? $lookup->key : $validated['key'],
                'label_en' => $validated['label_en'],
                'label_th' => $validated['label_th'],
                'description' => $validated['description'] ?? null,
                'required_permission' => $validated['required_permission'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);
            $this->syncItems($lookup, $validated['items'] ?? []);
        });

        return redirect()->route('settings.lookups.edit', $lookup)->with('success', __('common.saved'));
    }

    public function destroy(LookupList $lookup): RedirectResponse
    {
        if ($lookup->is_system) {
            return redirect()->route('settings.lookups.index')
                ->withErrors(['delete' => __('common.lookup_system_protected')]);
        }

        $referencingForms = $this->formsReferencing($lookup->key);
        if ($referencingForms->isNotEmpty()) {
            return redirect()->route('settings.lookups.index')
                ->withErrors(['delete' => __('common.lookup_in_use', ['forms' => $referencingForms->implode(', ')])]);
        }

        $lookup->delete();

        return redirect()->route('settings.lookups.index')->with('success', __('common.deleted'));
    }

    public function exportCsv(LookupList $lookup): StreamedResponse
    {
        $filename = 'lookup-'.$lookup->key.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($lookup) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel Thai compatibility
            $out = fopen('php://output', 'w');
            fputcsv($out, ['value', 'label_th', 'label_en', 'sort_order', 'is_active', 'extra']);
            foreach ($lookup->items()->orderBy('sort_order')->orderBy('id')->get() as $item) {
                fputcsv($out, [
                    $item->value,
                    $item->label_th,
                    $item->label_en,
                    (int) $item->sort_order,
                    $item->is_active ? '1' : '0',
                    $item->extra ? json_encode($item->extra, JSON_UNESCAPED_UNICODE) : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCsv(Request $request, LookupList $lookup): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'mode' => ['required', Rule::in(['replace', 'append'])],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->withErrors(['file' => 'ไม่สามารถเปิดไฟล์ได้']);
        }

        // Strip UTF-8 BOM from the first line if present.
        $first = fgets($handle);
        if ($first !== false) {
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            rewind($handle);
            fgets($handle); // re-read header
            // But we need to skip BOM — simplest: stream via a string stream
            fclose($handle);
            $csv = file_get_contents($path);
            $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $csv);
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (! $header || ! in_array('value', array_map('strtolower', $header), true)) {
            fclose($handle);

            return back()->withErrors(['file' => 'CSV ต้องมีคอลัมน์ value เป็นอย่างน้อย (header row)']);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $idx = array_flip($header);

        $rows = [];
        $rowNo = 1; // header is row 1
        $errors = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowNo++;
            if (count($row) === 1 && trim($row[0]) === '') {
                continue; // blank line
            }
            $value = trim($row[$idx['value']] ?? '');
            if ($value === '') {
                $errors[] = "row {$rowNo}: value ว่าง";

                continue;
            }
            $rows[] = [
                'value' => $value,
                'label_th' => trim($row[$idx['label_th'] ?? -1] ?? $value),
                'label_en' => trim($row[$idx['label_en'] ?? -1] ?? $value),
                'sort_order' => (int) ($row[$idx['sort_order'] ?? -1] ?? count($rows)),
                'is_active' => ! empty($row[$idx['is_active'] ?? -1]) && $row[$idx['is_active']] !== '0',
                'extra_raw' => $row[$idx['extra'] ?? -1] ?? '',
            ];
        }
        fclose($handle);

        if ($errors) {
            return back()->withErrors(['file' => implode(', ', array_slice($errors, 0, 5))]);
        }

        DB::transaction(function () use ($rows, $lookup, $request) {
            if ($request->mode === 'replace') {
                $lookup->items()->delete();
            }
            foreach ($rows as $r) {
                LookupListItem::updateOrCreate(
                    ['list_id' => $lookup->id, 'value' => $r['value']],
                    [
                        'label_th' => $r['label_th'],
                        'label_en' => $r['label_en'],
                        'sort_order' => $r['sort_order'],
                        'is_active' => $r['is_active'],
                        'extra' => $r['extra_raw'] ? (json_decode($r['extra_raw'], true) ?: null) : null,
                    ]
                );
            }
        });

        return redirect()->route('settings.lookups.edit', $lookup)
            ->with('success', __('common.lookup_csv_imported', ['count' => count($rows)]));
    }

    private function validatedPayload(Request $request, ?LookupList $existing = null): array
    {
        $builtIn = LookupRegistry::builtInSourceKeys();

        $validator = validator($request->all(), [
            'key' => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('lookup_lists', 'key')->ignore($existing?->id),
                function ($attr, $value, $fail) use ($builtIn) {
                    if (in_array($value, $builtIn, true)) {
                        $fail(__('common.lookup_key_reserved'));
                    }
                },
            ],
            'label_en' => ['required', 'string', 'max:100'],
            'label_th' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'required_permission' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.value' => ['required', 'string', 'max:100'],
            'items.*.label_en' => ['required', 'string', 'max:255'],
            'items.*.label_th' => ['required', 'string', 'max:255'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:lookup_list_items,id'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.is_active' => ['nullable', 'boolean'],
            'items.*.extra' => ['nullable', 'string'],
        ]);

        $validator->after(function ($v) use ($request) {
            $items = $request->input('items');
            if (! is_array($items)) {
                return;
            }
            $seenValues = [];
            foreach ($items as $idx => $item) {
                $value = $item['value'] ?? '';
                if ($value === '') {
                    continue;
                }
                if (isset($seenValues[$value])) {
                    $v->errors()->add("items.{$idx}.value", __('common.lookup_duplicate_value'));
                    $v->errors()->add("items.{$seenValues[$value]}.value", __('common.lookup_duplicate_value'));
                } else {
                    $seenValues[$value] = $idx;
                }
                if (! empty($item['extra'])) {
                    $decoded = json_decode($item['extra'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $v->errors()->add("items.{$idx}.extra", __('common.lookup_invalid_json'));
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function syncItems(LookupList $list, array $items): void
    {
        // Replace the full item set: simplest correct semantics for a flat pick-list.
        $list->items()->delete();

        foreach ($items as $index => $item) {
            LookupListItem::create([
                'list_id' => $list->id,
                'value' => $item['value'],
                'label_en' => $item['label_en'],
                'label_th' => $item['label_th'],
                'parent_id' => ! empty($item['parent_id']) ? (int) $item['parent_id'] : null,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'is_active' => (bool) ($item['is_active'] ?? true),
                'extra' => ! empty($item['extra']) ? json_decode($item['extra'], true) : null,
            ]);
        }
    }

    /**
     * Forms that reference a lookup by source key (via options.source in fields).
     * Used to block deletion when the list is actively wired into a form.
     */
    private function formsReferencing(string $sourceKey): \Illuminate\Support\Collection
    {
        return DocumentForm::query()
            ->whereHas('fields', function ($q) use ($sourceKey) {
                $q->where('field_type', 'lookup')
                    ->whereRaw("json_extract(options, '$.source') = ?", [$sourceKey]);
            })
            ->pluck('name');
    }
}
