<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'counter' => $this->counter,
            'son' => $this->son,
            'total' => $this->total,
            'cash' => $this->cash,
            'change' => $this->change,
            'vatable' => $this->vatable,
            'vat' => $this->vat,
            'vat_exempt' => $this->vat_exempt,
            'non_vat' => $this->non_vat,
            'zero_rated' => $this->zero_rated,
            'discount' => $this->discount,
            'profit' => $this->profit,
            'type' => $this->type,
            'cancelled' => $this->cancelled,
            'payment_type' => $this->payment_type,
            'reference_number' => $this->reference_number,
            'bank_amount' => $this->bank_amount,
            'header' => $this->header,
            'footer' => $this->footer,
            'sc_discount' => $this->sc_discount,
            'pwd_discount' => $this->pwd_discount,
            'sp_discount' => $this->sp_discount,
            'naac_discount' => $this->naac_discount,
            'vat_special_discounts' => $this->vat_special_discounts,
            'special_discount_type' => $this->special_discount_type,
            'special_discount_name' => $this->special_discount_name,
            'special_discount_id' => $this->special_discount_id,
            'special_discount_tin' => $this->special_discount_tin,
            'acquired_points' => $this->acquired_points,
            'points_used' => $this->points_used,
            'voucher_id' => $this->voucher_id,
            'voucher_code' => $this->voucher_code,
            'voucher_discount' => $this->voucher_discount,
            'sold_by' => new UserResource($this->whenLoaded('sold_by')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'pos' => $this->whenLoaded('pos'),
            'store' => new StoreResource($this->whenLoaded('store')),
            'bank' => $this->whenLoaded('bank'),
            'lines' => SaleLineResource::collection($this->whenLoaded('lines')),
            'refund' => new SaleResource($this->whenLoaded('refund')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
