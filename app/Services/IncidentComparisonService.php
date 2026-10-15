<?php
namespace App\Services;

use App\Models\{Barangay, FireIncident, FloodIncidentRecord, FloodTrainingRecord};
use Carbon\Carbon;
use Illuminate\Support\Str;

class IncidentComparisonService
{
    public static function comparison(int $current, int $previous): array {
        $delta = $current - $previous;
        $percent = $previous > 0 ? round($delta * 100 / $previous, 1) : ($current === 0 ? 0.0 : null);
        $label = $percent === null ? 'No baseline records'
            : ($delta === 0 ? 'No change' : number_format(abs($percent), 1).'% '.($delta > 0 ? 'increase' : 'decrease'));
        return compact('current', 'previous', 'delta', 'percent', 'label');
    }
    private function key(string $name): string {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
        $key = str_replace('hagdangbato', 'hagdanbato', $key);
        return $key === 'wackwackgreenhillseast' ? 'wackwackgreenhills' : $key;
    }
    public function build(int $year, array $types, ?int $barangayId = null, ?int $baselineYear = null): array {
        $now = now('Asia/Manila');
        $baselineYear ??= $year - 1;
        $incomplete = $year === $now->year;
        $baselineIncomplete = $baselineYear === $now->year;
        $end = $incomplete ? $now : Carbon::create($year, 12, 31, 23, 59, 59, 'Asia/Manila');
        $previousEnd = $baselineIncomplete ? $now->copy() : Carbon::create($baselineYear, 12, 31, 23, 59, 59, 'Asia/Manila');
        $start = $end->copy()->startOfYear(); $previousStart = $previousEnd->copy()->startOfYear();
        $names = Barangay::query()->pluck('name', 'id');
        $canonical = $names->mapWithKeys(fn ($name) => [$this->key($name) => $name])->all();
        $selectedName = $barangayId ? $names->get($barangayId) : null;
        $periods = ['current' => [$start, $end], 'previous' => [$previousStart, $previousEnd]];
        $data = [];
        foreach ($periods as $period => [$from, $through]) {
            $rows = collect();
            if (in_array('fire', $types, true)) {
                $fires = FireIncident::query()->with('barangay:id,name')
                    ->whereRaw('COALESCE(occurred_at, reported_at) >= ?', [$from->copy()->utc()->toDateTimeString()])
                    ->whereRaw('COALESCE(occurred_at, reported_at) <= ?', [$through->copy()->utc()->toDateTimeString()])
                    ->get(['id', 'barangay_id', 'source_barangay', 'occurred_at', 'reported_at', 'alarm_level']);
                foreach ($fires as $r) $rows->push(['type' => 'fire', 'source' => 'Official fire records',
                    'at' => ($r->occurred_at ?? $r->reported_at)->copy()->timezone('Asia/Manila'),
                    'barangay' => $r->barangay?->name ?? $r->source_barangay ?? 'Unspecified', 'category' => $r->alarm_level ?: 'Not recorded']);
            }
            if (in_array('flood', $types, true)) {
                $floods = FloodIncidentRecord::query()
                    ->whereRaw('COALESCE(flood_start_datetime, observation_datetime) >= ?', [$from->toDateTimeString()])
                    ->whereRaw('COALESCE(flood_start_datetime, observation_datetime) <= ?', [$through->toDateTimeString()])
                    ->get(['id', 'barangay', 'flood_start_datetime', 'observation_datetime', 'flood_code']);
                foreach ($floods as $r) $rows->push(['type' => 'flood', 'source' => 'Flood incident records',
                    'at' => Carbon::parse($r->flood_start_datetime ?: $r->observation_datetime, 'Asia/Manila'),
                    'barangay' => $r->barangay ?: 'Unspecified', 'category' => in_array($r->flood_code, ['A', 'B', 'C', 'D'], true) ? $r->flood_code : 'Unknown / other']);
                $observations = FloodTrainingRecord::query()->whereBetween('observed_at', [$from->copy()->utc(), $through->copy()->utc()])
                    ->get(['id', 'barangay', 'observed_at', 'flood_level_code']);
                foreach ($observations as $r) $rows->push(['type' => 'flood', 'source' => 'Flood field observations',
                    'at' => $r->observed_at->copy()->timezone('Asia/Manila'), 'barangay' => $r->barangay ?: 'Unspecified',
                    'category' => in_array($r->flood_level_code, ['A', 'B', 'C', 'D'], true) ? $r->flood_level_code : 'Unknown / other']);
            }
            $rows = $rows->map(function ($r) use ($canonical) {
                $r['barangay'] = $canonical[$this->key($r['barangay'])] ?? $r['barangay']; return $r;
            });
            if ($selectedName) $rows = $rows->filter(fn ($r) => $this->key($r['barangay']) === $this->key($selectedName));
            $data[$period] = $rows->values();
        }
        $cards = count($types) > 1 ? ['all' => self::comparison($data['current']->count(), $data['previous']->count())] : [];
        foreach ($types as $type) $cards[$type] = self::comparison($data['current']->where('type', $type)->count(), $data['previous']->where('type', $type)->count());
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $current = $data['current']->filter(fn ($r) => $r['at']->month === $month)->count();
            $previous = $data['previous']->filter(fn ($r) => $r['at']->month === $month)->count();
            $future = $incomplete && $month > $end->month;
            $baselineFuture = $baselineIncomplete && $month > $previousEnd->month;
            $months[] = ['month' => Carbon::create(2000, $month, 1)->format('M'), 'partial' => ($incomplete && $month === $end->month) || ($baselineIncomplete && $month === $previousEnd->month),
                ...(($future || $baselineFuture) ? ['current' => $future ? null : $current, 'previous' => $baselineFuture ? null : $previous, 'delta' => null, 'percent' => null, 'label' => 'Not yet elapsed'] : self::comparison($current, $previous))];
        }
        $barangays = $data['current']->pluck('barangay')->merge($data['previous']->pluck('barangay'))->unique()->map(fn ($name) => [
            'name' => $name, ...self::comparison($data['current']->where('barangay', $name)->count(), $data['previous']->where('barangay', $name)->count()),
        ])->sort(fn ($a, $b) => ($b['delta'] <=> $a['delta']) ?: ($b['current'] <=> $a['current']) ?: strcmp($a['name'], $b['name']))->values()->all();
        $categories = [];
        foreach ($types as $type) {
            $labels = $type === 'flood' ? ['A', 'B', 'C', 'D', 'Unknown / other']
                : $data['current']->where('type', $type)->pluck('category')->merge($data['previous']->where('type', $type)->pluck('category'))->unique()->sort()->values()->all();
            foreach ($labels as $label) {
                $current = $data['current']->where('type', $type)->where('category', $label)->count();
                $previous = $data['previous']->where('type', $type)->where('category', $label)->count();
                $categories[$type][] = ['name' => $label, ...self::comparison($current, $previous)];
            }
        }
        $sources = [];
        foreach (['Official fire records' => 'fire', 'Flood incident records' => 'flood', 'Flood field observations' => 'flood'] as $source => $type) {
            if (! in_array($type, $types, true)) continue;
            $row = ['name' => $source];
            foreach (['current', 'previous'] as $period) {
                $group = $data[$period]->where('source', $source);
                $row[$period] = $group->count();
                $latest = $group->sortByDesc(fn ($r) => $r['at']->timestamp)->first();
                $row[$period.'_latest'] = $latest ? $latest['at']->format('M d, Y') : 'No records in period';
            }
            $sources[] = $row;
        }
        return ['incomplete' => $incomplete || $baselineIncomplete, 'baseline_year' => $baselineYear, 'year' => $year, 'start' => $start, 'end' => $end, 'previous_start' => $previousStart, 'previous_end' => $previousEnd,
            'types' => $types, 'barangay' => $selectedName ?? 'All barangays', 'cards' => $cards,
            'months' => $months, 'barangays' => $barangays, 'categories' => $categories, 'sources' => $sources];
    }
}
