<?php
namespace App\Http\Controllers;
use App\Models\FloodTrainingRecord;
use App\Models\FloodObservationChange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class FloodLifecycleController extends Controller {
    public function history(FloodTrainingRecord $floodTrainingRecord): JsonResponse {
        return response()->json(['changes' => FloodObservationChange::where('flood_training_record_id', $floodTrainingRecord->id)->orderByDesc('id')->get()]);
    }
    public function bulk(Request $request): JsonResponse {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['increase', 'decrease', 'subside'])],
            'change_observed_at' => ['required', 'date', 'before_or_equal:now'],
            'change_note' => ['required', 'string', 'max:2000'],
            'expected' => ['required', 'array'],
            'expected.*.code' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'expected.*.status' => ['required', Rule::in(['Active', 'Subsided'])],
        ]);
        $observed = Carbon::parse($data['change_observed_at'], 'Asia/Manila')->utc();
        $records = DB::transaction(function () use ($data, $observed) {
            $records = FloodTrainingRecord::whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($data['ids'])) throw ValidationException::withMessages(['ids' => 'A selected incident is unavailable. Refresh and select again.']);
            foreach ($records as $record) {
                $expected = $data['expected'][$record->id] ?? null;
                if (! $expected || $expected['code'] !== $record->flood_level_code || $expected['status'] !== $record->flood_status)
                    throw ValidationException::withMessages(['ids' => 'An incident changed after selection. Refresh before applying updates.']);
                if ($record->flood_status !== 'Active' || $observed->lt($record->observed_at))
                    throw ValidationException::withMessages(['ids' => 'Select active incidents and an observation time after their start.']);
                $last = FloodObservationChange::where('flood_training_record_id', $record->id)->latest('observed_at')->first();
                if ($last && $observed->lt($last->observed_at)) throw ValidationException::withMessages(['change_observed_at' => 'Observation time cannot precede the latest update.']);
                if (($data['action'] === 'increase' && $record->flood_level_code === 'D') || ($data['action'] === 'decrease' && $record->flood_level_code === 'A'))
                    throw ValidationException::withMessages(['ids' => 'A selected incident is already at the highest or lowest code. No changes were saved.']);
            }
            foreach ($records as $record) {
                $attributes = ['include_in_training' => false, 'review_status' => 'Pending', 'reviewed_by' => null, 'reviewed_at' => null, 'model_version' => null];
                if ($data['action'] === 'subside') {
                    $attributes += ['flood_status' => 'Subsided', 'subsided_at' => $observed, 'duration_hours' => round($record->observed_at->diffInSeconds($observed) / 3600, 4)];
                } else {
                    $code = chr(ord($record->flood_level_code) + ($data['action'] === 'increase' ? 1 : -1));
                    $attributes += ['flood_level_code' => $code, 'risk_level' => ['A'=>'Low','B'=>'Medium','C'=>'High','D'=>'High'][$code],
                        'flood_depth_mm' => ['A'=>152.4,'B'=>457.2,'C'=>914.4,'D'=>1219.2][$code]];
                }
                $record->update($attributes);
            }
            return $records;
        });
        return response()->json(['message' => $records->count().' incident(s) updated. Check history and SMS logs for notification outcomes.', 'records' => $records]);
    }
}
