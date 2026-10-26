<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->isMethodSafe() || $request->routeIs('session.activity', 'logout')) return $next($request);
        // Data changes and their audit trail commit or roll back together.
        DB::beginTransaction();
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400) DB::rollBack();
            else DB::commit();
            return $response;
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
