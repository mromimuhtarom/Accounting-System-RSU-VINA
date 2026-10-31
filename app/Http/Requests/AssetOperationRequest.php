<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate', Asset::class) ?? false;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['TRANSFER', 'MAINTENANCE', 'CALIBRATION', 'CALIBRATION_SCHEDULE', 'STOCKTAKE', 'DISPOSAL_REQUEST'])],
            'event_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'notes' => 'required|string|max:5000',
            'location' => 'required_if:type,TRANSFER,STOCKTAKE|nullable|string|max:255',
            'custodian' => 'required_if:type,TRANSFER|nullable|string|max:255',
            'condition' => ['required_if:type,STOCKTAKE', 'nullable', Rule::in(['GOOD', 'BROKEN', 'INACTIVE', 'UNKNOWN'])],
            'calibration_required' => 'nullable|boolean', 'next_due_at' => 'required_if:type,CALIBRATION,CALIBRATION_SCHEDULE|nullable|date_format:Y-m-d|after:event_date',
            'provider' => 'required_if:type,MAINTENANCE,CALIBRATION|nullable|string|max:255', 'certificate' => 'required_if:type,CALIBRATION|nullable|string|max:255'];
    }
}
