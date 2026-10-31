<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemAssistantRequest;
use App\Services\AssistantProfileSettings;
use App\Services\SystemAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemAssistantController extends Controller
{
    public function avatar(Request $request, AssistantProfileSettings $settings)
    {
        abort_if($request->user()->is_active === false, 403);
        $profile = $settings->current();
        abort_unless($profile->photo_path && preg_match('~^assistant-profiles/[0-9a-f-]{36}\.(jpg|png|webp)$~i', $profile->photo_path) && in_array($profile->photo_mime, ['image/jpeg', 'image/png', 'image/webp'], true) && Storage::disk('local')->exists($profile->photo_path), 404);

        return response()->file(Storage::disk('local')->path($profile->photo_path), ['Content-Type' => $profile->photo_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function ask(SystemAssistantRequest $request, SystemAssistant $assistant)
    {
        return response()->json($assistant->answer($request->user(), $request->validated()))->header('Cache-Control', 'no-store, private');
    }

    public function guide(Request $request)
    {
        abort_if($request->user()->is_active === false, 403);
        $path = base_path('docs/Panduan-Penggunaan-Sistem-RSU-Vina-Estetica-Lengkap-Neraca.pdf');
        abort_unless(is_file($path), 404, 'Panduan PDF belum tersedia.');

        return response()->file($path, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private']);
    }
}
