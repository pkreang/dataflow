@extends('layouts.app')
@section('title', __('common.create_purchase_order'))
@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.purchasing')],
        ['label' => __('common.purchase_orders'), 'url' => route('purchase-orders.index')],
        ['label' => __('common.create_purchase_order')],
    ]" />
@endsection
@section('content')
    <div class="mb-6">
        <a href="{{ $prInstance ? route('purchase-requests.show', $prInstance) : route('purchase-orders.index') }}"
           class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ __('common.create_purchase_order') }}</h2>
        @if($prInstance)
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('common.pr_reference') }}:
                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $prInstance->reference_no ?? 'PR#'.$prInstance->id }}</span>
            </p>
        @endif
    </div>

    @if ($errors->has('workflow'))
        <div class="alert-error mb-4">
            {{ $errors->first('workflow') }}
        </div>
    @endif

    @if($prInstance)
        <div class="alert-info mb-4">
            {{ __('common.creating_po_from_pr') }}: <span class="font-semibold">{{ $prInstance->reference_no ?? 'PR#'.$prInstance->id }}</span>
        </div>
    @endif

    <div x-data="poForm({{ json_encode($prLineItems->map(fn($i) => ['item_name' => $i->item_name, 'qty' => $i->qty, 'unit' => $i->unit, 'unit_price' => $i->unit_price, 'total_price' => $i->total_price, 'notes' => $i->notes ?? ''])->values()) }})" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: Form fields --}}
        <div class="card p-5">
            @include('partials.company-header', ['company' => $company ?? null, 'branch' => $branch ?? null])
            <form id="po-form" method="POST" action="{{ route('purchase-orders.store') }}" class="space-y-3" novalidate>
                @csrf
                @if($form)
                    <input type="hidden" name="form_key" value="{{ $form->form_key }}">
                @endif
                @if($prInstance)
                    <input type="hidden" name="purchase_request_id" value="{{ $prInstance->id }}">
                @endif
                @if($form)
                    @foreach($form->fields as $field)
                        @php
                            $name  = "form_payload[{$field->field_key}]";
                            $value = old("form_payload.{$field->field_key}",
                                $prInstance?->payload[$field->field_key] ?? null);
                        @endphp
                        <div>
                            <label class="form-label">
                                {{ $field->label }}
                                @if($field->is_required) <span class="text-red-500">*</span> @endif
                            </label>
                            @include('components.dynamic-field', ['field' => $field, 'name' => $name, 'value' => $value])
                        </div>
                    @endforeach
                @endif
                <input type="hidden" name="amount" :value="totalAmount">
            </form>
        </div>

        {{-- Right: Line items --}}
        <div class="card p-5">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.line_items') }}</h4>
            <template x-for="(item, index) in items" :key="index">
                <div class="mb-3 p-3 bg-white dark:bg-slate-900/30 rounded-lg border border-slate-200 dark:border-slate-700 space-y-2">
                    <div>
                        <label class="text-xs text-slate-500">{{ __('common.item_name') }}</label>
                        <input :name="'items['+index+'][item_name]'" x-model="item.item_name" required
                               class="form-input w-full mt-0.5 text-sm">
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-slate-500">{{ __('common.qty') }}</label>
                            <input :name="'items['+index+'][qty]'" x-model="item.qty" type="number" min="0.01" step="0.01"
                                   @input="updateTotal(item)" required
                                   class="form-input w-full mt-0.5 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">{{ __('common.unit_label') }}</label>
                            <input :name="'items['+index+'][unit]'" x-model="item.unit" required
                                   class="form-input w-full mt-0.5 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">{{ __('common.unit_price') }}</label>
                            <input :name="'items['+index+'][unit_price]'" x-model="item.unit_price" type="number" min="0" step="0.01"
                                   @input="updateTotal(item)" required
                                   class="form-input w-full mt-0.5 text-sm">
                        </div>
                    </div>
                    <input type="hidden" :name="'items['+index+'][total_price]'" :value="item.total_price">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">
                            {{ __('common.total_price') }}:
                            <span x-text="Number(item.total_price).toLocaleString('th-TH', {minimumFractionDigits:2})"
                                  class="font-medium text-slate-800 dark:text-slate-200"></span>
                        </span>
                        <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                class="text-xs text-red-500 hover:text-red-700">{{ __('common.remove') }}</button>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">{{ __('common.notes') }}</label>
                        <input :name="'items['+index+'][notes]'" x-model="item.notes"
                               class="form-input w-full mt-0.5 text-sm">
                    </div>
                </div>
            </template>
            <button type="button" @click="addItem"
                    class="w-full py-2 text-sm text-blue-600 dark:text-blue-400 border border-dashed border-blue-400 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20">
                + {{ __('common.add_line_item') }}
            </button>
            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-600 flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('common.total_price') }}</span>
                <span x-text="Number(totalAmount).toLocaleString('th-TH', {minimumFractionDigits:2})"
                      class="text-lg font-bold text-blue-600 dark:text-blue-400"></span>
            </div>
            <div class="mt-4">
                <button type="submit" form="po-form" class="btn-primary w-full py-2.5">
                    {{ __('common.submit') }}
                </button>
            </div>
        </div>
    </div>

    <script>
    function poForm(prefill) {
        return {
            items: prefill && prefill.length ? prefill : [{ item_name: '', qty: 1, unit: '', unit_price: 0, total_price: 0, notes: '' }],
            get totalAmount() {
                return this.items.reduce((s, i) => s + (parseFloat(i.total_price) || 0), 0);
            },
            addItem() {
                this.items.push({ item_name: '', qty: 1, unit: '', unit_price: 0, total_price: 0, notes: '' });
            },
            removeItem(i) {
                if (this.items.length > 1) this.items.splice(i, 1);
            },
            updateTotal(item) {
                item.total_price = ((parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0)).toFixed(2);
            },
        };
    }
    </script>
@endsection
