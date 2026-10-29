<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class BatchTransactionService
{
    public const MAX_ROWS = 20;

    public function store(Request $request, callable $validate, callable $store, string $returnRoute)
    {
        $request->validate(['rows'=>['required', 'array', 'min:1', 'max:'.self::MAX_ROWS], 'rows.*'=>['required', 'array'], 'rows.*.rows'=>['prohibited']]);
        $children = [];
        $errors = [];
        $files = new \ArrayObject();
        foreach ($request->input('rows') as $index => $row) {
            if (!ctype_digit((string) $index)) throw ValidationException::withMessages(['rows'=>'Nomor baris transaksi tidak valid.']);
            $child = Request::create($request->url(), 'POST', $row, [], $request->file('rows.'.$index, []));
            $child->setUserResolver(fn () => $request->user());
            $child->setRouteResolver(fn () => $request->route());
            $child->attributes->set('batch_files', $files);
            try {
                $validate($child);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $errors['rows.'.$index.'.'.$field] = array_map(fn ($m) => 'Transaksi '.((int) $index + 1).': '.$m, $messages);
                }
            }
            $children[$index] = $child;
        }
        if ($errors) throw ValidationException::withMessages($errors);

        try {
            DB::transaction(function () use ($children, $store) {
                foreach ($children as $index => $child) {
                    try { $store($child); }
                    catch (ValidationException $e) {
                        $errors = [];
                        foreach ($e->errors() as $field => $messages) $errors['rows.'.$index.'.'.$field] = array_map(fn ($m) => 'Transaksi '.((int) $index + 1).': '.$m, $messages);
                        throw ValidationException::withMessages($errors);
                    }
                }
            });
        } catch (\Throwable $e) {
            foreach ($files as $file) Storage::disk($file['disk'])->delete($file['path']);
            throw $e;
        }
        return redirect()->route($returnRoute)->with('success', count($children).' transaksi berhasil disimpan sekaligus.');
    }

    public static function trackFile(Request $request, string $disk, string $path): void
    {
        $files = $request->attributes->get('batch_files');
        if ($files instanceof \ArrayObject) $files[] = compact('disk', 'path');
    }
}
