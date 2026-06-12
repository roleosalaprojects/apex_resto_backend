<?php

namespace App\Models\Pos;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePaymentProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'path',
        'uploaded_by',
        'note',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Public asset URL for the proof image, or null if the path is empty.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->path ? asset($this->path) : null;
    }
}
