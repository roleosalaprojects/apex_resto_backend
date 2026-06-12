<?php

namespace App\Http\Resources;

use App\Models\Accounting\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
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
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'account_type_name' => $this->account_type_name,
            'opening_balance' => (float) $this->opening_balance,
            'balance' => (float) $this->balance,
            'description' => $this->description,
            'total_deposits' => $this->when($request->routeIs('api.*.banks.show'), (float) $this->total_deposits),
            'total_withdrawals' => $this->when($request->routeIs('api.*.banks.show'), (float) $this->total_withdrawals),
            'recent_transactions' => BankTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get account type constants for frontend reference.
     *
     * @return array<string, int>
     */
    public static function accountTypes(): array
    {
        return [
            'savings' => Bank::TYPE_SAVINGS,
            'checking' => Bank::TYPE_CHECKING,
            'credit' => Bank::TYPE_CREDIT,
            'passbook' => Bank::TYPE_PASSBOOK,
            'ewallet' => Bank::TYPE_EWALLET,
        ];
    }
}
