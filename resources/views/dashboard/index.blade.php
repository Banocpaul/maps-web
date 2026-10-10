@extends('layouts.app')

@section('title', $assignedRole->name . ' Dashboard | Mandaluyong Flood & Fire')
@section('page-title', $assignedRole->name . ' Dashboard')
@section('page-description', 'Role-focused information and actions for your assigned responsibilities')

@section('content')
    @include('dashboard.partials.heading')

    @includeIf('dashboard.roles.' . $roleSlug)
@endsection

@if (in_array($roleSlug, [
    'administrator',
    'fire-responder',
    'flood-analyst',
], true))
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
@endif
