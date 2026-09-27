<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')
            ->with('status', 'profile-updated');
    }

    /**
     * Update the authenticated user's profile photo.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ], [
            'profile_photo.required' => 'Please select a profile photo.',
            'profile_photo.image' => 'The selected file must be a valid image.',
            'profile_photo.mimes' => 'The profile photo must be a JPG, JPEG, PNG, or WebP image.',
            'profile_photo.max' => 'The profile photo must not be larger than 2 MB.',
        ]);

        $user = $request->user();

        /*
         * Store the new image first.
         * Laravel generates a safe unique filename.
         */
        $newPhotoPath = $validated['profile_photo']->store(
            'profile-photos',
            'public'
        );

        $oldPhotoPath = $user->profile_photo_path;

        /*
         * Save the new path to the user before removing the old file.
         */
        $user->profile_photo_path = $newPhotoPath;
        $user->save();

        /*
         * Remove the previous profile photo after the new one
         * has successfully been stored and attached to the user.
         */
        if (
            $oldPhotoPath &&
            Storage::disk('public')->exists($oldPhotoPath)
        ) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return Redirect::route('profile.edit')
            ->with('status', 'profile-photo-updated');
    }

    /**
     * Remove the authenticated user's profile photo.
     */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (
            $user->profile_photo_path &&
            Storage::disk('public')->exists($user->profile_photo_path)
        ) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->profile_photo_path = null;
        $user->save();

        return Redirect::route('profile.edit')
            ->with('status', 'profile-photo-removed');
    }
}