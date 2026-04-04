<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'market' => ['required', 'string', 'max:50'],
            'category' => ['required', 'in:crypto,stock_idx,stock_us,commodity'],
            'is_watchlist' => ['nullable', 'boolean'],
            'tradingview_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
