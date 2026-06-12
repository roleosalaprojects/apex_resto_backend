<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'code' => $this->code,
            'status' => $this->status,
            'is_customer' => $this->is_customer,
            'role' => new RoleResource($this->whenLoaded('role')),
            'details' => new EmployeeResource($this->whenLoaded('details')),
            'schedule' => $this->whenLoaded('schedule'),
            'position' => new RoleResource($this->whenLoaded('position')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
