<?php

namespace App\Http\Requests;

class UpdateAssetRequest extends StoreAssetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('asset')) ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['entry_token']);
        $rules['edit_reason'] = 'required|string|min:5|max:2000';
        $rules['lock_version'] = 'required|integer|min:0';

        return $rules;
    }
}
