@extends('layouts.app')
@section('title', 'System Analytics | M.A.P.S')
@section('page-title', 'System Analytics')
@section('page-description', 'Account activity and system performance')
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
@section('content')
<a href="{{ route('incident-comparison.index') }}" class="mb-4 inline-block rounded-lg border border-blue-200 bg-white px-4 py-2 font-semibold text-blue-700">Annual incident comparison report</a>
    @include('system-analytics.partials.overview')
@endsection
