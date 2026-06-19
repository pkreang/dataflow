<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $submission->reference_no ?: ('#' . $submission->id) }} — {{ $submission->form->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page { size: A4; margin: 16mm 14mm 18mm 14mm; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Sarabun", "Noto Sans Thai", Roboto, sans-serif; color: #111827; background: #fff; padding: 24px; max-width: 900px; margin: 0 auto; }
        .print-doc { width: 100%; border-collapse: collapse; }
        .print-doc > thead { display: table-header-group; }
        .print-doc > tbody > tr > td { padding: 0; }
        .page-break { page-break-after: always; height: 0; }
        .group-row { border: 1px solid #d1d5db; padding: 8px 10px; margin-top: 8px; }
        .group-row .group-row-title { font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600; }
        .group-row .group-grid { display: grid; gap: 6px 12px; }
        .print-header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 20px; }
        .print-header h1 { font-size: 20px; margin: 0 0 4px; }
        .print-header .meta { font-size: 12px; color: #4b5563; }
        .field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin-bottom: 24px; }
        .field-grid .full { grid-column: span 2; }
        .field label { display: block; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px; }
        .field .val { font-size: 14px; color: #111827; white-space: pre-wrap; word-break: break-word; }
        .approval-trail { border-top: 1px solid #e5e7eb; padding-top: 16px; }
        .approval-trail h2 { font-size: 14px; margin: 0 0 10px; }
        .step { display: flex; justify-content: space-between; font-size: 12px; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
        .step:last-child { border-bottom: none; }
        .step .label { font-weight: 600; }
        .step .action-approved { color: #059669; }
        .step .action-rejected { color: #dc2626; }
        .step .action-pending { color: #6b7280; }
        .print-toolbar { position: fixed; top: 12px; right: 12px; display: flex; gap: 8px; }
        .print-btn { background: #2563eb; color: #fff; padding: 6px 14px; border: 0; border-radius: 6px; font-size: 13px; cursor: pointer; }
        .print-btn.secondary { background: #6b7280; }
        .signature-table { width: 100%; border-collapse: collapse; margin-top: 24px; font-size: 12px; }
        .signature-table caption { caption-side: top; text-align: left; font-weight: 600; padding-bottom: 6px; }
        .signature-table th, .signature-table td { border: 1px solid #111827; padding: 6px 8px; vertical-align: middle; }
        .signature-table th { background: #f3f4f6; text-align: left; font-weight: 600; }
        .signature-table .sig-cell { width: 220px; height: 70px; text-align: center; }
        .signature-table .sig-cell img { max-height: 60px; max-width: 200px; object-fit: contain; }
        .signature-table .step-cell { width: 60px; text-align: center; font-weight: 600; }
        .signature-table .date-cell { width: 130px; }
        @media print {
            .print-toolbar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button class="print-btn" onclick="window.print()">{{ __('common.action_print') }}</button>
        <button class="print-btn secondary" onclick="window.close()">{{ __('common.cancel') }}</button>
    </div>

    <table class="print-doc">
        <thead>
            <tr><td>
                <div class="print-header">
                    <h1>{{ $submission->form->name }}</h1>
                    <div class="meta">
                        <div>{{ __('common.reference_no') }}: <strong>{{ $submission->reference_no ?: ('#' . $submission->id) }}</strong></div>
                        <div>{{ __('common.submitted_at') ?? 'ส่งเมื่อ' }}: {{ $submission->created_at->format('d M Y H:i') }}</div>
                        @if($submission->user)
                            <div>{{ __('common.requester') ?? 'ผู้ขอ' }}: {{ $submission->user->first_name }} {{ $submission->user->last_name }}</div>
                        @endif
                        @if($submission->orgUnit || $submission->department)
                            <div>{{ $submission->orgUnit ? __('common.org_unit') : __('common.department') }}: {{ $submission->orgUnit?->name ?? $submission->department?->name }}</div>
                        @endif
                    </div>
                </div>
            </td></tr>
        </thead>
        <tbody>
            <tr><td>

    <div class="field-grid">
        @foreach($submission->form->fields as $field)
            @php
                $val = $submission->payload[$field->field_key] ?? null;
                $display = is_array($val) ? implode(', ', array_map('strval', $val)) : (string) ($val ?? '');
                $isSection = $field->field_type === 'section';
                $isPageBreak = $field->field_type === 'page_break';
                $isGroup = $field->field_type === 'group';
                $isLong = in_array($field->field_type, ['textarea', 'signature', 'multi_file'], true);
            @endphp
            @if($isSection)
                <div class="full" style="margin-top: 12px; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; font-weight:600; font-size:13px; color:#374151;">
                    {{ $field->localized_label }}
                </div>
            @elseif($isPageBreak)
                <div class="full"><div class="page-break"></div></div>
            @elseif($field->field_type === 'qr_code')
                @php
                    $qrOpts = is_array($field->options) ? $field->options : [];
                    $qrPayload = \App\Support\QrTemplateResolver::resolve(
                        (string) ($qrOpts['template'] ?? ''),
                        $submission
                    );
                    $qrSize = (int) ($qrOpts['size'] ?? 128);
                    $qrLabelPos = (string) ($qrOpts['label_position'] ?? 'below');
                @endphp
                @if($qrPayload !== '')
                    <div class="field full" style="text-align:center;">
                        @if($qrLabelPos === 'above')
                            <div style="font-size:11px; margin-bottom:2px;">{{ $field->localized_label }}</div>
                        @endif
                        <canvas data-qr-payload="{{ $qrPayload }}"
                                data-qr-size="{{ $qrSize }}"
                                width="{{ $qrSize }}" height="{{ $qrSize }}"
                                style="display:inline-block;"></canvas>
                        @if($qrLabelPos === 'below')
                            <div style="font-size:11px; margin-top:2px;">{{ $field->localized_label }}</div>
                        @endif
                    </div>
                @endif
            @elseif($isGroup)
                @php
                    $groupOpts = is_array($field->options) ? $field->options : [];
                    $innerFields = is_array($groupOpts['fields'] ?? null) ? $groupOpts['fields'] : [];
                    $groupCols = max(1, min(4, (int) ($groupOpts['layout_columns'] ?? 1)));
                    $rows = is_array($val) ? array_values($val) : [];
                @endphp
                <div class="full">
                    <div class="field"><label>{{ $field->localized_label }}</label></div>
                    @forelse($rows as $rIdx => $row)
                        <div class="group-row">
                            <div class="group-row-title">#{{ $rIdx + 1 }}</div>
                            <div class="group-grid" style="grid-template-columns: repeat({{ $groupCols }}, minmax(0, 1fr));">
                                @foreach($innerFields as $inner)
                                    @php
                                        $iLabel = $inner['label_th'] ?? $inner['label'] ?? $inner['key'];
                                        $rawV = is_array($row) ? ($row[$inner['key']] ?? null) : null;
                                        $vDisp = is_array($rawV) ? implode(', ', array_map('strval', $rawV)) : (string) ($rawV ?? '');
                                    @endphp
                                    <div class="field">
                                        <label>{{ $iLabel }}</label>
                                        <div class="val">{{ $vDisp !== '' ? $vDisp : '—' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="val">—</div>
                    @endforelse
                </div>
            @elseif($field->field_type === 'multi_file')
                @php $paths = is_array($val) ? $val : []; @endphp
                <div class="field full">
                    <label>{{ $field->localized_label }}</label>
                    @if(count($paths) > 0)
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;">
                            @foreach($paths as $p)
                                @php $ext = strtolower(pathinfo((string) $p, PATHINFO_EXTENSION)); $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','heic','bmp'], true); @endphp
                                @if($isImg)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($p) }}" style="max-height:80px; border:1px solid #d1d5db;">
                                @else
                                    <span style="padding:4px 8px; border:1px solid #d1d5db; font-size:11px;">{{ basename((string) $p) }}</span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="val">—</div>
                    @endif
                </div>
            @else
                <div class="field {{ $isLong ? 'full' : '' }}">
                    <label>{{ $field->localized_label }}</label>
                    <div class="val">{{ $display !== '' ? $display : '—' }}</div>
                </div>
            @endif
        @endforeach
    </div>

    @if($submission->instance && $submission->instance->steps->isNotEmpty())
        <div class="approval-trail">
            <h2>{{ __('common.approval_history') ?? 'ประวัติการอนุมัติ' }}</h2>
            @foreach($submission->instance->steps as $step)
                @php
                    $actionClass = [
                        'approved' => 'action-approved',
                        'rejected' => 'action-rejected',
                    ][$step->action] ?? 'action-pending';
                @endphp
                <div class="step">
                    <span class="label">{{ $step->step_no }}. {{ $step->stage_name }}</span>
                    <span class="{{ $actionClass }}">
                        {{ __('common.approval_status_' . $step->action) }}
                        @if($step->actioned_at)
                            · {{ \Carbon\Carbon::parse($step->actioned_at)->format('d M Y H:i') }}
                        @endif
                    </span>
                </div>
            @endforeach
        </div>

        @php
            // Build the signature roster: one row per actor.
            // Approved steps → one row per approver in `approved_by`.
            // Rejected steps → one row from acted_by_user_id + signature_image.
            // Pending / never-acted steps are skipped.
            $signatureRows = [];
            foreach ($submission->instance->steps as $step) {
                if ($step->action === 'approved') {
                    foreach (($step->approved_by ?? []) as $entry) {
                        $signatureRows[] = [
                            'step' => $step->step_no,
                            'stage' => $step->stage_name,
                            'name' => $entry['name'] ?? '—',
                            'signature' => $entry['signature'] ?? null,
                            'at' => $entry['at'] ?? null,
                            'action' => 'approved',
                        ];
                    }
                } elseif ($step->action === 'rejected') {
                    $rejector = \App\Models\User::find($step->acted_by_user_id);
                    $signatureRows[] = [
                        'step' => $step->step_no,
                        'stage' => $step->stage_name,
                        'name' => $rejector?->full_name ?? '—',
                        'signature' => $step->signature_image,
                        'at' => $step->acted_at?->toIso8601String(),
                        'action' => 'rejected',
                    ];
                }
            }
        @endphp
        @if(! empty($signatureRows))
            <table class="signature-table">
                <caption>{{ __('common.authorized_signatures') }}</caption>
                <thead>
                    <tr>
                        <th class="step-cell">{{ __('common.workflow_step_short') }}</th>
                        <th>{{ __('common.workflow_stage_name') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.signature') }}</th>
                        <th class="date-cell">{{ __('common.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($signatureRows as $row)
                        <tr>
                            <td class="step-cell">{{ $row['step'] }}</td>
                            <td>{{ $row['stage'] }}</td>
                            <td>
                                {{ $row['name'] }}
                                @if($row['action'] === 'rejected')
                                    <div style="font-size:10px;color:#dc2626">{{ __('common.approval_status_rejected') }}</div>
                                @endif
                            </td>
                            <td class="sig-cell">
                                @if(! empty($row['signature']))
                                    <img src="{{ $row['signature'] }}" alt="">
                                @endif
                            </td>
                            <td class="date-cell">
                                {{ $row['at'] ? \Carbon\Carbon::parse($row['at'])->format('d M Y H:i') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

            </td></tr>
        </tbody>
    </table>

    <script>
        // Auto-open print dialog once. Wait long enough for QR canvases to
        // render — QRCode.toCanvas is async; 350 ms covers a typical page.
        window.addEventListener('load', () => {
            if (typeof window.renderFormQrCodes === 'function') {
                window.renderFormQrCodes();
            }
            setTimeout(() => window.print(), 350);
        });
    </script>
</body>
</html>
