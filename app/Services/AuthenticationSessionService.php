<?php

namespace App\Services;

use App\Models\{ActivityLog, AuthenticationSession, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cookie, DB};
use Illuminate\Support\Str;

class AuthenticationSessionService
{
    public const PERSISTENT_COOKIE = 'rsuve_keep_signed_in';

    public function forgetPersistentCookie(): void
    {
        Cookie::queue(Cookie::forget(self::PERSISTENT_COOKIE, config('session.path', '/'), config('session.domain')));
    }

    public function restorePersistent(Request $request): bool
    {
        if (!$this->available()) return false;
        $token = $request->cookie(self::PERSISTENT_COOKIE);
        if (!is_string($token) || !preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) return false;
        $session = AuthenticationSession::where('persistent_token_hash', hash('sha256', $token))
            ->where('actor_id', $request->user()->id)->where('keep_signed_in', true)->whereNull('logged_out_at')->first();
        if (!$session) return false;
        $updated = AuthenticationSession::whereKey($session->id)->whereNull('logged_out_at')->update([
            'session_hash'=>hash('sha256', $request->session()->getId()),
            'last_activity_at'=>now('UTC'), 'expires_at'=>now('UTC')->addMinutes((int)config('session.lifetime',120)),
        ]);
        if (!$updated) return false;
        $request->session()->put('keep_signed_in', true);
        return true;
    }
    private function available(): bool
    {
        $lookup = app(RequestDatabaseLookup::class);
        return $lookup->hasTable('authentication_sessions') && $lookup->hasTable('activity_logs');
    }

    public function start(Request $request, User $actor, bool $newLogin = true): ?AuthenticationSession
    {
        if (!$this->available()) return null;
        return DB::transaction(function () use ($request, $actor, $newLogin) {
            $hash = hash('sha256', $request->session()->getId());
            $existing = AuthenticationSession::where('session_hash', $hash)->first();
            if ($existing) return $existing;
            $lastActivity = $newLogin ? now('UTC') : Carbon::createFromTimestamp((int)$request->session()->get('last_activity_at', now()->timestamp), 'UTC');
            $keep = (bool) $request->session()->get('keep_signed_in', false);
            $persistentToken = $newLogin && $keep ? Str::random(64) : null;
            $session = AuthenticationSession::firstOrCreate(['session_hash'=>$hash], [
                'actor_id'=>$actor->id, 'actor_name'=>$actor->name,
                'actor_email'=>$actor->email, 'actor_roles'=>$actor->loadMissing('roles')->roles->pluck('code')->implode(', '),
                'device_name'=>app(DeviceInfo::class)->name($request), 'user_agent'=>mb_substr($request->userAgent() ?? '', 0, 4096),
                'ip_address'=>$request->ip(), 'logged_in_at'=>$newLogin ? now('UTC') : null,
                'last_activity_at'=>$lastActivity, 'expires_at'=>$lastActivity->copy()->addMinutes((int)config('session.lifetime',120)),
                'keep_signed_in'=>$keep, 'persistent_token_hash'=>$persistentToken ? hash('sha256', $persistentToken) : null,
            ]);
            if ($persistentToken) Cookie::queue(Cookie::forever(self::PERSISTENT_COOKIE, $persistentToken,
                config('session.path', '/'), config('session.domain'),
                config('session.secure'), true, false, config('session.same_site', 'lax')));
            // Existing sessions are enrolled without inventing an earlier login timestamp.
            if ($newLogin && $session->wasRecentlyCreated) $this->writeLog($session, 'login', null, $this->values($session), now('UTC'), $request);
            return $session;
        });
    }

    public function touch(Request $request): bool
    {
        if (!$this->available()) return true;
        $hash = hash('sha256', $request->session()->getId());
        $session = AuthenticationSession::where('session_hash', $hash)->first();
        if (!$session) $session = $this->start($request, $request->user(), false);
        if ($session->logged_out_at) return false;
        // Conditional write prevents a concurrent logout from being resurrected,
        // without a separate transaction and SELECT FOR UPDATE on every page.
        $updated = AuthenticationSession::whereKey($session->id)->whereNull('logged_out_at')->update([
            'last_activity_at'=>now('UTC'), 'expires_at'=>now('UTC')->addMinutes((int)config('session.lifetime',120)),
        ]);
        return $updated > 0 || AuthenticationSession::whereKey($session->id)->whereNull('logged_out_at')->exists();
    }

