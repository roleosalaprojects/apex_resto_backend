<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDetailResource extends JsonResource
{
    /**
     * @var array{total_transactions: int, total_spent: float, average_transaction: float, last_purchase_date: string|null}
     */
    protected array $transactionSummary = [];

    /**
     * Set the transaction summary data.
     *
     * @param  array{total_transactions: int, total_spent: float, average_transaction: float, last_purchase_date: string|null}  $summary
     */
    public function setTransactionSummary(array $summary): self
    {
        $this->transactionSummary = $summary;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'zip' => $this->zip,
            'country' => $this->country,
            'tin' => $this->tin,
            'business_type' => $this->business_type,
            'note' => $this->note,
            'image' => $this->image,
            'status' => $this->status,
            'e_name' => $this->e_name,
            'e_phone' => $this->e_phone,
            'e_address' => $this->e_address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'loyalty_points' => [
                'current_balance' => (float) $this->points,
                'accumulated_total' => (float) $this->accumulated_points,
            ],
            'transaction_summary' => $this->transactionSummary,
            'purchase_history' => SaleResource::collection($this->whenLoaded('purchases')),
        ];
    }
}
