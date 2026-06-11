<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{
    use HasPerPage;

    public function importForm(): View
    {
        return view('settings.positions.import');
    }

    public function downloadTemplate()
    {
        $csv = "name,code,description\n";
        $csv .= "ครู,SCH_TEACHER,\n";
        $csv .= "หัวหน้าฝ่ายวิชาการ,SCH_ACAD_HEAD,\n";

        return response()->streamDownload(
            fn () => print ($csv),
            'positions_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('file')->getRealPath();
        $lines = array_map('str_getcsv', file($path));
        $header = array_shift($lines);

        $created = $updated = $skipped = 0;
        $errors = [];

        foreach ($lines as $i => $row) {
            if (count($row) < 1 || empty(trim($row[0]))) {
                $skipped++;

                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), null));
            $name = trim($data['name'] ?? $data['ชื่อ'] ?? '');
            $code = strtoupper(trim($data['code'] ?? $data['รหัส'] ?? ''));
            $desc = trim($data['description'] ?? $data['คำอธิบาย'] ?? '') ?: null;

            if (empty($name)) {
                $skipped++;

                continue;
            }

            try {
                $unique = $code !== ''
                    ? Position::where('code', $code)->first()
                    : Position::where('name', $name)->first();

                if ($unique) {
                    $unique->update([
                        'name' => $name,
                        'code' => $code !== '' ? $code : $unique->code,
                        'description' => $desc,
                    ]);
                    $updated++;
                } else {
                    Position::create([
                        'name' => $name,
                        'code' => $code !== '' ? $code : strtoupper(substr(preg_replace('/\s+/', '_', $name), 0, 20)),
                        'description' => $desc,
                        'is_active' => true,
                    ]);
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Row '.($i + 2).': '.$e->getMessage();
            }
        }

        $message = __('common.import_result', compact('created', 'updated', 'skipped'));
        if (! empty($errors)) {
            return redirect()->route('settings.positions.import')
                ->with('success', $message)
                ->with('import_errors', array_slice($errors, 0, 10));
        }

        return redirect()->route('settings.positions.index')->with('success', $message);
    }

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'positions_per_page');
        $positions = Position::query()
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.positions.index', compact('positions', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.positions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', 'unique:positions,code'],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        Position::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.positions.index')
            ->with('success', __('common.saved'));
    }

    public function edit(Position $position): View
    {
        return view('settings.positions.edit', compact('position'));
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $position->update(['is_active' => ! $position->is_active]);

            return redirect()->route('settings.positions.index')->with('success', __('common.saved'));
        }

        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('positions', 'code')->ignore($position->id)],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $position->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.positions.index')
            ->with('success', __('common.updated'));
    }

    public function destroy(Position $position): RedirectResponse
    {
        if (User::query()->where('position_id', $position->id)->exists()) {
            return redirect()->route('settings.positions.index')
                ->with('error', __('common.cannot_delete_position_has_users'));
        }

        $position->delete();

        return redirect()->route('settings.positions.index')->with('success', __('common.deleted'));
    }

    private function normalizeCode(mixed $raw): string
    {
        return strtoupper(trim((string) $raw));
    }
}
