<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCapitalizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('account', Asset::class) ?? false;
    }

    public function rules(): array
    {
        $rules = ['cost' => 'required|numeric|min:0.01|max:1000000000000|decimal:0,2', 'acquired_at' => 'required|date_format:Y-m-d', 'depreciation_starts_at' => 'required|date_format:Y-m-d|after_or_equal:acquired_at', 'useful_life_months' => 'required|integer|min:1|max:1200', 'residual_value' => 'required|numeric|min:0|lte:cost|decimal:0,2', 'opening_accumulated' => 'required|numeric|min:0|lte:cost|decimal:0,2', 'opening_book_value' => 'required|numeric|min:0|lte:cost|decimal:0,2', 'evidence' => 'required|string|max:5000'];
        foreach (['asset_account_id' => 'ASSET', 'expense_account_id' => 'EXPENSE', 'accumulated_account_id' => 'ASSET', 'offset_account_id' => null] as $f => $type) {
            $exists = Rule::exists('chart_accounts', 'id')->where('is_active', true);
            if ($type) {
                $exists->where('type', $type);
            }
            $rules[$f] = ['required', 'integer', $exists];
        }

        return $rules;
    }
}
