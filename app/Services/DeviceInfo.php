<?php

namespace App\Services;

use Illuminate\Http\Request;

class DeviceInfo
{
    public function name(Request $request): string
    {
        $agent = $request->userAgent() ?? '';
        $os = match (true) {
            str_contains($agent, 'iPad') => 'iPad / iPadOS',
            str_contains($agent, 'iPhone') => 'iPhone / iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Komputer Windows',
            str_contains($agent, 'Macintosh') => 'Mac / macOS',
            str_contains($agent, 'CrOS') => 'Chromebook',
            str_contains($agent, 'Linux') => 'Komputer Linux',
            default => 'Perangkat tidak dikenali',
        };
        $browser = match (true) {
            preg_match('/Edg(?:e|A|iOS)?\//', $agent) === 1 => 'Microsoft Edge',
            str_contains($agent, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($agent, 'OPR/') => 'Opera',
            preg_match('/(?:Firefox|FxiOS)\//', $agent) === 1 => 'Firefox',
            preg_match('/(?:Chrome|CriOS)\//', $agent) === 1 => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser tidak dikenali',
        };
        // UA Client Hints may supply a phone model; this is reported by the browser, not a verified hostname.
        $model = trim((string) $request->header('Sec-CH-UA-Model'), '" ');
        if ($model === '' && preg_match('/Android[^;]*;\s*(?:[a-z]{2}(?:[-_][A-Z]{2})?;\s*)?([^;)]+?)\s+Build\//', $agent, $matches)) $model = trim($matches[1]);
        if ($model === 'K') $model = '';
        return mb_substr(($model !== '' ? $model.' / ' : '').$os.' · '.$browser, 0, 255);
    }
}
