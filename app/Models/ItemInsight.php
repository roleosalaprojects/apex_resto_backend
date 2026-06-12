<?php

namespace App\Models;

use App\Models\Products\Item;
use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'insight_date',
        'item_id',
        'rank',
        'sellability_score',
        'score_breakdown',
        'ai_insight',
        'predicted_qty',
        'current_stock',
        'profit_margin',
        'category_name',
        'factors',
    ];

    protected $casts = [
        'insight_date' => 'date',
        'sellability_score' => 'decimal:2',
        'score_breakdown' => 'array',
        'predicted_qty' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'factors' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
