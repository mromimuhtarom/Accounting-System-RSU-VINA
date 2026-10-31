<?php

namespace App\Services;

use App\Models\AssistantProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssistantProfileSettings
{
    public function current(): AssistantProfile
    {
        return (Schema::hasTable('assistant_profiles') ? AssistantProfile::find(1) : null) ?? new AssistantProfile(['id' => 1, 'name' => 'Sukoco', 'lock_version' => 0]);
    }

    public function photoUrl(AssistantProfile $profile): ?string
    {
        return $profile->photo_path ? route('assistant.avatar', ['v' => $profile->lock_version]) : null;
    }

    public function update(array $data, ?UploadedFile $photo, int $actor): AssistantProfile
    {
        if (! Schema::hasTable('assistant_profiles')) {
            throw ValidationException::withMessages(['profile' => 'Penyimpanan pengaturan belum tersedia. Hubungi administrator.']);
        }
        $newPath = null;
        if ($photo) {
            $extension = match ($photo->getMimeType()) {
                'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp',default => throw ValidationException::withMessages(['photo' => 'Format foto tidak didukung.'])
            };
            $newPath = $photo->storeAs('assistant-profiles', Str::uuid().'.'.$extension, 'local');
            if (! $newPath) {
                throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Coba kembali.']);
            }
        }
        try {
            return DB::transaction(function () use ($data, $photo, $newPath, $actor) {
                AssistantProfile::firstOrCreate(['id' => 1], ['name' => 'Sukoco', 'lock_version' => 0]);
                $profile = AssistantProfile::whereKey(1)->lockForUpdate()->firstOrFail();
                if ($profile->lock_version !== (int) $data['lock_version']) {
                    throw ValidationException::withMessages(['profile' => 'Profil sudah diubah dari halaman lain. Muat ulang sebelum menyimpan.']);
                }
                $oldPath = $profile->photo_path;
                $changes = ['name' => trim($data['name']), 'updated_by' => $actor, 'lock_version' => $profile->lock_version + 1];
                if ($newPath) {
                    $changes += ['photo_path' => $newPath, 'photo_mime' => $photo->getMimeType()];
                } elseif (! empty($data['remove_photo'])) {
                    $changes += ['photo_path' => null, 'photo_mime' => null];
                }
                $profile->update($changes);
                if ($oldPath && $oldPath !== $profile->photo_path && str_starts_with($oldPath, 'assistant-profiles/')) {
                    DB::afterCommit(fn () => rescue(fn () => Storage::disk('local')->delete($oldPath)));
                }

                return $profile;
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }throw $error;
        }
    }
}
