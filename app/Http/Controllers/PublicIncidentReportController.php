<?php

namespace App\Http\Controllers;

use App\Models\PublicIncidentReport;
use App\Services\IncidentReportArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PublicIncidentReportController extends Controller
{
    public function create(IncidentReportArea $area): View
    {
        return view('public.report-incident', [
            'boundaries' => $area->boundaries(),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, IncidentReportArea $area): RedirectResponse
    {
        $validated = $request->validate([
            'incident_type' => ['required', Rule::in(['fire', 'flood'])],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'submission_token' => ['required', 'uuid'],
            'website' => ['nullable', 'string', 'max:0'],
            'photo' => ['bail', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:max_width=8000,max_height=8000'],
        ]);
        if (! $area->contains((float) $validated['latitude'], (float) $validated['longitude'])) {
            throw ValidationException::withMessages(['latitude' => 'Please place the pin inside Mandaluyong City.']);
        }

        $storedPhoto = null;
        $photoDisk = config('filesystems.incident_report_disk', 'local');
        try {
            $report = DB::transaction(function () use ($validated, $request, $photoDisk, &$storedPhoto): PublicIncidentReport {
                $report = PublicIncidentReport::firstOrCreate(
                    ['submission_token' => $validated['submission_token']],
                    [
                        'reference' => 'PR-'.Str::ulid(),
                        'incident_type' => $validated['incident_type'],
                        'latitude' => $validated['latitude'], 'longitude' => $validated['longitude'],
                        'status' => 'Pending',
                        'submitted_by' => $request->user()->id,
                        'reporter_barangay_id' => $request->user()->barangay_id,
                    ]
                );
                abort_unless((int) $report->submitted_by === $request->user()->id, 409,
                    'This submission token has already been used. Open a new report form.');
                if ($report->wasRecentlyCreated) {
                    if ($request->hasFile('photo')) {
                        $storedPhoto = $request->file('photo')->store('incident-report-photos', ['disk' => $photoDisk, 'visibility' => 'private']);
                        if (! is_string($storedPhoto) || $storedPhoto === '') {
                            throw ValidationException::withMessages([
                                'photo' => 'Your photo could not be saved. Please try again or submit without a photo.',
                            ]);
                        }
                        $report->forceFill(['photo_path' => $storedPhoto, 'photo_disk' => $photoDisk])->save();
                    }
                    $report->events()->create(['actor_id' => $request->user()->id,
                        'to_status' => 'Pending', 'notes' => 'Submitted by a public resident.']);
                }

                return $report;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPhoto) && $storedPhoto !== '') {
                Storage::disk($photoDisk)->delete($storedPhoto);
            }
            throw $exception;
        }

        return redirect()->route('public.incident-reports.create')
            ->with('report_reference', $report->reference);
    }
}
