<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function create(){ return view('auth.login'); }
    public function store(Request $request)
    {
        $data=$request->validate(['email'=>['required','email'],'password'=>['required'], 'remember'=>['sometimes','boolean']]);
        $remember = $request->boolean('remember');
        if (!Auth::attempt(['email'=>$data['email'], 'password'=>$data['password'], 'is_active'=>true], $remember)) {
            return back()->withErrors(['email'=>'Email atau password tidak sesuai.'])->onlyInput('email', 'remember');
        }
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->timestamp);
        $request->session()->put('keep_signed_in', $remember);
        if (!$remember) app(\App\Services\AuthenticationSessionService::class)->forgetPersistentCookie();
        $request->user()->update(['last_login_at'=>now()]);
        app(\App\Services\AuthenticationSessionService::class)->start($request, $request->user());
        return redirect()->intended(route('dashboard'));
    }
    public function destroy(Request $request)
    {
        if ($request->input('reason') === 'idle_timeout' && $request->session()->get('keep_signed_in', false)) return response()->noContent();
        app(\App\Services\AuthenticationSessionService::class)->endCurrent($request, $request->input('reason') === 'idle_timeout' ? 'idle_timeout' : 'manual');
        app(\App\Services\AuthenticationSessionService::class)->forgetPersistentCookie();
        Auth::logoutCurrentDevice(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');
    }
}
