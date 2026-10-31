<?php

namespace App\Http\Requests;

use App\Models\OpeningBalance;
use App\Services\AccountingEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', OpeningBalance::class) ?? false;
    }

    public function rules(): array
    {
        return ['entry_token' => 'required|uuid', 'kind' => ['required', Rule::in(array_keys(AccountingEntry::TYPES))],
            'journal_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            'reference' => 'required|string|max:100', 'description' => 'required|string|min:10|max:400',
            'amount' => 'required|numeric|gt:0|max:1000000000000|decimal:0,2',
            'debit_account_id' => 'required|integer|exists:chart_accounts,id',
            'credit_account_id' => 'required|integer|exists:chart_accounts,id|different:debit_account_id',
            'verified' => 'required|accepted'];
    }
}
