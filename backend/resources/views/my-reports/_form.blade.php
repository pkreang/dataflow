{{-- Wrapper around the shared dashboard builder partial.
     Hides admin-only visibility/permission picker so user dashboards are always
     scoped to the owner (controller forces visibility=permission + owner-only token). --}}
@php
    $hideVisibilityPicker = true;
@endphp
@include('settings.dashboards._form')
