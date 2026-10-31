<?php

namespace App\Http\Requests;

use App\Models\AssistantProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssistantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', AssistantProfile::class) ?? false;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|min:2|max:60', 'lock_version' => 'required|integer|min:0', 'remove_photo' => 'nullable|boolean',
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=2048,max_height=2048', Rule::prohibitedIf($this->boolean('remove_photo'))]];
    }

    public function attributes(): array
    {
        return ['name' => 'nama asisten', 'photo' => 'foto asisten', 'lock_version' => 'versi pengaturan'];
    }

    public function messages(): array
    {
        return ['photo.prohibited' => 'Pilih unggah foto baru atau hapus foto lama, jangan keduanya.', 'photo.max' => 'Ukuran foto maksimal 2 MB.', 'photo.dimensions' => 'Dimensi foto harus antara 32 × 32 dan 2048 × 2048 piksel.', 'photo.image' => 'Unggah gambar JPG, PNG atau WebP yang valid.'];
    }
}
