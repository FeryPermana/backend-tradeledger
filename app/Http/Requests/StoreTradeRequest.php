<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],

            'asset_id' => [
                'required',
                Rule::exists('assets', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],

            'strategy_id' => [
                'nullable',
                Rule::exists('strategies', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],

            'position_type' => [
                'required',
                'in:scalping,intra_day,swing,investment',
            ],

            'entry_price' => ['required', 'numeric'],
            'exit_price' => ['nullable', 'numeric'],
            'quantity' => ['required', 'numeric'],

            'stop_loss' => ['nullable', 'numeric'],
            'take_profit' => ['nullable', 'numeric'],
            'fees' => ['nullable', 'numeric'],

            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:entry_date'],

            'notes' => ['nullable', 'string', 'max:5000'],

            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],

            'closed_quantity' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['nullable', 'in:open,partial,closed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $positionType = (string) $this->input('position_type');
            $quantity = (float) ($this->input('quantity') ?? 0);
            $closedQuantity = (float) ($this->input('closed_quantity') ?? 0);

            if ($closedQuantity > $quantity) {
                $validator->errors()->add(
                    'closed_quantity',
                    'Closed quantity tidak boleh melebihi quantity.'
                );
            }

            if ($positionType === 'investment' && $closedQuantity > 0) {
                $validator->errors()->add(
                    'closed_quantity',
                    'Investment tidak boleh partial close dari trade form.'
                );
            }

            if (
                $positionType === 'investment' &&
                ($this->filled('exit_price') || $this->filled('exit_date'))
            ) {
                $validator->errors()->add(
                    'position_type',
                    'Investment close harus dilakukan dari portfolio.'
                );
            }

            if (! $this->filled('account_id') || ! $this->filled('entry_price') || ! $this->filled('quantity')) {
                return;
            }

            $account = Account::query()
                ->where('id', $this->account_id)
                ->where('user_id', $this->user()->id)
                ->first();

            if (! $account) {
                return;
            }

            $entryPrice = (float) $this->entry_price;
            $fees = (float) ($this->fees ?? 0);

            $positionValue = ($entryPrice * $quantity) + $fees;
            $availableEquity = (float) $account->initial_balance;

            if ($positionValue > $availableEquity) {
                $validator->errors()->add(
                    'quantity',
                    'Nilai posisi melebihi equity account.'
                );
            }
        });
    }
}
