<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advertisement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'advertisements';

    protected $fillable = [
        'name',
        'description',
        'image',
        'media_type',
        'duration',
        'status',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'status' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }
}
