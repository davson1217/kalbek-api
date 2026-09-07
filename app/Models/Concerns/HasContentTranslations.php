<?php

namespace App\Models\Concerns;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasContentTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(ContentTranslation::class, 'translatable');
    }

    public function translated(string $field, string $locale, ?string $fallback = null): ?string
    {
        $translation = $this->loadedTranslation($field, $locale)
            ?? $this->loadedTranslation($field, 'en');

        return $translation !== null && $translation !== '' ? $translation : $fallback;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function translationMap(): array
    {
        /** @var Collection<int, ContentTranslation> $translations */
        $translations = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->get();

        return $translations
            ->groupBy('field')
            ->map(fn (Collection $items): array => $items->pluck('value', 'locale')->all())
            ->all();
    }

    private function loadedTranslation(string $field, string $locale): ?string
    {
        if (! $this->relationLoaded('translations')) {
            /** @var ContentTranslation|null $translation */
            $translation = $this->translations()
                ->where('field', $field)
                ->where('locale', $locale)
                ->first();

            return $translation?->value;
        }

        /** @var Collection<int, ContentTranslation> $translations */
        $translations = $this->getRelation('translations');

        return $translations
            ->first(fn (ContentTranslation $translation): bool => $translation->field === $field && $translation->locale === $locale)
            ?->value;
    }
}
