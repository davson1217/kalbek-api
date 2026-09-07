<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'field',
    'locale',
    'value',
])]
class ContentTranslation extends Model
{
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
