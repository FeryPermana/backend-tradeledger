<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Currency;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountId = $this->route('account')->id ?? $this->route('account');

        return [
            'name' => ['required', 'string', 'max:255', 'unique:accounts,name,' . $accountId . ',id,user_id,' . $this->user()->id],
            'type' => ['required', 'in:scalping,intra_day,swing,investment,mix'],
            'currency' => ['required', Rule::in(Currency::all())],
            'initial_balance' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
