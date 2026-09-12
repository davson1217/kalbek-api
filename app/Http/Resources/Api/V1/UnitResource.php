<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $this->supportLocale($request);

        return [
            'id' => $this->slug,
            'title' => $this->translated('title', $locale, $this->title),
            'description' => $this->translated('description', $locale, $this->description),
            'cefr_level' => $this->cefr_level?->value,
            'status' => $this->status->value,
            'sort_order' => $this->sort_order,
            'scenarios_count' => $this->scenarios_count ?? null,
            'language' => $this->whenLoaded('language', fn () => [
                'code' => $this->language->code,
                'name' => $this->language->name,
                'native_name' => $this->language->native_name,
                'support_language' => [
                    'code' => $this->language->support_language_code,
                    'name' => $this->language->support_language_name,
                ],
            ]),
            'scenarios' => $this->whenLoaded('scenarios', fn () => ScenarioSummaryResource::collection($this->scenarios)),
        ];
    }

    private function supportLocale(Request $request): string
    {
        $userLocale = $request->user('sanctum')?->profile?->app_language;

        return $request->string('app_language')->trim()->lower()->value() ?: ($userLocale ?: 'en');
    }
}
