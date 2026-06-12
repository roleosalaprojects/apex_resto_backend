<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseLineResource extends JsonResource
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
            'cost' => $this->cost,
            'unit_qty' => $this->unit_qty,
            'unit_name' => $this->unit_name,
            'received' => $this->received,
            'item' => new ItemResource($this->whenLoaded('item')),
            'unit' => $this->whenLoaded('unit'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
