@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.document_forms'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.document_forms'), 'url' => route('settings.document-forms.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
    @include('settings.document-forms._form', ['inlineToolbar' => true])
@endsection
