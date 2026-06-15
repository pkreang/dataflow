@extends('layouts.mobile')

@section('title', __('common.write_form'))

@section('content')
<section class="mb-4 mt-1 px-1">
    <h1 class="text-2xl font-bold leading-tight" style="color: var(--mob-navy)">{{ __('common.write_form') }}</h1>
    <p class="text-sm mt-1" style="color: var(--mob-muted)">{{ __('common.forms_index_desc') }}</p>
</section>

@if($forms->isEmpty())
    <div class="mob-glass py-10 flex flex-col items-center gap-2" style="color: var(--mob-muted)">
        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
        </svg>
        <p class="text-sm font-medium">{{ __('common.no_forms_available') }}</p>
    </div>
@else
    @php
        $tones = ['blue', 'teal', 'orange', 'green', 'purple'];
    @endphp
    <div class="space-y-2">
        @foreach($forms as $idx => $form)
            @php $tone = $tones[$idx % count($tones)]; @endphp
            <a href="{{ route('mobile.form.create', $form->form_key) }}" class="mob-list-card">
                <div class="mob-icon-square text-white" style="background: var(--mob-{{ $tone }})">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate" style="color: var(--mob-navy)">{{ $form->name }}</p>
                    @if($form->description)
                        <p class="text-xs mt-0.5 line-clamp-1" style="color: var(--mob-muted)">{{ $form->description }}</p>
                    @endif
                </div>
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--mob-muted)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endforeach
    </div>
@endif
@endsection
