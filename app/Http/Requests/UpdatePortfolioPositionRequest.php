<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortfolioPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(
                    fn ($q) => $q->where('user_id', $this->user()->id)
                ),
            ],
            'quantity' => ['required', 'numeric'],
            'avg_price' => ['required', 'numeric'],
            'total_fees' => ['nullable', 'numeric'],
            'target_price' => ['nullable', 'numeric'],
            'horizon' => ['nullable', 'in:short_term,medium_term,long_term'],
            'conviction_level' => ['nullable', 'in:low,medium,high'],
            'thesis' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
