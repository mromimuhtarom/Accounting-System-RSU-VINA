<?php

namespace App\Http\Controllers\IT;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $isSessionLog = $request->routeIs('it.session-logs.*');
        $actions = $isSessionLog ? ['login','logout','logout_auto'] : ['created','updated','deleted','restored'];
        $filters = $request->validate([
            'actor_id'=>['nullable','integer'], 'action'=>['nullable', \Illuminate\Validation\Rule::in($actions)],
            'menu'=>['nullable','string','max:100'], 'form_name'=>['nullable','string','max:100'],
            'date_from'=>['nullable','date_format:Y-m-d'], 'date_to'=>array_filter(['nullable','date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : null]),
            'q'=>['nullable','string','max:200'],
        ]);
        $base = ActivityLog::whereIn('action', $actions);
        $query = clone $base;
        foreach (['actor_id','action','menu','form_name'] as $key) {
            if ($request->filled($key)) $query->where($key, $filters[$key]);
        }
        if ($request->filled('date_from')) $query->where('occurred_at', '>=', CarbonImmutable::parse($filters['date_from'], 'Asia/Jakarta')->startOfDay()->utc());
        if ($request->filled('date_to')) $query->where('occurred_at', '<=', CarbonImmutable::parse($filters['date_to'], 'Asia/Jakarta')->endOfDay()->utc());
        if ($request->filled('q')) {
            $term = '%'.trim($filters['q']).'%';
            $query->where(fn ($q) => $q->where('actor_name','like',$term)->orWhere('actor_email','like',$term)
                ->orWhere('subject_label','like',$term)->orWhere('subject_id','like',$term)
                ->orWhere('device_name','like',$term)->orWhere('ip_address','like',$term));
        }
        return view('it.activity-logs.index', [
            'isSessionLog'=>$isSessionLog,
            'logRoutePrefix'=>$isSessionLog ? 'it.session-logs' : 'it.activity-logs',
            'logs'=>$query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'actors'=>(clone $base)->selectRaw('actor_id, MAX(actor_name) as actor_name')->whereNotNull('actor_id')->groupBy('actor_id')->orderBy('actor_name')->get(),
            'menus'=>(clone $base)->distinct()->orderBy('menu')->pluck('menu'),
            'forms'=>(clone $base)->distinct()->orderBy('form_name')->pluck('form_name'),
        ]);
    }

    public function show(ActivityLog $activityLog)
    {
        $isSessionLog = request()->routeIs('it.session-logs.*');
        abort_unless(in_array($activityLog->action, $isSessionLog ? ['login','logout','logout_auto'] : ['created','updated','deleted','restored'], true), 404);
        return view('it.activity-logs.show', ['log'=>$activityLog, 'logRoutePrefix'=>$isSessionLog ? 'it.session-logs' : 'it.activity-logs']);
    }
}
