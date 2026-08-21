@extends('layouts.admin', ['title' => 'Edit tool category'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => 'Categories', 'url' => route('admin.tool-categories.index')],
        ['label' => $category->name, 'url' => route('admin.tool-categories.show', $category)],
        ['label' => 'Edit'],
    ]" />

    <x-admin.page-header title="Edit tool category" :subtitle="$category->name" />

    <form method="POST" action="{{ route('admin.tool-categories.update', $category) }}">
        @csrf
        @method('PUT')
        @include('admin.tool-categories._form', ['category' => $category, 'workshops' => $workshops])
    </form>
@endsection
