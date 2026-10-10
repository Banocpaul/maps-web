@extends('layouts.app')
@section('title', 'System Analytics | Mandaluyong Flood & Fire')
@section('page-title', 'System Analytics')
@section('page-description', 'Account activity and system performance')
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
@section('content')
    @include('system-analytics.partials.overview')
@endsection
