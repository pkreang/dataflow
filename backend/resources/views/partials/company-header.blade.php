{{--
    $company: App\Models\Company|null
    $branch: App\Models\Branch|null — must belong to $company; contact block uses branch address/phone when set, else company.
--}}
@php
    /** @var \App\Models\Company|null $company */
    /** @var \App\Models\Branch|null $branch */
    $company = $company ?? null;
    $branch = $branch ?? null;
    if ($branch && $company && (int) $branch->company_id !== (int) $company->id) {
        $branch = null;
    }
    $branchAddr = $branch ? trim($branch->formattedAddress()) : '';
    $companyAddr = $company ? trim($company->formattedAddress()) : '';
    $displayAddress = $branchAddr !== '' ? $branchAddr : ($companyAddr !== '' ? $companyAddr : null);
    $displayPhone = $branch?->phone ?: $company?->phone;
@endphp
@if ($company)
    <div class="mb-5 flex flex-col sm:flex-row gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40">
        @if ($company->logo)
            <div class="shrink-0">
                <img src="{{ asset('storage/' . $company->logo) }}" alt="" class="h-14 w-auto max-w-[120px] object-contain rounded-lg">
            </div>
        @endif
        <div class="min-w-0 flex-1 text-sm">
            <p class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $company->name }}</p>
            @if ($company->code)
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $company->code }}</p>
            @endif
            @if ($branch)
                <p class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                    {{ __('company.branch') }}: {{ $branch->name }}
                    @if ($branch->code)
                        <span class="text-slate-500 dark:text-slate-400 font-normal">({{ $branch->code }})</span>
                    @endif
                </p>
            @endif
            @if ($displayAddress)
                <p class="mt-2 text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $displayAddress }}</p>
            @endif
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                @if ($displayPhone)
                    <span>{{ __('company.phone') }}: {{ $displayPhone }}</span>
                @endif
                @if ($company->email)
                    <span>{{ __('company.email') }}: {{ $company->email }}</span>
                @endif
            </div>
        </div>
    </div>
@endif
