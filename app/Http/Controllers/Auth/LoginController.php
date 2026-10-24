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
        if (!Auth::attempt(array_merge($credentials,['is_active'=>true]), $request->boolean('remember'))) {
            return back()->withErrors(['email'=>'Email atau password tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->user()->update(['last_login_at'=>now()]);
        return redirect()->intended(route('dashboard'));
    }
    public function destroy(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');
    }
}
