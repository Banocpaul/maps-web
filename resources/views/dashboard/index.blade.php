@extends('layouts.app')

@section('title', $assignedRole->name . ' Dashboard | M.A.P.S.')
@section('page-title', $assignedRole->name . ' Dashboard')
@section('page-description', 'Role-focused information and actions for your assigned responsibilities')

@section('content')
    @include('dashboard.partials.heading')

    @includeIf('dashboard.roles.' . $roleSlug)
@endsection