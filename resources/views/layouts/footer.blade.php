<footer class="border-t border-slate-200 bg-white">
    <div class="flex min-h-16 flex-col gap-3 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">

        {{-- System identity --}}
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-700">
                © {{ now()->year }} M.A.P.S.
            </p>

            <p class="mt-1 text-xs text-slate-500">
                Mandaluyong Analytics Predictive System
            </p>
        </div>

        {{-- System information --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500">

            <span>
                Mandaluyong City Disaster Operations Portal
            </span>

            <span class="hidden h-4 w-px bg-slate-200 sm:block"></span>

            <span>
                Version 1.0
            </span>

        </div>

    </div>
</footer>