@extends('layouts.app')

@section('title', __('common.org_unit_import_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings'), 'url' => route('settings.org-units.index')],
        ['label' => __('common.org_chart'), 'url' => route('settings.org-units.index')],
        ['label' => __('common.import')],
    ]" />
@endsection

@section('content')
<div>
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-1">{{ __('common.org_unit_import_title') }}</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ __('common.org_unit_import_subtitle') }}</p>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="alert-warning mb-4">
            <p class="text-sm font-medium mb-2">{{ __('common.error') }}</p>
            <ul class="text-sm space-y-1 list-disc list-inside">
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-6">
            <form method="POST" action="{{ route('settings.org-units.import.store') }}" enctype="multipart/form-data" class="space-y-5" novalidate>
                @csrf
                <div>
                    <label class="form-label">{{ __('common.import_data') }}</label>
                    <input type="file" name="file" accept=".csv,.txt" required
                           class="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 dark:file:bg-blue-900/30 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50" />
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">CSV / TXT, max 2MB — UTF-8</p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('settings.org-units.import.template') }}" class="btn-secondary">
                        {{ __('common.download_template') }}
                    </a>
                    <a href="{{ route('settings.org-units.index') }}" class="btn-secondary">
                        {{ __('common.cancel') }}
                    </a>
                    <button type="submit" class="btn-primary">
                        {{ __('common.import') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="card p-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-3">Columns</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="table-header py-2 pr-4 text-left">Column</th>
                            <th class="table-header py-2 text-left">Required</th>
                            <th class="table-header py-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-700 dark:text-slate-300">
                        <tr>
                            <td class="py-1.5 pr-4 font-mono">name</td>
                            <td>*</td>
                            <td class="text-slate-400">ชื่อหน่วยงาน (unique key)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 pr-4 font-mono">type</td>
                            <td>*</td>
                            <td class="text-slate-400">company / division / department / section / team</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 pr-4 font-mono">parent_name</td>
                            <td></td>
                            <td class="text-slate-400">ชื่อ parent ต้องอยู่ก่อน child ในไฟล์</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 pr-4 font-mono">head_email</td>
                            <td></td>
                            <td class="text-slate-400">email ผู้รับผิดชอบ (ต้องมี account)</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 pr-4 font-mono">sort_order</td>
                            <td></td>
                            <td class="text-slate-400">ลำดับแสดงผล (ตัวเลข)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
                ถ้า name ซ้ำ — อัปเดต type/parent/head/sort_order; ถ้าไม่มี — สร้างใหม่
            </p>
        </div>
    </div>
</div>
@endsection
