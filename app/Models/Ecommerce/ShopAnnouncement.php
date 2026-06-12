<?php

namespace App\Models\Ecommerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopAnnouncement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'media_path',
        'media_type',
        'link_url',
        'link_text',
        'position',
        'display_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeScheduled($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('ends_at')
                ->orWhere('ends_at', '>=', $now);
        });
    }

    public function scopeHero($query)
    {
        return $query->where('position', 'hero');
    }

    public function scopeBanner($query)
    {
        return $query->where('position', 'banner');
    }

    public function scopePopup($query)
    {
        return $query->where('position', 'popup');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderByDesc('created_at');
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        return true;
    }

    public function getMediaUrlAttribute(): ?string
    {
        if (empty($this->media_path)) {
            return null;
        }

        return asset($this->media_path);
    }
}
