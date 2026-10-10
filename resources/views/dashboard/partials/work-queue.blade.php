<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Pending work">
    @foreach ($workflowCards as $card)
        <a href="{{ $card['url'] }}" data-task-card="{{ $card['key'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-600">
            <p class="text-sm font-medium text-slate-600">{{ $card['title'] }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($card['count']) }}</p>
            <p class="mt-3 text-sm font-semibold text-sky-700">{{ $card['action'] }} &rarr;</p>
        </a>
    @endforeach
</section>

<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold text-slate-950">Work Queue</h2><p class="mt-1 text-sm text-slate-500">Urgent incidents first, then outstanding reviews.</p></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th scope="col" class="px-5 py-3">Task</th><th scope="col" class="px-5 py-3">Barangay / Scope</th><th scope="col" class="px-5 py-3">Stage</th><th scope="col" class="px-5 py-3">Last Update</th><th scope="col" class="px-5 py-3">Next Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($workQueue as $task)
                    <tr data-work-task="{{ $task['key'] }}" class="hover:bg-slate-50">
                        <td class="px-5 py-4 font-semibold text-slate-900">{{ $task['title'] }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $task['barangay'] }}</td>
                        <td class="px-5 py-4"><span class="inline-block rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ $task['stage'] }}</span></td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ $task['updated_at']?->copy()->timezone('Asia/Manila')->format('M j, g:i A') ?? 'Unknown' }}</td>
                        <td class="px-5 py-4"><a href="{{ $task['url'] }}" class="inline-block whitespace-nowrap rounded-lg border border-sky-200 px-3 py-2 font-semibold text-sky-700 hover:bg-sky-50">{{ $task['action'] }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No pending tasks for your current role.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Up to 20 tasks shown. Open a task card to see its list. Times are in Asia/Manila.</p>
</section>

@if (count($quickLinks))
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-950">Quick Actions</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($quickLinks as $link)
                <a href="{{ $link['url'] }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </section>
@endif
