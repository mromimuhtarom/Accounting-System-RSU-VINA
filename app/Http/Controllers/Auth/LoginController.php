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
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required']]);
        if (!Auth::attempt(array_merge($credentials,['is_active'=>true]))) {
            return back()->withErrors(['email'=>'Email atau password tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->timestamp);
        $request->user()->update(['last_login_at'=>now()]);
        app(\App\Services\AuthenticationSessionService::class)->start($request, $request->user());
        return redirect()->intended(route('dashboard'));
    }
    public function destroy(Request $request)
    {
        app(\App\Services\AuthenticationSessionService::class)->endCurrent($request, $request->input('reason') === 'idle_timeout' ? 'idle_timeout' : 'manual');
        Auth::logoutCurrentDevice(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');
    }
}