    public function endCurrent(Request $request, string $reason): void
    {
        if (!$this->available() || !$request->user()) return;
        $session = $this->start($request, $request->user(), false);
        $this->end($session->id, $reason, $request);
    }

    private function end(int $id, string $reason, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($id, $reason, $request) {
            $session = AuthenticationSession::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($session->logged_out_at) return false;
            if ($reason === 'idle_timeout' && $session->keep_signed_in) return false;
            $before = $this->values($session);
            $at = $reason === 'idle_timeout' && $session->expires_at->lte(now('UTC')) ? $session->expires_at : now('UTC');
            $session->update(['logged_out_at'=>$at, 'logout_reason'=>$reason]);
            $this->writeLog($session, $reason === 'manual' ? 'logout' : 'logout_auto', $before, $this->values($session), $at, $request);
            return true;
        });
    }

    public function expireDue(): int
    {
        if (!$this->available()) return 0;
        $count = 0;
        AuthenticationSession::whereNull('logged_out_at')->where('keep_signed_in', false)->where('expires_at','<=',now('UTC'))->select('id')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                // Re-check under lock: activity may have extended the session since the query ran.
                DB::transaction(function () use ($row, &$count) {
                    $current = AuthenticationSession::whereKey($row->id)->lockForUpdate()->firstOrFail();
                    if (!$current->logged_out_at && !$current->keep_signed_in && $current->expires_at->lte(now('UTC'))) $count += (int)$this->end($current->id, 'idle_timeout');
                });
            }
        });
        return $count;
    }

    private function values(AuthenticationSession $session): array
    {
        $date = fn ($value) => $value?->copy()->timezone('Asia/Jakarta')->format('d-m-Y H:i:s').' WIB';
        return [
            'session_status'=>$session->logged_out_at ? 'Sudah logout' : 'Login aktif',
            'logged_in_at'=>$session->logged_in_at ? $date($session->logged_in_at) : 'Login sebelum pencatatan perangkat diaktifkan',
            'last_activity_at'=>$date($session->last_activity_at), 'expires_at'=>$session->keep_signed_in ? 'Tetap masuk sampai logout' : $date($session->expires_at),
            'logged_out_at'=>$session->logged_out_at ? $date($session->logged_out_at) : null,
            'logout_reason'=>match ($session->logout_reason) {
                'idle_timeout'=>'Otomatis: 2 jam tanpa aktivitas', 'manual'=>'Tombol logout',
                'remember_expired'=>'Otomatis: sesi tersimpan sudah tidak berlaku', 'account_disabled'=>'Akun dinonaktifkan', default=>null,
            },
            'device_name'=>$session->device_name, 'login_ip'=>$session->ip_address,
        ];
    }

    private function writeLog(AuthenticationSession $session, string $action, ?array $before, array $after, $at, ?Request $request): void
    {
        ActivityLog::create([
            'actor_id'=>$session->actor_id,'actor_name'=>$session->actor_name,'actor_email'=>$session->actor_email,'actor_roles'=>$session->actor_roles,
            'action'=>$action,'menu'=>'Akses Akun','form_name'=>$action === 'login' ? 'Login' : 'Logout',
            'route_name'=>$request?->route()?->getName() ?? 'system.session-expired',
            'subject_type'=>AuthenticationSession::class,'subject_id'=>(string)$session->id,'subject_label'=>'Sesi perangkat #'.$session->id,
            'before_values'=>$before,'after_values'=>$after,'changed_fields'=>$action === 'login' ? array_keys($after) : ['session_status','logged_out_at','logout_reason'],
            'ip_address'=>$request?->ip() ?? $session->ip_address,'occurred_at'=>$at,
            'authentication_session_id'=>$session->id,'device_name'=>$session->device_name,'user_agent'=>$session->user_agent,
        ]);
    }
}
