<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ProfilePhoto;
use App\Models\User;
use App\Services\ResidentSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(Request $request, ResidentSubscriptionService $subscriptions): RedirectResponse
    {
        $request->merge([
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
        ]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        DB::transaction(function () use ($request, $data, $subscriptions): void {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $user->update([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'name' => $data['first_name'].' '.$data['last_name'],
            ]);
            if ($request->hasFile('photo')) {
                ProfilePhoto::updateOrCreate(['user_id' => $user->id], [
                    'mime_type' => $request->file('photo')->getMimeType(),
                    'image_data' => base64_encode($request->file('photo')->get()),
                ]);
            }
            if ($user->isPublicResident()) {
                $subscriptions->sync($user);
            }
        });

        return back()->with('open_profile', $request->boolean('_profile_modal'))->with('success', 'Your profile has been updated.');
    }

    public function photo(Request $request): Response
    {
        $photo = ProfilePhoto::where('user_id', $request->user()->id)->firstOrFail();

        return response(base64_decode($photo->image_data), 200, [
            'Content-Type' => $photo->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
