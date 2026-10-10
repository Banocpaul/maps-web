@extends('layouts.app')

@section('title', $assignedRole->name . ' Dashboard | Mandaluyong Flood & Fire')
@section('page-title', $assignedRole->name . ' Dashboard')
@section('page-description', 'Pending work and next actions')

@section('content')
    @include('dashboard.partials.heading')

    @include('dashboard.partials.work-queue')
@endsection
