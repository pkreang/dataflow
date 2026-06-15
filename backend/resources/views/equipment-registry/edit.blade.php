@extends('layouts.app')

@section('title', __('common.edit_equipment'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'CMMS'],
        ['label' => __('common.equipment_registry_title'), 'url' => route('equipment-registry.index')],
        ['label' => __('common.edit_equipment')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.edit_equipment') }}</h2>
        <a href="{{ route('equipment-registry.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
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

    <form method="POST" action="{{ route('equipment-registry.update', $equipment) }}"
          x-data="{
              companyId: '{{ old('company_id', $equipment->company_id) }}',
              companies: {{ Js::from($companies) }},
              get branches() {
                  const co = this.companies.find(c => c.id == this.companyId);
                  return co ? co.branches : [];
              }
          }" novalidate>
        @csrf
        @method('PUT')
        <div class="card p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('common.equipment_registry') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">
                        {{ __('common.code') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="code" value="{{ old('code', $equipment->code) }}" required maxlength="100" class="form-input" />
                </div>

                <div>
                    <label class="form-label">
                        {{ __('common.name') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="name" value="{{ old('name', $equipment->name) }}" required maxlength="255" class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.serial_number') }}</label>
                    <input name="serial_number" value="{{ old('serial_number', $equipment->serial_number) }}" maxlength="255" class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.manufacturer') }}</label>
                    <input name="manufacturer" value="{{ old('manufacturer', $equipment->manufacturer) }}" maxlength="255" class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.model') }}</label>
                    <input name="model" value="{{ old('model', $equipment->model) }}" maxlength="255" class="form-input" />
                </div>

                <div>
                    <label class="form-label">
                        {{ __('common.category') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="equipment_category_id" required class="form-input">
                        <option value="">{{ __('common.please_select') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('equipment_category_id', $equipment->equipment_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">
                        {{ __('common.location') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="equipment_location_id" required class="form-input">
                        <option value="">{{ __('common.please_select') }}</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('equipment_location_id', $equipment->equipment_location_id) == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($branchesManagementEnabled ?? true)
                    <div>
                        <label class="form-label">{{ __('common.companies') }}</label>
                        <select name="company_id" x-model="companyId" class="form-input">
                            <option value="">{{ __('common.please_select') }}</option>
                            @foreach ($companies as $co)
                                <option value="{{ $co->id }}" {{ old('company_id', $equipment->company_id) == $co->id ? 'selected' : '' }}>{{ $co->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('common.branch') }}</label>
                        <select name="branch_id" class="form-input">
                            <option value="">{{ __('common.please_select') }}</option>
                            <template x-for="branch in branches" :key="branch.id">
                                <option :value="branch.id" x-text="branch.name" :selected="branch.id == '{{ old('branch_id', $equipment->branch_id) }}'"></option>
                            </template>
                        </select>
                    </div>
                @endif

                <div>
                    <label class="form-label">
                        {{ __('common.status') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="status" required class="form-input">
                        <option value="active" {{ old('status', $equipment->status) == 'active' ? 'selected' : '' }}>{{ __('common.status_active') }}</option>
                        <option value="inactive" {{ old('status', $equipment->status) == 'inactive' ? 'selected' : '' }}>{{ __('common.status_inactive') }}</option>
                        <option value="under_maintenance" {{ old('status', $equipment->status) == 'under_maintenance' ? 'selected' : '' }}>{{ __('common.status_under_maintenance') }}</option>
                        <option value="decommissioned" {{ old('status', $equipment->status) == 'decommissioned' ? 'selected' : '' }}>{{ __('common.status_decommissioned') }}</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('common.criticality') }}</label>
                    <select name="criticality" class="form-input">
                        <option value="">{{ __('common.please_select') }}</option>
                        <option value="A" {{ old('criticality', $equipment->criticality) == 'A' ? 'selected' : '' }}>{{ __('common.criticality_a') }}</option>
                        <option value="B" {{ old('criticality', $equipment->criticality) == 'B' ? 'selected' : '' }}>{{ __('common.criticality_b') }}</option>
                        <option value="C" {{ old('criticality', $equipment->criticality) == 'C' ? 'selected' : '' }}>{{ __('common.criticality_c') }}</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('common.purchase_date') }}</label>
                    <input type="date" name="purchase_date" value="{{ old('purchase_date', $equipment->purchase_date?->format('Y-m-d')) }}" class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.installed_date') }}</label>
                    <input type="date" name="installed_date" value="{{ old('installed_date', $equipment->installed_date?->format('Y-m-d')) }}" class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.warranty_expiry') }}</label>
                    <input type="date" name="warranty_expiry" value="{{ old('warranty_expiry', $equipment->warranty_expiry?->format('Y-m-d')) }}" class="form-input" />
                </div>

                <div>
                    <label class="form-label">{{ __('common.runtime_hours') }}</label>
                    <input type="number" step="0.01" min="0" name="runtime_hours" value="{{ old('runtime_hours', $equipment->runtime_hours) }}" class="form-input" />
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">{{ __('common.specifications') }}</label>
                    <textarea name="specifications" rows="3"
                              placeholder='{"power": "5kW", "voltage": "380V"}'
                              class="form-input resize-y font-mono">{{ old('specifications', $equipment->specifications ? json_encode($equipment->specifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">{{ __('common.notes') }}</label>
                    <textarea name="notes" rows="2" class="form-input resize-y">{{ old('notes', $equipment->notes) }}</textarea>
                </div>

                <div>
                    <x-form.active-toggle name="is_active" :checked="old('is_active', $equipment->is_active)" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end pt-2 pb-4">
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('equipment-registry.destroy', $equipment) }}"
                      onsubmit="return confirm('{{ __('common.are_you_sure') }}')" novalidate>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">
                        {{ __('common.delete') }}
                    </button>
                </form>
                <a href="{{ route('equipment-registry.index') }}" class="btn-secondary">
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
