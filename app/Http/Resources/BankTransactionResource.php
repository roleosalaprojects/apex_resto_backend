<?php

namespace App\Http\Resources;

use App\Models\Accounting\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'bank_id' => $this->bank_id,
            'transfer_to_bank_id' => $this->transfer_to_bank_id,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'is_debit' => $this->isDebit(),
            'is_credit' => $this->isCredit(),
            'amount' => (float) $this->amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'payee' => $this->payee,
            'proof_photo' => $this->proof_photo,
            'proof_photo_url' => $this->proof_photo_url,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'bank' => new BankResource($this->whenLoaded('bank')),
            'transfer_to_bank' => new BankResource($this->whenLoaded('transferToBank')),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get transaction type constants for frontend reference.
     *
     * @return array<string, int>
     */
    public static function transactionTypes(): array
    {
        return [
            'deposit' => BankTransaction::TYPE_DEPOSIT,
            'withdrawal' => BankTransaction::TYPE_WITHDRAWAL,
            'transfer_out' => BankTransaction::TYPE_TRANSFER_OUT,
            'transfer_in' => BankTransaction::TYPE_TRANSFER_IN,
        ];
    }
}
