<?php

namespace App\Models\Products;

use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'name', 'description', 'image', 'icon', 'status', 'user_id',
        'featured', 'featured_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'featured_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id', 'id');
    }

    /**
     * Scope for the /shop homepage spotlight: featured + active,
     * ordered by featured_order (nulls last) then name.
     */
    public function scopeFeaturedSpotlight(Builder $query): Builder
    {
        return $query
            ->where('featured', true)
            ->where('status', true)
            ->orderByRaw('featured_order IS NULL, featured_order ASC')
            ->orderBy('name');
    }
}
