<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'points' => $this->points,
            'accumulated_points' => $this->accumulated_points,
            'note' => $this->note,
            'image' => $this->image,
            'status' => $this->status,
            'credit_limit' => $this->credit_limit,
            'credit_balance' => $this->credit_balance,
            'credit_term_days' => $this->credit_term_days,
            'available_credit' => $this->available_credit,
            'e_name' => $this->e_name,
            'e_phone' => $this->e_phone,
            'e_address' => $this->e_address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
