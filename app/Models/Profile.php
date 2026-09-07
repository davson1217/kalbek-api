<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'avatar_character_id',
    'display_name',
    'app_language',
    'show_translations',
    'xp',
    'hearts',
    'streak',
    'longest_streak',
    'last_practice_date',
])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatarCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'avatar_character_id');
    }

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'show_translations' => 'boolean',
            'hearts' => 'integer',
            'streak' => 'integer',
            'longest_streak' => 'integer',
            'last_practice_date' => 'date',
        ];
    }
}
