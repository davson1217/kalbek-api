<?php

namespace App\Services\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SyncContentTranslations
{
    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    public function rules(array $fields): array
    {
        $rules = [
            'translations' => ['sometimes', 'array'],
        ];

        foreach ($fields as $field) {
            $rules["translations.{$field}"] = ['sometimes', 'array'];
            $rules["translations.{$field}.*"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /**
     * @param  array<string, array<string, string|null>>  $translations
     * @param  array<int, string>  $fields
     */
    public function sync(Model $model, array $translations, array $fields): void
    {
        if (! method_exists($model, 'translations')) {
            return;
        }

        foreach ($fields as $field) {
            foreach (Arr::get($translations, $field, []) as $locale => $value) {
                $locale = str($locale)->lower()->trim()->value();

                if ($locale === '' || ! in_array($locale, ['en', 'lt'], true)) {
                    continue;
                }

                $value = is_string($value) ? trim($value) : '';

                if ($value === '') {
                    $model->translations()
                        ->where('field', $field)
                        ->where('locale', $locale)
                        ->delete();

                    continue;
                }

                $model->translations()->updateOrCreate(
                    ['field' => $field, 'locale' => $locale],
                    ['value' => $value],
                );
            }
        }
    }
}
