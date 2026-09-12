<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'native_name',
    'support_language_code',
    'support_language_name',
    'status',
    'default_voice',
    'sort_order',
])]
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
