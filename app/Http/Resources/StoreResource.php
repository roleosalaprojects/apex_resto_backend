<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'name' => $this->name,
            'header' => $this->header,
            'footer' => $this->footer,
            'tin' => $this->tin,
            'vat_reg' => $this->vat_reg,
            'phone' => $this->phone,
            'email' => $this->email,
            'counter' => $this->counter,
            'status' => $this->status,
            'pos' => $this->whenLoaded('pos'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
