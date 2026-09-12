<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->supportLocale($request);

        return [
            'id' => $this->slug,
            'title' => $this->translated('title', $locale, $this->title),
            'subtitle' => $this->translated('subtitle', $locale, $this->subtitle),
            'description' => $this->translated('description', $locale, $this->description),
            'emoji' => $this->emoji,
            'language' => $this->whenLoaded('language', fn () => [
                'code' => $this->language->code,
                'name' => $this->language->name,
                'native_name' => $this->language->native_name,
                'support_language' => [
                    'code' => $this->language->support_language_code,
                    'name' => $this->language->support_language_name,
                ],
            ]),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->slug,
                'title' => $this->unit->translated('title', $locale, $this->unit->title),
                'description' => $this->unit->translated('description', $locale, $this->unit->description),
                'cefr_level' => $this->unit->cefr_level?->value,
                'sort_order' => $this->unit->sort_order,
            ] : null),
            'character' => $this->whenLoaded('character', fn () => [
                'id' => $this->character->slug,
                'name' => $this->character->name,
                'role' => $this->character->role,
                'image_path' => $this->character->image_path,
            ]),
            'tone' => $this->tone,
            'cefr_level' => $this->cefr_level?->value,
            'is_free' => (bool) $this->is_free,
            'available' => $this->getAttribute('available_for_user') ?? true,
            'availability_reason' => $this->getAttribute('availability_reason'),
        ];
    }

    private function supportLocale(Request $request): string
    {
        $userLocale = $request->user('sanctum')?->profile?->app_language;

        return $request->string('app_language')->trim()->lower()->value() ?: ($userLocale ?: 'en');
    }
}
