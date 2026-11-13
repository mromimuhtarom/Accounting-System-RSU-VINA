<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage};
use Illuminate\Validation\ValidationException;

class TransactionEvidence
{
    public function store(Request $request, bool $referral = false): ?string
    {
        if (!$request->hasFile('attachment')) return null;
        $disk = $referral ? 'local' : 'public';
        $prefix = $referral ? 'referral-evidence' : 'expenses';
        $path = $request->file('attachment')->store($prefix, $disk);
        if (!$path) throw ValidationException::withMessages(['attachment'=>'Bukti transaksi gagal disimpan.']);
        BatchTransactionService::trackFile($request, $disk, $path);
        return $path;
    }

    public function delete(?string $path): void
    {
        if (!$path) return;
        try {
            if (!str_contains($path, '..') && str_starts_with($path, 'referral-evidence/')) Storage::disk('local')->delete($path);
            elseif (!str_contains($path, '..') && str_starts_with($path, 'expenses/')) Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Transaction evidence cleanup needs retry', ['path'=>$path]);
        }
    }

    public function retire(?string $path): void
    {
        // Keep the old evidence until both data and audit changes have committed.
        if ($path) DB::afterCommit(fn () => $this->delete($path));
    }

    public function open(string $path, ?string $downloadName = null)
    {
        $disk = str_starts_with($path, 'referral-evidence/') ? 'local' : 'public';
        abort_unless(!str_contains($path, '..') && (str_starts_with($path, 'referral-evidence/') || str_starts_with($path, 'expenses/')) && Storage::disk($disk)->exists($path), 404);
        return Storage::disk($disk)->download($path, $downloadName ?? basename($path), ['X-Content-Type-Options'=>'nosniff']);
    }
}
