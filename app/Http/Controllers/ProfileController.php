<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdateAttorneyProfileRequest;
use App\Models\Maintenance\Branch;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAttorney()) {
            $user->load(['signatory', 'notary']);

            return Inertia::render('Profile/Edit', [
                'mustVerifyEmail' => $user instanceof MustVerifyEmail,
                'status' => session('status'),
                'isAttorney' => true,
                'signatory' => $user->signatory,
                'notary' => $user->notary,
            ]);
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'isAttorney' => false,
            'branchOptions' => Branch::activeOptions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->user()->isAttorney()) {
            return $this->updateAttorneyProfile($request);
        }

        $profileRequest = ProfileUpdateRequest::createFrom($request);
        $profileRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

        $request->user()->fill($profileRequest->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    private function updateAttorneyProfile(Request $request): RedirectResponse
    {
        $formRequest = UpdateAttorneyProfileRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

        $validated = $formRequest->validated();
        $user = $request->user();
        $user->load(['signatory', 'notary']);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $signatory = $user->signatory;
        $notary = $user->notary;

        if ($signatory === null || $notary === null) {
            abort(500, 'Attorney account is missing signatory or notary records.');
        }

        $signatoryData = [
            'name' => $validated['name'],
            'position' => $validated['signatory_position'],
            'tin' => $validated['signatory_tin'],
        ];

        if ($request->hasFile('signatory_signature')) {
            if ($signatory->signature_path) {
                Storage::disk('public')->delete($signatory->signature_path);
            }

            $signatoryData['signature_path'] = $request->file('signatory_signature')->store('signatures', 'public');
        }

        $signatory->update($signatoryData);

        $notaryData = [
            'name' => $validated['name'],
            'commission_number' => $validated['notary_commission_number'],
            'tin' => $validated['notary_tin'],
        ];

        if ($request->hasFile('notary_signature')) {
            if ($notary->signature_path) {
                Storage::disk('public')->delete($notary->signature_path);
            }

            $notaryData['signature_path'] = $request->file('notary_signature')->store('notary-seals', 'public');
        }

        $notary->update($notaryData);

        return Redirect::route('profile.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
