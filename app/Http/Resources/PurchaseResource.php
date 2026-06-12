<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
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
            'po' => $this->po,
            'invoice_no' => $this->invoice_no,
            'total' => $this->total,
            'amount' => $this->amount,
            'items' => $this->items,
            'purchased' => $this->purchased,
            'expected' => $this->expected,
            'received' => $this->received,
            'note' => $this->note,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_type' => $this->payment_type,
            'date_issued' => $this->date_issued,
            'issued_to' => $this->issued_to,
            'issued_by' => $this->issued_by,
            'supplier' => $this->whenLoaded('supplier'),
            'store' => new StoreResource($this->whenLoaded('store')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
            'lines' => PurchaseLineResource::collection($this->whenLoaded('lines')),
            'adds' => $this->whenLoaded('adds'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
