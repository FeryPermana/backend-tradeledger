<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Trade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTradeRequest extends FormRequest
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

            'entry_price' => ['required', 'numeric', 'gt:0'],
            'exit_price' => ['nullable', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],

            'stop_loss' => ['nullable', 'numeric'],
            'take_profit' => ['nullable', 'numeric'],
            'fees' => ['nullable', 'numeric', 'gte:0'],

            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:entry_date'],

            'notes' => ['nullable', 'string', 'max:5000'],

            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],

            /*
            |--------------------------------------------------------------------------
            | UPDATE TRADE
            |--------------------------------------------------------------------------
            | closed_quantity from FE = additional quantity to close now,
            | not the final total closed quantity.
            */
            'closed_quantity' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['nullable', 'in:open,partial,closed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Trade|null $trade */
            $trade = $this->route('trade');

            if (! $trade) {
                return;
            }

            $positionType = (string) $this->input('position_type');
            $payloadQuantity = (float) ($this->input('quantity') ?? 0);
            $incrementClose = (float) ($this->input('closed_quantity') ?? 0);

            $tradeQuantity = (float) $trade->quantity;
            $tradeClosedQuantity = (float) ($trade->closed_quantity ?? 0);
            $remainingQuantity = max(0, $tradeQuantity - $tradeClosedQuantity);

            /*
            |--------------------------------------------------------------------------
            | BASIC GUARD
            |--------------------------------------------------------------------------
            */
            if ($payloadQuantity <= 0) {
                $validator->errors()->add(
                    'quantity',
                    'Quantity must be greater than 0.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CLOSED TRADE GUARD
            |--------------------------------------------------------------------------
            | Closed trades cannot change quantity or be closed again.
            |--------------------------------------------------------------------------
            */
            if ($trade->status === 'closed') {
                if ((float) $payloadQuantity !== (float) $tradeQuantity) {
                    $validator->errors()->add(
                        'quantity',
                        'Closed trades cannot change quantity.'
                    );
                }

                if ($incrementClose > 0) {
                    $validator->errors()->add(
                        'closed_quantity',
                        'Closed trades cannot be closed again.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | INVESTMENT GUARD
            |--------------------------------------------------------------------------
            */
            if ($positionType === 'investment' && $incrementClose > 0) {
                $validator->errors()->add(
                    'closed_quantity',
                    'Investment positions cannot be partially closed from the trade form.'
                );
            }

            if (
                $positionType === 'investment' &&
                ($this->filled('exit_price') || $this->filled('exit_date'))
            ) {
                $validator->errors()->add(
                    'position_type',
                    'Investment positions must be closed from the portfolio page.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PARTIAL / CLOSE GUARD
            |--------------------------------------------------------------------------
            | incrementClose = quantity to close now
            |--------------------------------------------------------------------------
            */
            if ($incrementClose > 0) {
                if ($remainingQuantity <= 0) {
                    $validator->errors()->add(
                        'closed_quantity',
                        'There is no remaining quantity to close.'
                    );
                }

                if ($incrementClose > $remainingQuantity) {
                    $validator->errors()->add(
                        'closed_quantity',
                        'Closed quantity cannot be greater than the remaining quantity.'
                    );
                }

                if (! $this->filled('exit_price')) {
                    $validator->errors()->add(
                        'exit_price',
                        'Exit price is required when closing a position.'
                    );
                }

                if (! $this->filled('exit_date')) {
                    $validator->errors()->add(
                        'exit_date',
                        'Exit date is required when closing a position.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | ACCOUNT CHECK
            |--------------------------------------------------------------------------
            | Final balance validation stays in controller via AccountBalanceService.
            |--------------------------------------------------------------------------
            */
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

            if ($incrementClose <= 0 && empty($this->input('exit_date'))) {
                $entryPrice = (float) $this->input('entry_price');
                $fees = (float) ($this->input('fees') ?? 0);
                $positionValue = ($entryPrice * $payloadQuantity) + $fees;
                $availableEquity = (float) $account->initial_balance;

                if ($positionValue > $availableEquity) {
                    $validator->errors()->add(
                        'quantity',
                        'Position value exceeds account equity.'
                    );
                }
            }
        });
    }
}
