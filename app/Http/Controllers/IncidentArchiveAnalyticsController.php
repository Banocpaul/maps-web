<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class IncidentArchiveAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $now = now('Asia/Manila');
        $data = $request->validate([
            'year' => ['nullable','integer','min:2001','max:'.$now->year],
            'type' => ['nullable',Rule::in(['all','fire','flood'])],
            'scope' => ['nullable',Rule::in(['all','archived'])],
            'print' => ['nullable','boolean'],
        ]);
        $year = (int) ($data['year'] ?? $now->year - 1);
        $type = $data['type'] ?? 'all';
        $scope = $data['scope'] ?? 'all';
        $from = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Manila');
        $to = $from->copy()->addYear();
        $events = collect();
        if ($type !== 'flood') {
            $fire = DB::table('fire_incidents')
                ->leftJoin('barangays', 'barangays.id', '=', 'fire_incidents.barangay_id')
                ->whereRaw('COALESCE(fire_incidents.occurred_at, fire_incidents.reported_at) >= ?', [$from->copy()->utc()->toDateTimeString()])
                ->whereRaw('COALESCE(fire_incidents.occurred_at, fire_incidents.reported_at) < ?', [$to->copy()->utc()->toDateTimeString()]);
            if ($scope === 'archived') $fire->whereNotNull('fire_incidents.archived_at');
            foreach ($fire->select('fire_incidents.id','fire_incidents.incident_number','fire_incidents.occurred_at','fire_incidents.reported_at','fire_incidents.status','fire_incidents.location','fire_incidents.alarm_level','fire_incidents.individuals_affected','fire_incidents.houses_destroyed','fire_incidents.injured_count','fire_incidents.deceased_count','barangays.name as barangay_name','fire_incidents.source_barangay')->get() as $r) {
                $at = Carbon::parse($r->occurred_at ?? $r->reported_at, 'UTC')->timezone('Asia/Manila');
                $events->push(['type'=>'Fire','id'=>$r->incident_number ?: 'Fire #'.$r->id,'at'=>$at,
                    'barangay'=>$r->barangay_name ?: ($r->source_barangay ?: 'Unspecified'),'location'=>$r->location ?: 'Not recorded',
                    'status'=>$r->status,'severity'=>$r->alarm_level ?: 'Not recorded',
                    'people'=>$r->individuals_affected,'houses'=>$r->houses_destroyed,'injured'=>$r->injured_count,'deceased'=>$r->deceased_count]);
            }
        }
        if ($type !== 'fire') {
            $flood = DB::table('flood_incident_records')
                ->whereRaw('COALESCE(flood_start_datetime, observation_datetime) >= ?', [$from->toDateTimeString()])
                ->whereRaw('COALESCE(flood_start_datetime, observation_datetime) < ?', [$to->toDateTimeString()]);
            if ($scope === 'archived') $flood->whereNotNull('archived_at');
            foreach ($flood->select('id','event_id','barangay','flood_start_datetime','observation_datetime','status','flood_code','injured_count','deceased_count')->get() as $r) {
                $at = Carbon::parse($r->flood_start_datetime ?? $r->observation_datetime, 'Asia/Manila');
                $events->push(['type'=>'Flood','id'=>$r->event_id ?: 'Flood #'.$r->id,'at'=>$at,
                    'barangay'=>$r->barangay ?: 'Unspecified','location'=>$r->barangay ?: 'Not recorded',
                    'status'=>$r->status,'severity'=>$r->flood_code ?: 'Not recorded',
                    'people'=>null,'houses'=>null,'injured'=>$r->injured_count,'deceased'=>$r->deceased_count]);
            }
        }
        $events = $events->sortByDesc(fn($r) => $r['at']->timestamp)->values();
        $total = $events->count();
        $sums = [];
        foreach (['people','houses','injured','deceased'] as $field) {
            $known = $events->filter(fn($r) => $r[$field] !== null);
            $sums[$field] = ['total'=>$known->count() ? $known->sum($field) : null,'recorded'=>$known->count(),'missing'=>$total-$known->count()];
        }
        $barangays = $events->groupBy(fn($r)=>mb_strtolower(trim($r['barangay'])))
            ->map(fn($rows)=>['name'=>$rows->first()['barangay'],'count'=>$rows->count(),'fire'=>$rows->where('type','Fire')->count(),'flood'=>$rows->where('type','Flood')->count()])
            ->sortByDesc('count')->values();
        $months = collect(range(1,12))->map(function($m) use ($events) {
            $subset = $events->filter(fn($r) => $r['at']->month === $m);
            return ['name'=>Carbon::create(2000,$m,1)->format('M'),'count'=>$subset->count(),'fire'=>$subset->where('type','Fire')->count(),'flood'=>$subset->where('type','Flood')->count()];
        });
        $severities = $events->groupBy(fn($r)=>$r['type'].' — '.$r['severity'])->map->count()->sortDesc();
        return view($request->boolean('print') ? 'incident-archives.analytics-print' : 'incident-archives.analytics',
            compact('year','type','scope','total','sums','events','barangays','months','severities','now'));
    }
}
