<?php

namespace App\Http\Controllers;

use App\Models\PublicAdvisory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PublicAdvisoryController extends Controller
{
    public function index(): View
    {
        $advisories = PublicAdvisory::query()
            ->with([
                'user:id,role_id,name,first_name,last_name',
                'user.role:id,name,slug',
            ])
            ->newestFirst()
            ->paginate(15);

        return view('advisories.index', [
            'advisories' => $advisories,
        ]);
    }

    public function create(): View
    {
        return view('advisories.create', [
            'types' => $this->allowedTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAdvisory($request);

        try {
            $photoPath = null;

            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store(
                    'advisories',
                    $this->advisoryDisk()
                );
            }

            PublicAdvisory::query()->create([
                'user_id' => $request->user()->id,
                'advisory_date' => $validated['advisory_date'],
                'type' => $validated['type'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'photo_path' => $photoPath,
            ]);

            return redirect()
                ->route('advisories.index')
                ->with('success', 'Public advisory published successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'The public advisory could not be published.'
                );
        }
    }

    public function edit(PublicAdvisory $publicAdvisory): View
    {
        $this->ensureOwner($publicAdvisory);

        return view('advisories.edit', [
            'advisory' => $publicAdvisory,
            'types' => $this->allowedTypes(),
        ]);
    }

    public function update(
        Request $request,
        PublicAdvisory $publicAdvisory
    ): RedirectResponse {
        $this->ensureOwner($publicAdvisory);

        $validated = $this->validateAdvisory($request);

        try {
            $photoPath = $publicAdvisory->photo_path;

            if ($request->boolean('remove_photo') && $photoPath) {
                Storage::disk($this->advisoryDisk())->delete($photoPath);
                $photoPath = null;
            }

            if ($request->hasFile('photo')) {
                if ($photoPath) {
                    Storage::disk($this->advisoryDisk())
                        ->delete($photoPath);
                }

                $photoPath = $request->file('photo')->store(
                    'advisories',
                    $this->advisoryDisk()
                );
            }

            $publicAdvisory->update([
                'advisory_date' => $validated['advisory_date'],
                'type' => $validated['type'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'photo_path' => $photoPath,
            ]);

            return redirect()
                ->route('advisories.index')
                ->with('success', 'Public advisory updated successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'The public advisory could not be updated.'
                );
        }
    }

    public function destroy(
        PublicAdvisory $publicAdvisory
    ): RedirectResponse {
        $this->ensureOwner($publicAdvisory);

        try {
            if ($publicAdvisory->photo_path) {
                Storage::disk($this->advisoryDisk())
                    ->delete($publicAdvisory->photo_path);
            }

            $publicAdvisory->delete();

            return redirect()
                ->route('advisories.index')
                ->with('success', 'Public advisory deleted successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The public advisory could not be deleted.'
            );
        }
    }

    private function validateAdvisory(Request $request): array
    {
        return $request->validate([
            'advisory_date' => [
                'required',
                'date',
            ],
            'type' => [
                'required',
                'string',
                Rule::in($this->allowedTypes()),
            ],
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'message' => [
                'required',
                'string',
                'max:10000',
            ],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
            'remove_photo' => [
                'nullable',
                'boolean',
            ],
        ]);
    }

    private function allowedTypes(): array
    {
        return \App\Support\RoleAccess::advisoryTypes(auth()->user()->role()->value('slug'));
    }

    private function ensureOwner(PublicAdvisory $advisory): void
    {
        abort_unless(in_array($advisory->type, $this->allowedTypes(), true), 403);
        abort_unless(
            auth()->user()->hasAnyRole(['operations-manager', 'operations-officer'])
                || (int) $advisory->user_id === (int) auth()->id(),
            403,
            'You may only edit or delete advisories that you published.'
        );
    }

    private function advisoryDisk(): string
    {
        return (string) config(
            'filesystems.advisory_disk',
            'public'
        );
    }
}
