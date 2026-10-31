<?php

namespace App\Http\Requests;

use App\Models\OpeningBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', OpeningBalance::class) ?? false;
    }

    public function rules(): array
    {
        return ['version' => 'required|integer|min:0', 'balance_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            'evidence' => 'required|string|min:10|max:10000', 'lines' => 'required|array|min:2|max:500',
            'lines.*.chart_account_id' => ['nullable', 'integer', Rule::exists('chart_accounts', 'id')],
            'lines.*.amount' => 'nullable|numeric|min:0|max:1000000000000|decimal:0,2',
            'lines.*.side' => ['required', Rule::in(['NORMAL', 'DEBIT', 'CREDIT'])]];
    }
}
