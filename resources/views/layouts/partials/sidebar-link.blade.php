@php
    $nested = $nested ?? false;
    $isActive = collect($item['active'])
        ->contains(fn ($pattern) => request()->routeIs($pattern));
@endphp

<a
    href="{{ route($item['route']) }}"
    @class([
        'group relative flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-sm transition',
        'ml-2 text-[13px]' => $nested,
        'bg-white/10 font-semibold text-white' => $isActive,
        'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! $isActive,
    ])
    @if ($isActive) aria-current="page" @endif
>
    @if ($isActive)
        <span class="absolute inset-y-2 left-0 w-0.5 rounded-r bg-sky-400" aria-hidden="true"></span>
    @endif
    <span @class([
        'flex h-5 w-5 flex-none items-center justify-center',
        'text-sky-300' => $isActive,
        'text-slate-400 group-hover:text-slate-200' => ! $isActive,
    ])>
        @include('layouts.partials.sidebar-icon', ['icon' => $item['icon']])
    </span>
    <span class="min-w-0 truncate">{{ $item['label'] }}</span>
</a>
