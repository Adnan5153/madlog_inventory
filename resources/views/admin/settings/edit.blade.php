@extends('layouts.admin', ['title' => 'Settings'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Settings']]" />

    <x-admin.page-header
        title="System settings"
        subtitle="Runtime configuration values, persisted in the database. Changes take effect immediately." />

    @if(session('status'))
        <x-admin.alert variant="success">{{ session('status') }}</x-admin.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        @php
            $groupTitles = [
                'inventory'    => 'Inventory',
                'procurement'  => 'Procurement',
                'numbering'    => 'Numbering',
                'general'      => 'General',
                'notifications'=> 'Notifications',
            ];

            $typeInputs = [
                'bool'   => fn($name, $value) => view('admin.settings._input-bool', compact('name','value'))->render(),
                'int'    => fn($name, $value) => view('admin.settings._input-int',  compact('name','value'))->render(),
                'string' => fn($name, $value) => view('admin.settings._input-string', compact('name','value'))->render(),
            ];
        @endphp

        @if($groupGlobal->isNotEmpty())
            <h2 class="h5 mt-4 mb-3">
                <i class="bi bi-globe me-1"></i> Global defaults
                <small class="text-muted">Apply to every workshop unless overridden below.</small>
            </h2>

            @foreach($groupGlobal as $group => $rows)
                <div class="admin-card mb-3">
                    <h3 class="h6 text-uppercase text-muted mb-3">{{ $groupTitles[$group] ?? ucfirst($group) }}</h3>
                    @foreach($rows as $row)
                        <div class="row g-2 align-items-center mb-2">
                            <label class="col-md-5 col-form-label" for="global_{{ $row->key }}">
                                {{ ucwords(str_replace(['_', '.'], [' ', ' → '], $row->key)) }}
                                @if($row->description)
                                    <div class="text-muted small fw-normal">{{ $row->description }}</div>
                                @endif
                            </label>
                            <div class="col-md-7">
                                {!! $typeInputs[$row->type]("global[{$row->key}]", $row->value) !!}
                                @error("global.{$row->key}") <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <x-admin.alert variant="info">
                No default settings are seeded. Run <code>php artisan db:seed --class=SettingsSeeder</code> to install them.
            </x-admin.alert>
        @endif

        @if($workshopId && $groupWorkshop->isNotEmpty())
            <h2 class="h5 mt-4 mb-3">
                <i class="bi bi-building me-1"></i> Workshop overrides
                <small class="text-muted">Override the global default for this workshop only.</small>
            </h2>

            @foreach($groupWorkshop as $group => $rows)
                <div class="admin-card mb-3">
                    <h3 class="h6 text-uppercase text-muted mb-3">{{ $groupTitles[$group] ?? ucfirst($group) }}</h3>
                    @foreach($rows as $row)
                        <div class="row g-2 align-items-center mb-2">
                            <label class="col-md-5 col-form-label" for="workshop_{{ $row->key }}">
                                {{ ucwords(str_replace(['_', '.'], [' ', ' → '], $row->key)) }}
                            </label>
                            <div class="col-md-7">
                                {!! $typeInputs[$row->type]("workshop[{$row->key}]", $row->value) !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save settings
            </button>
        </div>
    </form>
@endsection