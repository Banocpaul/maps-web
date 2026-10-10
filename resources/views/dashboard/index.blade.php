@extends('layouts.app')

@section('title', $assignedRole->name . ' Dashboard | M.A.P.S')
@section('page-title', $assignedRole->name . ' Dashboard')
@section('page-description', 'Pending work and next actions')

@section('content')
    @include('dashboard.partials.heading')

    @include('dashboard.partials.work-queue')
@endsection
