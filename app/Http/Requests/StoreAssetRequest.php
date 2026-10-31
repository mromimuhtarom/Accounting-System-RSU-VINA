<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Services\AssetExcelImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Asset::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'code', 'serial_number', 'brand', 'model', 'category', 'location', 'custodian', 'funding', 'capacity'] as $field) {
            $value = AssetExcelImport::text($this->input($field));
            $data[$field] = $value === '' ? null : $value;
        }
        foreach (['category', 'location'] as $field) {
            if ($data[$field]) {
                $data[$field] = mb_strtoupper($data[$field]);
            }
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $rules = [
            'entry_token' => 'required|uuid', 'name' => 'required|string|max:255',
            'inventory_type' => ['required', Rule::in(array_keys(Asset::TYPES))],
            'category' => 'required|string|max:255', 'location' => 'required_unless:inventory_type,FACILITY|nullable|string|max:255',
            'custodian' => 'required_unless:inventory_type,FACILITY|nullable|string|max:255',
            'quantity' => 'required|integer|min:1|max:1000000',
            'condition' => ['required', Rule::in(['GOOD', 'BROKEN', 'INACTIVE', 'UNKNOWN'])],
            'procurement_year' => 'nullable|integer|min:1970|max:'.now()->year,
            'source_price' => 'nullable|numeric|min:0|max:1000000000000|decimal:0,2',
            'capacity' => 'required_if:inventory_type,FACILITY|nullable|string|max:255',
            'notes' => 'required|string|min:5|max:5000',
            'calibration_required' => 'required|boolean', 'calibration_due_at' => 'required_if:calibration_required,1|nullable|date_format:Y-m-d',
            'allow_similar' => 'sometimes|accepted', 'duplicate_reason' => 'required_if:allow_similar,1|nullable|string|min:10|max:2000',
        ];
        foreach (['code', 'serial_number', 'brand', 'model', 'funding'] as $f) {
            $rules[$f] = 'nullable|string|max:255';
        }

        return $rules;
    }
}
