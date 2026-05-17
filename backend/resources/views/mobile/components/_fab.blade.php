{{--
    Global floating action button — appears on /m, /m/approvals, /m/forms
    (hidden on /m/me because profile page shouldn't suggest creating a repair).
    Links to the primary repair form ($primaryRepairForm). If no repair form
    is visible to the user, the FAB hides entirely.
--}}
@php
    $repairForm = $primaryRepairForm ?? null;
    $currentRoute = request()->route()?->getName() ?? '';
    $hideOn = ['mobile.me'];
@endphp
@if($repairForm && ! in_array($currentRoute, $hideOn, true))
    <a href="{{ route('forms.create', $repairForm->form_key) }}"
       class="fixed bottom-20 right-4 z-30 w-14 h-14 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg hover:shadow-xl active:scale-95 transition-all flex items-center justify-center"
       title="{{ __('common.create_repair_request') }}"
       aria-label="{{ __('common.create_repair_request') }}">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
    </a>
@endif
