<?php

namespace App\Http\Controllers\IT;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status'=>['nullable','in:online,offline'], 'q'=>['nullable','string','max:150']]);
        $onlineSession = fn ($query) => $query->whereNull('logged_out_at')
            ->where('expires_at','>',now('UTC'))->where('last_activity_at','>=',now('UTC')->subMinutes(5));
        $onlineUsers = User::where('is_active',true)->whereHas('authenticationSessions',$onlineSession);
        $total = User::count();
        $online = (clone $onlineUsers)->count();
        $offline = $total - $online;
        $users = User::with(['roles', 'latestAuthenticationSession', 'authenticationSessions'=>$onlineSession])
            ->withExists(['authenticationSessions as has_online_session'=>$onlineSession]);
        if ($request->status === 'online') $users->whereIn('id',(clone $onlineUsers)->select('users.id'));
        if ($request->status === 'offline') $users->whereNotIn('id',(clone $onlineUsers)->select('users.id'));
        if ($request->filled('q')) {
            $term = '%'.trim($request->q).'%';
            $users->where(fn ($query) => $query->where('name','like',$term)->orWhere('email','like',$term));
        }
        return view('it.dashboard', ['users'=>$users->orderBy('name')->paginate(20)->withQueryString(),
            'total'=>$total, 'online'=>$online, 'offline'=>$offline, 'checkedAt'=>now('Asia/Jakarta')]);
    }
}
