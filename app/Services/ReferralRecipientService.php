<?php

namespace App\Services;

use App\Models\ReferralRecipient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReferralRecipientService
{
    public function similar(string $name, ?int $ignore = null): Collection
    {
        $key = ReferralRecipient::normalizeName($name);
        if ($key === '') return collect();
        return ReferralRecipient::query()->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->get()
            ->filter(function ($r) use ($key) {
                similar_text($key, $r->normalized_name, $percent);
                return $percent >= 82 || str_contains($r->normalized_name, $key) || str_contains($key, $r->normalized_name);
            })->values();
    }

    public function save(array $data, ?ReferralRecipient $recipient = null): ReferralRecipient
    {
        $key = ReferralRecipient::normalizeName($data['name']);
        if ($key === '') throw ValidationException::withMessages(['name'=>'Nama harus mengandung huruf atau angka.']);
        if (ReferralRecipient::where('normalized_name', $key)->when($recipient, fn ($q) => $q->where('id', '!=', $recipient->id))->exists()) {
            throw ValidationException::withMessages(['name'=>'Nama penerima sudah ada. Gunakan penerima yang tersedia, atau aktifkan kembali jika nonaktif.']);
        }
        try {
            $recipient ??= new ReferralRecipient();
            $recipient->fill($data)->save();
            return $recipient;
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name'=>'Penerima sudah disimpan oleh pengguna lain. Pilih data existing.']);
        }
    }

    public function import(array $rows): array
    {
        $headers = array_shift($rows) ?? [];
        $columns = [];
        foreach ($headers as $i => $header) {
            $key = ReferralRecipient::normalizeName((string) $header);
            $field = match ($key) {
                'nama', 'name', 'namapenerima', 'namapenerimareferral' => 'name',
                'kontak', 'nomorkontak', 'phone' => 'phone',
                'keterangan', 'notes' => 'notes', default => null,
            };
            if ($field) $columns[$field] = $i;
        }
        if (!isset($columns['name'])) throw ValidationException::withMessages(['file'=>'Kolom Nama wajib tersedia. Gunakan template.']);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $columns) {
            $created = $reused = 0;
            foreach ($rows as $i => $row) {
                if (!array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
                $data = [];
                foreach ($columns as $field => $column) $data[$field] = trim((string) ($row[$column] ?? ''));
                \Illuminate\Support\Facades\Validator::make($data, ['name'=>'required|string|max:150', 'phone'=>'nullable|string|max:50', 'notes'=>'nullable|string|max:2000'])->validate();
                $key = ReferralRecipient::normalizeName($data['name']);
                if (ReferralRecipient::where('normalized_name', $key)->exists()) { $reused++; continue; }
                if ($this->similar($data['name'])->isNotEmpty()) throw ValidationException::withMessages(['file'=>'Baris '.($i+2).': nama mirip penerima existing. Samakan nama dengan data existing atau tambahkan melalui form untuk konfirmasi. Seluruh import dibatalkan.']);
                $this->save($data + ['is_active'=>true]);
                $created++;
            }
            return compact('created', 'reused');
        });
    }
}
