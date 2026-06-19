<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>{{ $submission->reference_no ?: ('#' . $submission->id) }}</title>
<style>
@font-face {
    font-family: 'Sarabun';
    font-style: normal;
    font-weight: normal;
    src: url('{{ storage_path('fonts/sarabun/Sarabun-Regular.ttf') }}');
}
@font-face {
    font-family: 'Sarabun';
    font-style: normal;
    font-weight: bold;
    src: url('{{ storage_path('fonts/sarabun/Sarabun-Bold.ttf') }}');
}
* { box-sizing: border-box; }
body {
    font-family: 'Sarabun', sans-serif;
    font-size: 13px;
    color: #111827;
    background: #fff;
    margin: 0; padding: 0;
}

/* ── Header ─────────────────────────────────────────────── */
.header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 16px; }
.header h1 { font-size: 18px; font-weight: bold; margin: 0 0 6px 0; }
.header-meta { font-size: 11px; color: #4b5563; }
.header-meta td { padding: 1px 12px 1px 0; vertical-align: top; }
.header-meta .lbl { color: #6b7280; }

/* ── Field grid (mirrors web col_span logic) ─────────────── */
.field-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.field-grid td { vertical-align: top; padding: 4px 10px 6px 0; }
.field-label {
    display: block;
    font-size: 10px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 2px;
}
.field-val { font-size: 13px; color: #111827; }

/* ── Section header ──────────────────────────────────────── */
.section-hdr {
    font-weight: bold; font-size: 13px; color: #374151;
    border-bottom: 1px solid #d1d5db;
    padding-bottom: 3px; margin: 10px 0 6px 0;
}

/* ── Group / repeater ────────────────────────────────────── */
.group-row { border: 1px solid #d1d5db; padding: 6px 8px; margin-bottom: 5px; }
.group-row-title { font-size: 10px; color: #6b7280; font-weight: bold; margin-bottom: 4px; }
.group-inner { width: 100%; border-collapse: collapse; }
.group-inner td { padding: 2px 8px 2px 0; vertical-align: top; font-size: 12px; }

/* ── Table field ─────────────────────────────────────────── */
.tbl-field { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 4px; }
.tbl-field th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 3px 6px; font-weight: bold; text-align: left; }
.tbl-field td { border: 1px solid #d1d5db; padding: 3px 6px; }

/* ── Approval trail ──────────────────────────────────────── */
.approval-section { border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 8px; }
.approval-title { font-size: 13px; font-weight: bold; margin-bottom: 8px; }
.approval-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.approval-table th { background: #f3f4f6; font-weight: bold; padding: 4px 6px; border: 1px solid #d1d5db; text-align: left; }
.approval-table td { padding: 4px 6px; border: 1px solid #d1d5db; vertical-align: middle; }
.s-approved { color: #059669; font-weight: bold; }
.s-rejected  { color: #dc2626; font-weight: bold; }
.s-pending   { color: #6b7280; }

/* ── Signature table ─────────────────────────────────────── */
.sig-title { font-size: 12px; font-weight: bold; margin: 14px 0 6px; }
.sig-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.sig-table th { background: #f3f4f6; font-weight: bold; padding: 4px 6px; border: 1px solid #111827; text-align: left; }
.sig-table td { padding: 4px 6px; border: 1px solid #111827; vertical-align: middle; }
.sig-cell { width: 180px; height: 60px; text-align: center; }
.sig-cell img { max-height: 52px; max-width: 160px; }

/* ── Footer ──────────────────────────────────────────────── */
.footer { border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 5px; font-size: 10px; color: #9ca3af; text-align: right; }

.page-break { page-break-after: always; }
</style>
</head>
<body>

{{-- ===== HEADER ===== --}}
<div class="header">
    <h1>{{ $submission->form->name }}</h1>
    <table class="header-meta"><tbody><tr>
        <td><span class="lbl">{{ __('common.reference_no') }}: </span><strong>{{ $submission->reference_no ?: ('#' . $submission->id) }}</strong></td>
        <td><span class="lbl">{{ __('common.submitted_at') }}: </span>{{ $submission->created_at->format('d M Y H:i') }}</td>
        @if($submission->instance)
        <td><span class="lbl">{{ __('common.status') }}: </span><strong>{{ __('common.approval_status_' . $submission->instance->status) }}</strong></td>
        @endif
    </tr><tr>
        @if($submission->user)
        <td><span class="lbl">{{ __('common.requester') }}: </span>{{ $submission->user->first_name }} {{ $submission->user->last_name }}</td>
        @endif
        @if($submission->orgUnit || $submission->department)
        <td><span class="lbl">{{ $submission->orgUnit ? __('common.org_unit') : __('common.department') }}: </span>{{ $submission->orgUnit?->name ?? $submission->department?->name }}</td>
        @endif
        <td></td>
    </tr></tbody></table>
</div>

{{-- ===== FIELDS ===== --}}
{{--
    Mirrors web rendering:
    - form->layout_columns  = total grid columns (1–4)
    - field->col_span       = how many columns this field spans (1–layout_columns)
    - fields that span full width OR are special types are rendered as a full-width row
    - other fields are packed into a row until the row is "full"
--}}
@php
    $gridCols   = max(1, (int) ($submission->form->layout_columns ?? 1));
    $fields     = $submission->form->fields->sortBy('sort_order');

    // Width of each logical column as a percentage
    $colWidthPct = floor(100 / $gridCols);

    // Buffer: [ ['span' => N, 'html' => '...'], ... ]
    $rowBuffer  = [];
    $rowSpanUsed = 0;

    $fullWidthTypes = ['section','page_break','group','table','multi_file','signature','qr_code'];
@endphp

@foreach($fields as $field)
@php
    $key     = $field->field_key;
    $type    = $field->field_type;
    $label   = $field->localized_label;
    $val     = $submission->payload[$key] ?? null;
    $display = is_array($val) ? implode(', ', array_map('strval', $val)) : (string) ($val ?? '');

    // col_span: respect form's grid columns
    $rawSpan = (int) ($field->col_span ?? 1);
    $span    = ($gridCols > 1) ? min($rawSpan, $gridCols) : 1;

    // Force full-width for structural / complex types
    $isFull  = in_array($type, $fullWidthTypes, true) || ($span >= $gridCols);

    // Helper: flush buffer to table row
    $flushBuffer = function() use (&$rowBuffer, &$rowSpanUsed, $gridCols, $colWidthPct) {
        if (empty($rowBuffer)) return '';
        $html = '<tr>';
        $used = 0;
        foreach ($rowBuffer as $cell) {
            $html .= $cell['html'];
            $used += $cell['span'];
        }
        // Fill remaining columns with empty cell
        if ($used < $gridCols) {
            $html .= '<td colspan="' . ($gridCols - $used) . '"></td>';
        }
        $html .= '</tr>';
        $rowBuffer  = [];
        $rowSpanUsed = 0;
        return $html;
    };
@endphp

{{-- ── section ───────────────────────────────────────── --}}
@if($type === 'section')
    {!! $flushBuffer() !!}
    <table class="field-grid"><tbody><tr>
        <td colspan="{{ $gridCols }}"><div class="section-hdr">{{ $label }}</div></td>
    </tr></tbody></table>

{{-- ── page_break ─────────────────────────────────────── --}}
@elseif($type === 'page_break')
    {!! $flushBuffer() !!}
    <div class="page-break"></div>

{{-- ── group (repeater) ──────────────────────────────── --}}
@elseif($type === 'group')
    {!! $flushBuffer() !!}
    @php
        $gOpts      = is_array($field->options) ? $field->options : [];
        $innerFlds  = is_array($gOpts['fields'] ?? null) ? $gOpts['fields'] : [];
        $gCols      = max(1, min(4, (int) ($gOpts['layout_columns'] ?? 1)));
        $groupRows  = is_array($val) ? array_values($val) : [];
    @endphp
    <table class="field-grid"><tbody>
        <tr><td colspan="{{ $gridCols }}">
            <span class="field-label">{{ $label }}</span>
            @forelse($groupRows as $rIdx => $row)
                <div class="group-row">
                    <div class="group-row-title">#{{ $rIdx + 1 }}</div>
                    <table class="group-inner"><tbody>
                    @foreach(array_chunk($innerFlds, $gCols) as $chunk)
                        <tr>
                        @foreach($chunk as $inner)
                            @php
                                $iLabel = $inner['label_th'] ?? $inner['label'] ?? ($inner['key'] ?? '');
                                $rawV   = is_array($row) ? ($row[$inner['key'] ?? ''] ?? null) : null;
                                $vDisp  = is_array($rawV) ? implode(', ', array_map('strval', $rawV)) : (string) ($rawV ?? '');
                            @endphp
                            <td style="width:{{ floor(100/$gCols) }}%">
                                <span class="field-label">{{ $iLabel }}</span>
                                <span class="field-val">{{ $vDisp !== '' ? $vDisp : '—' }}</span>
                            </td>
                        @endforeach
                        </tr>
                    @endforeach
                    </tbody></table>
                </div>
            @empty
                <span class="field-val" style="color:#9ca3af">—</span>
            @endforelse
        </td></tr>
    </tbody></table>

{{-- ── table field ────────────────────────────────────── --}}
@elseif($type === 'table')
    {!! $flushBuffer() !!}
    @php
        $tOpts = is_array($field->options) ? $field->options : [];
        $tCols = is_array($tOpts['columns'] ?? null) ? $tOpts['columns'] : [];
        $tRows = is_array($val) ? array_values($val) : [];
    @endphp
    <table class="field-grid"><tbody>
        <tr><td colspan="{{ $gridCols }}">
            <span class="field-label">{{ $label }}</span>
            @if(!empty($tCols))
            <table class="tbl-field">
                <thead><tr>
                    @foreach($tCols as $tc)
                        <th>{{ $tc['label'] ?? $tc['key'] ?? '' }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach($tRows as $tr)
                    <tr>
                    @foreach($tCols as $tc)
                        <td>{{ is_array($tr) ? ($tr[$tc['key'] ?? ''] ?? '—') : '—' }}</td>
                    @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            @else
                <span class="field-val" style="color:#9ca3af">—</span>
            @endif
        </td></tr>
    </tbody></table>

{{-- ── multi_file ─────────────────────────────────────── --}}
@elseif($type === 'multi_file')
    {!! $flushBuffer() !!}
    @php $paths = is_array($val) ? $val : []; @endphp
    <table class="field-grid"><tbody>
        <tr><td colspan="{{ $gridCols }}">
            <span class="field-label">{{ $label }}</span>
            @if($paths)
                <div style="margin-top:4px;">
                @foreach($paths as $p)
                    @php
                        $ext   = strtolower(pathinfo((string) $p, PATHINFO_EXTENSION));
                        $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true);
                        $lpath = storage_path('app/public/' . ltrim((string) $p, '/storage/'));
                    @endphp
                    @if($isImg && file_exists($lpath))
                        <img src="{{ $lpath }}" style="max-height:70px;border:1px solid #d1d5db;margin-right:4px;">
                    @else
                        <span style="padding:2px 6px;border:1px solid #d1d5db;font-size:10px;">{{ basename((string) $p) }}</span>
                    @endif
                @endforeach
                </div>
            @else
                <span class="field-val" style="color:#9ca3af">—</span>
            @endif
        </td></tr>
    </tbody></table>

{{-- ── signature ───────────────────────────────────────── --}}
@elseif($type === 'signature')
    {!! $flushBuffer() !!}
    <table class="field-grid"><tbody>
        <tr><td colspan="{{ $gridCols }}">
            <span class="field-label">{{ $label }}</span>
            @if($val && str_starts_with((string) $val, 'data:'))
                <div><img src="{{ $val }}" style="max-height:60px;max-width:200px;border:1px solid #d1d5db;margin-top:3px;"></div>
            @elseif($val)
                <span class="field-val">{{ $val }}</span>
            @else
                <span class="field-val" style="color:#9ca3af">—</span>
            @endif
        </td></tr>
    </tbody></table>

{{-- ── qr_code ─────────────────────────────────────────── --}}
@elseif($type === 'qr_code')
    {!! $flushBuffer() !!}
    @php
        $qrOpts = is_array($field->options) ? $field->options : [];
        $qrText = \App\Support\QrTemplateResolver::resolve((string) ($qrOpts['template'] ?? ''), $submission);
    @endphp
    @if($qrText)
    <table class="field-grid"><tbody>
        <tr><td colspan="{{ $gridCols }}">
            <span class="field-label">{{ $label }}</span>
            <span class="field-val" style="font-size:11px;color:#374151;">{{ $qrText }}</span>
        </td></tr>
    </tbody></table>
    @endif

{{-- ── regular field (respects col_span + grid packing) ── --}}
@else
    @php
        $cellHtml = '<td colspan="' . $span . '" style="width:' . ($colWidthPct * $span) . '%;vertical-align:top;padding:3px 10px 6px 0;">'
            . '<span class="field-label">' . e($label) . '</span>'
            . '<div class="field-val">' . e($display !== '' ? $display : '—') . '</div>'
            . '</td>';

        // If this field won't fit in current row → flush first
        if ($rowSpanUsed + $span > $gridCols) {
            echo '<table class="field-grid"><tbody>';
            echo $flushBuffer();
            echo '</tbody></table>';
        }

        $rowBuffer[]  = ['span' => $span, 'html' => $cellHtml];
        $rowSpanUsed += $span;

        // If row is now full → flush
        if ($rowSpanUsed >= $gridCols) {
            echo '<table class="field-grid"><tbody>';
            echo $flushBuffer();
            echo '</tbody></table>';
        }
    @endphp
@endif

@endforeach

{{-- Flush any remaining cells in buffer --}}
@php
    if (!empty($rowBuffer)) {
        echo '<table class="field-grid"><tbody>';
        echo $flushBuffer();
        echo '</tbody></table>';
    }
@endphp

{{-- ===== APPROVAL TRAIL ===== --}}
@if($submission->instance && $submission->instance->steps->isNotEmpty())
<div class="approval-section">
    <div class="approval-title">{{ __('common.approval_history') }}</div>
    <table class="approval-table">
        <thead>
            <tr>
                <th style="width:28px">#</th>
                <th>{{ __('common.workflow_stage_name') }}</th>
                <th style="width:80px">{{ __('common.status') }}</th>
                <th>{{ __('common.comment') }}</th>
                <th style="width:110px">{{ __('common.date') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($submission->instance->steps as $step)
            @php $cls = match($step->action) { 'approved' => 's-approved', 'rejected' => 's-rejected', default => 's-pending' }; @endphp
            <tr>
                <td>{{ $step->step_no }}</td>
                <td>{{ $step->stage_name }}</td>
                <td class="{{ $cls }}">{{ __('common.approval_status_' . ($step->action ?? 'pending')) }}</td>
                <td>{{ $step->comment ?? '—' }}</td>
                <td>{{ $step->actioned_at ? \Carbon\Carbon::parse($step->actioned_at)->format('d M Y H:i') : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Signature roster --}}
    @php
        $sigRows = [];
        foreach ($submission->instance->steps as $step) {
            if ($step->action === 'approved') {
                foreach (($step->approved_by ?? []) as $entry) {
                    $sigRows[] = ['step' => $step->step_no, 'stage' => $step->stage_name, 'name' => $entry['name'] ?? '—', 'sig' => $entry['signature'] ?? null, 'at' => $entry['at'] ?? null, 'action' => 'approved'];
                }
            } elseif ($step->action === 'rejected') {
                $rejector = \App\Models\User::find($step->acted_by_user_id);
                $sigRows[] = ['step' => $step->step_no, 'stage' => $step->stage_name, 'name' => $rejector?->full_name ?? '—', 'sig' => $step->signature_image, 'at' => $step->acted_at?->toIso8601String(), 'action' => 'rejected'];
            }
        }
    @endphp
    @if(!empty($sigRows))
    <div class="sig-title">{{ __('common.authorized_signatures') }}</div>
    <table class="sig-table">
        <thead>
            <tr>
                <th style="width:28px">#</th>
                <th>{{ __('common.workflow_stage_name') }}</th>
                <th>{{ __('common.name') }}</th>
                <th class="sig-cell">{{ __('common.signature') }}</th>
                <th style="width:110px">{{ __('common.date') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($sigRows as $row)
            <tr>
                <td>{{ $row['step'] }}</td>
                <td>{{ $row['stage'] }}</td>
                <td>
                    {{ $row['name'] }}
                    @if($row['action'] === 'rejected')
                        <div style="font-size:9px;color:#dc2626">{{ __('common.approval_status_rejected') }}</div>
                    @endif
                </td>
                <td class="sig-cell">
                    @if(!empty($row['sig']) && str_starts_with((string) $row['sig'], 'data:'))
                        <img src="{{ $row['sig'] }}">
                    @endif
                </td>
                <td>{{ $row['at'] ? \Carbon\Carbon::parse($row['at'])->format('d M Y H:i') : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endif

{{-- ===== FOOTER ===== --}}
<div class="footer">
    {{ config('app.name') }} &nbsp;·&nbsp; {{ __('common.generated_at') }} {{ now()->format('d M Y H:i') }}
</div>

</body>
</html>
