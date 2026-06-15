@extends('layouts.app')

@section('title', __('common.substitution_add'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.substitutions'), 'url' => route('settings.substitutions.index')],
        ['label' => __('common.add')],
    ]" />
@endsection

@section('content')
<div class="max-w-lg">
    <form method="POST" action="{{ route('settings.substitutions.store') }}" class="card p-6 space-y-5">
        @csrf

        <div>
            <label class="form-label">{{ __('common.substitution_from') }}</label>
            <select name="from_user_id" class="form-select @error('from_user_id') border-red-500 @enderror" required>
                <option value="">— {{ __('common.select') }} —</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('from_user_id') == $user->id)>
                        {{ trim($user->first_name . ' ' . $user->last_name) }} ({{ $user->email }})
                    </option>
                @endforeach
            </select>
            @error('from_user_id') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('common.substitution_to') }}</label>
            <select name="to_user_id" class="form-select @error('to_user_id') border-red-500 @enderror" required>
                <option value="">— {{ __('common.select') }} —</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('to_user_id') == $user->id)>
                        {{ trim($user->first_name . ' ' . $user->last_name) }} ({{ $user->email }})
                    </option>
                @endforeach
            </select>
            @error('to_user_id') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ __('common.starts_at') }}</label>
                <input type="date" name="starts_at" value="{{ old('starts_at') }}"
                       class="form-input @error('starts_at') border-red-500 @enderror" required />
                @error('starts_at') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('common.ends_at') }} <span class="text-slate-400 text-xs">({{ __('common.optional') }})</span></label>
                <input type="date" name="ends_at" value="{{ old('ends_at') }}"
                       class="form-input @error('ends_at') border-red-500 @enderror" />
                @error('ends_at') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="form-label">{{ __('common.reason') }} <span class="text-slate-400 text-xs">({{ __('common.optional') }})</span></label>
            <textarea name="reason" rows="2" class="form-input @error('reason') border-red-500 @enderror"
                      placeholder="{{ __('common.substitution_reason_placeholder') }}">{{ old('reason') }}</textarea>
            @error('reason') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('settings.substitutions.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
@endsection
