<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleLineResource extends JsonResource
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
            'qty' => $this->qty,
            'unit' => $this->unit,
            'unit_qty' => $this->unit_qty,
            'price' => $this->price,
            'cost' => $this->cost,
            'discount' => $this->discount,
            'sub_total' => $this->sub_total,
            'vatable' => $this->vatable,
            'vat' => $this->vat,
            'exempt' => $this->exempt,
            'zero_rated' => $this->zero_rated,
            'refundable' => $this->refundable,
            'refunded' => $this->refunded,
            'profit' => $this->profit,
            'sc_discount' => $this->sc_discount,
            'pwd_discount' => $this->pwd_discount,
            'sp_discount' => $this->sp_discount,
            'naac_discount' => $this->naac_discount,
            'item' => new ItemResource($this->whenLoaded('item')),
            'unit_relation' => $this->whenLoaded('unit'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
