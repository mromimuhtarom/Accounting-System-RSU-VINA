<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->is_active !== false;
    }

    public function rules(): array
    {
        return ['message' => 'nullable|string|max:400', 'mode' => ['required', Rule::in(['auto', 'guide', 'search', 'summary', 'analysis'])],
            'resource' => ['nullable', Rule::in(['receipts', 'expenses', 'assets', 'claims', 'accounts', 'balance', 'transactions'])],
            'term' => 'nullable|string|max:120', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'as_of' => 'nullable|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            'context' => 'nullable|string|max:120'];
    }
}
