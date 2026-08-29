<?php

namespace App\Models;

use Database\Factories\GeneratedAudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cache_key',
    'text',
    'voice',
    'provider',
    'model',
    'disk',
    'path',
    'mime_type',
    'bytes',
    'last_used_at',
])]
class GeneratedAudio extends Model
{
    /** @use HasFactory<GeneratedAudioFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }
}
