<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
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
            'payee' => $this->payee,
            'amount' => (float) $this->amount,
            'formatted_amount' => number_format($this->amount, 2),
            'expense_date' => $this->expense_date->toDateString(),
            'formatted_date' => $this->expense_date->format('M d, Y'),
            'description' => $this->description,
            'receipt_number' => $this->receipt_number,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'is_active' => $this->isActive(),
            'is_voided' => $this->isVoided(),
            'category' => $this->whenLoaded('category', fn () => new ExpenseCategoryResource($this->category)),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'name' => $this->store->name,
            ]),
            'bank' => $this->whenLoaded('bank', fn () => [
                'id' => $this->bank->id,
                'account_name' => $this->bank->account_name,
                'bank_name' => $this->bank->bank_name,
            ]),
            'bank_transaction' => $this->whenLoaded('bankTransaction', fn () => [
                'id' => $this->bankTransaction->id,
                'reference_number' => $this->bankTransaction->reference_number,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ] : null),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Get available status options.
     */
    public static function statuses(): array
    {
        return [
            ['value' => 1, 'label' => 'Active'],
            ['value' => 2, 'label' => 'Voided'],
        ];
    }
}
