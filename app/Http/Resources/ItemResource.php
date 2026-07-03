<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
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
            'barcode' => $this->barcode,
            'name' => $this->name,
            'cost' => $this->cost,
            'prev_cost' => $this->prev_cost,
            'price' => $this->price,
            'prev_price' => $this->prev_price,
            'markup' => $this->markup,
            'vatable' => $this->vatable,
            'discountable' => $this->discountable,
            'creditable_to_points' => $this->creditable_to_points,
            'type' => $this->type,
            'image' => $this->image,
            'status' => $this->status,
            'pwd' => $this->pwd,
            'senior' => $this->senior,
            'solo_parent' => $this->solo_parent,
            'naac' => $this->naac,
            'priority' => (bool) $this->priority,
            'is_composite' => (bool) $this->is_composite,
            'show_in_pos' => (bool) ($this->show_in_pos ?? true),
            'cost_override' => (bool) $this->cost_override,
            'uom_label' => $this->uom_label,
            'components' => $this->whenLoaded('components', function () {
                return $this->components->map(fn ($component) => [
                    'id' => $component->id,
                    'component_item_id' => $component->component_item_id,
                    'qty' => $component->qty,
                    'notes' => $component->notes,
                    'name' => $component->componentItem?->name,
                    'uom_label' => $component->componentItem?->uom_label,
                ]);
            }),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tax' => $this->whenLoaded('tax'),
            'supplier' => $this->whenLoaded('supplier'),
            'stocks' => $this->whenLoaded('stocks'),
            'item_units' => $this->whenLoaded('itemUnits'),
            'item_stores' => $this->whenLoaded('itemStores'),
            'wholesale_price_tiers' => $this->whenLoaded('wholesalePriceTiers'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
