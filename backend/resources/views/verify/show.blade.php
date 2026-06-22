@php
    $status = $submission->effective_status;
    $map = [
        'approved'  => ['อนุมัติแล้ว', '#16a34a', '#dcfce7'],
        'pending'   => ['รออนุมัติ', '#d97706', '#fef3c7'],
        'submitted' => ['ยื่นแล้ว', '#2563eb', '#dbeafe'],
        'returned'  => ['ส่งกลับแก้ไข', '#d97706', '#fef3c7'],
        'rejected'  => ['ปฏิเสธ', '#dc2626', '#fee2e2'],
        'cancelled' => ['ยกเลิก', '#64748b', '#f1f5f9'],
        'draft'     => ['ฉบับร่าง', '#64748b', '#f1f5f9'],
    ];
    [$stLabel, $stColor, $stBg] = $map[$status] ?? [$status, '#64748b', '#f1f5f9'];
    $requester = trim(($submission->user->first_name ?? '').' '.($submission->user->last_name ?? ''));
    $appName = config('app.name', 'Data Flow');
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>ตรวจสอบเอกสาร — {{ $appName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family: -apple-system, "Segoe UI", "Noto Sans Thai", Tahoma, sans-serif;
               background:#f1f5f9; color:#0f172a; display:flex; min-height:100vh; align-items:center; justify-content:center; padding:20px; }
        .card { background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); max-width:440px; width:100%; overflow:hidden; }
        .head { background:#2563eb; color:#fff; padding:22px 24px; text-align:center; }
        .head .check { width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,.18); display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:8px; }
        .head h1 { margin:0; font-size:18px; font-weight:700; }
        .head p { margin:4px 0 0; font-size:13px; opacity:.9; }
        .body { padding:20px 24px; }
        .badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:13px; font-weight:600; color:{{ $stColor }}; background:{{ $stBg }}; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:11px 0; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .row:last-child { border-bottom:0; }
        .row .k { color:#64748b; }
        .row .v { font-weight:600; text-align:right; }
        .foot { padding:14px 24px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <div class="check">✓</div>
            <h1>เอกสารนี้อยู่ในระบบจริง</h1>
            <p>ตรวจสอบโดย {{ $appName }}</p>
        </div>
        <div class="body">
            <div class="row"><span class="k">สถานะ</span><span class="v"><span class="badge">{{ $stLabel }}</span></span></div>
            <div class="row"><span class="k">ประเภทเอกสาร</span><span class="v">{{ $submission->form->name ?? '-' }}</span></div>
            <div class="row"><span class="k">เลขที่เอกสาร</span><span class="v">{{ $submission->reference_no ?? ('#'.$submission->id) }}</span></div>
            <div class="row"><span class="k">วันที่ยื่น</span><span class="v">{{ optional($submission->created_at)->format('d/m/Y') ?? '-' }}</span></div>
            @if($requester !== '')
                <div class="row"><span class="k">ผู้ยื่น</span><span class="v">{{ $requester }}</span></div>
            @endif
        </div>
        <div class="foot">เอกสารอิเล็กทรอนิกส์ออกโดยระบบ {{ $appName }} · สแกนเพื่อยืนยันความถูกต้อง</div>
    </div>
</body>
</html>
