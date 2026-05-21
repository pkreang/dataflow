@extends('layouts.app')

@section('title', __('common.edit_equipment_location'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.equipment_locations'), 'url' => route('settings.equipment-locations.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
<div>
    <nav class="text-sm text-slate-500 dark:text-slate-400 mb-2">
        <span>{{ __('common.settings') }}</span>
        <span class="mx-1">/</span>
        <a href="{{ route('settings.equipment-locations.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.equipment_locations') }}</a>
    </nav>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.edit_equipment_location') }}</h2>
        <a href="{{ route('settings.equipment-locations.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.equipment-locations.update', $equipmentLocation) }}" novalidate>
        @csrf
        @method('PUT')
        <div class="card p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('common.equipment_locations') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">
                        {{ __('common.code') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="code" value="{{ old('code', $equipmentLocation->code) }}" required maxlength="50"
                           class="form-input @error('code') form-input-error @enderror" />
                    @error('code')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">
                        {{ __('common.name') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="name" value="{{ old('name', $equipmentLocation->name) }}" required maxlength="255"
                           class="form-input @error('name') form-input-error @enderror" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('common.building') }}</label>
                    <input name="building" value="{{ old('building', $equipmentLocation->building) }}" maxlength="255"
                           class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.floor') }}</label>
                    <input name="floor" value="{{ old('floor', $equipmentLocation->floor) }}" maxlength="100"
                           class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.zone') }}</label>
                    <input name="zone" value="{{ old('zone', $equipmentLocation->zone) }}" maxlength="100"
                           class="form-input" />
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">{{ __('common.remark') }}</label>
                    <textarea name="description" rows="2" maxlength="1000"
                              class="form-input resize-y">{{ old('description', $equipmentLocation->description) }}</textarea>
                </div>

                <div>
                    <x-form.active-toggle name="is_active" :checked="old('is_active', $equipmentLocation->is_active)" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end pt-2 pb-4">
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('settings.equipment-locations.destroy', $equipmentLocation) }}"
                      onsubmit="return confirm('{{ __('common.are_you_sure') }}')" novalidate>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">
                        {{ __('common.delete') }}
                    </button>
                </form>
                <a href="{{ route('settings.equipment-locations.index') }}" class="btn-secondary">
                    {{ __('common.cancel') }}
                </a>
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
