{{-- Shared navigation icon to keep menu and submenu styles consistent. --}}
<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('dashboard')
            <path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4Zm0 10h6v6h-6v-6ZM4 14h6v6H4v-6Z" />
            @break
        @case('analytics')
            <path d="M5 20V11m5 9V5m5 15v-7m4 7V8" />
            @break
        @case('records')
        @case('backup')
            <ellipse cx="12" cy="5" rx="7" ry="3" />
            <path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6" />
            @break
        @case('prediction')
            <path d="M4 18 9 13l3 3 8-9M15 7h5v5" />
            @break
        @case('flood')
            <path d="M12 3.5c-2.4 3.2-5.5 6.8-5.5 10A5.5 5.5 0 0 0 12 19a5.5 5.5 0 0 0 5.5-5.5c0-3.2-3.1-6.8-5.5-10Z" />
            <path d="M9.5 14.5c.5 1 1.3 1.5 2.5 1.5" />
            @break
        @case('fire')
            <path d="M12 3.5c1.5 3 4.5 4.7 4.5 8.5A4.5 4.5 0 0 1 12 16.5 4.5 4.5 0 0 1 7.5 12c0-2.4 1.2-4.2 2.7-5.8.2 1.8.9 3.2 1.8 4 .4-2.3.1-4.3 0-6.7Z" />
            <path d="M9.8 16c0 2.1 1.1 3.5 2.2 4.2 1.1-.7 2.2-2.1 2.2-4.2" />
            @break
        @case('hydrant')
            <path d="M8 10h8m-6-3h4m-7 6h10v7H7v-7Zm3-6V4h4v3M7 15H4v3h3m10-3h3v3h-3" />
            @break
        @case('map')
            <path d="m9 19-5-2V5l5 2m0 12 6-2m-6 2V7m6 10 5 2V7l-5-2m0 12V5m0 0L9 7" />
            @break
        @case('message')
            <path d="M7 8h10M7 12h7m7 0a9 9 0 1 1-3.3-7A9 9 0 0 1 21 12Z" />
            <path d="m5.5 18.5-1.5 2.5 4-.8" />
            @break
        @case('reports')
            <path d="M4 19V9m5 10V5m5 14v-8m5 8V7" />
            @break
        @case('advisory')
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
            @break
        @case('submissions')
            <path d="M8 7h9M8 11h9M8 15h5M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
            @break
        @case('users')
            <path d="M15.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM4.5 20a7.5 7.5 0 0 1 15 0" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" />
            <path d="M19 14.5 21 12l-2-2.5-2.5.5-1-2.5L13 6l-2.5 1.5-1 2.5L7 9.5 5 12l2 2.5 2.5-.5 1 2.5L13 18l2.5-1.5 1-2.5Z" />
            @break
        @case('activity')
            <path d="M9 5h6m-7 4h8m-8 4h5m-5 4h8M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
