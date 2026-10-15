@extends('layouts.app')
@section('title', 'Archived Reports | M.A.P.S.')
@section('content')
<section class="rounded-2xl border bg-white p-6"><h1 class="text-2xl font-bold">Archived reports</h1>
<p class="mt-2 text-slate-600">Saved flood and fire report snapshots preserve the records and filters used when generated.</p>
<a href="{{ route('operational-records.index') }}" class="block mt-4 text-blue-700">Generate another report</a>
@forelse($archives as $archive)<div class="border-t py-4 mt-4"><a class="font-bold text-blue-700" href="{{ route('operational-records.archives.show', $archive) }}">#{{ $archive->id }} · {{ $archive->title }}</a>
<p>{{ $archive->created_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT · {{ $archive->creator?->name ?? 'Former user' }}</p></div>
@empty<p class="mt-5">No archived reports yet.</p>@endforelse
{{ $archives->links() }}</section>
@endsection
