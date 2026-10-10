<header class="border-b border-slate-200 bg-slate-950 text-white shadow-lg">

        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">

            <a href="{{ route('public.portal') }}" class="flex items-center gap-3">

                <img src="{{ asset('images/cdrrmo-logo.jpg') }}" alt="Mandaluyong City CDRRMO logo" width="1080" height="1075" class="h-11 w-11 flex-none rounded-full bg-white object-contain" />

                <div>

                    <p class="text-xl font-black tracking-[0.16em]">

                        M.A.P.S

                    </p>

                    <p class="text-xs text-slate-400">

                        Flood Prediction & Fire Management System

                    </p>

                </div>

            </a>

            <nav class="flex flex-wrap items-center gap-3" aria-label="Public portal navigation">
            @if($reportPage ?? false)
                <a href="{{ route('public.portal') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">Back to Public Portal</a>
            @else
                <a href="{{ route('public.incident-reports.create') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">Report an Incident</a>
            @endif

            @if(auth()->user()?->isPublicResident())
                <a href="{{ route('public.reports') }}" class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold hover:bg-white/10">My Reports</a>
                <a href="{{ route('public.account') }}" class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold hover:bg-white/10">My Account & Alerts</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold hover:bg-white/10">Sign out</button></form>
            @elseif(auth()->check())
                <a href="{{ route('dashboard') }}" class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold hover:bg-white/10">Staff Dashboard</a>
            @else
                <a href="{{ route('public.login') }}" class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold hover:bg-white/10">Public Login</a>
                <a href="{{ route('public.register') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-900 hover:bg-blue-50">Create Account / Get Alerts</a>
                <a href="{{ route('login') }}" class="px-2 py-2 text-xs font-semibold text-slate-300 hover:text-white">Staff Login</a>
            @endif
            </nav>

        </div>

    </header>
