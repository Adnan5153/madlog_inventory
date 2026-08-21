@extends('layouts.admin', ['title' => 'New tool category'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => 'Categories', 'url' => route('admin.tool-categories.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New tool category" />

    <form method="POST" action="{{ route('admin.tool-categories.store') }}">
        @csrf
        @include('admin.tool-categories._form', ['category' => null, 'workshops' => $workshops])
    </form>
@endsection
