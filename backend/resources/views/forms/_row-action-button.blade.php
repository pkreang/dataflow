@php
    $cssClass = $class ?? 'btn-secondary text-sm';
    $label = $action['label'];
    $target = $action['target'] ?? null;
    $method = $action['method'] ?? null;
@endphp

@if($method && $method !== 'GET')
    <form method="POST" action="{{ $action['action'] }}" class="inline">
        @csrf
        @method($method)
        @if(!empty($action['confirm']))
            <button type="button" class="{{ $cssClass }}"
                    @click="window.dispatchEvent(new CustomEvent('confirm-open', {detail:{message:'{{ addslashes($action['confirm']) }}', danger:true, form:$el.closest('form')}}))">{{ $label }}</button>
        @else
            <button type="submit" class="{{ $cssClass }}">{{ $label }}</button>
        @endif
    </form>
@else
    <a href="{{ $action['href'] }}" @if($target) target="{{ $target }}" @endif class="{{ $cssClass }}">
        {{ $label }}
    </a>
@endif
