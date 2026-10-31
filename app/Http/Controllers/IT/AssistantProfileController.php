<?php

namespace App\Http\Controllers\IT;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAssistantProfileRequest;
use App\Models\AssistantProfile;
use App\Services\AssistantProfileSettings;
use Illuminate\Support\Facades\Gate;

class AssistantProfileController extends Controller
{
    public function edit(AssistantProfileSettings $settings)
    {
        Gate::authorize('manage', AssistantProfile::class);
        $profile = $settings->current();

        return view('it.assistant-profile', ['profile' => $profile, 'photoUrl' => $settings->photoUrl($profile)]);
    }

    public function update(UpdateAssistantProfileRequest $request, AssistantProfileSettings $settings)
    {
        $settings->update($request->validated(), $request->file('photo'), $request->user()->id);

        return redirect()->route('it.assistant-profile.edit')->with('success', 'Profil asisten diperbarui. Nama dan foto digunakan saat pengguna memuat halaman berikutnya.');
    }
}
