<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->languages() as $language) {
            Language::query()->updateOrCreate(
                ['code' => $language['code']],
                $language,
            );
        }
    }

    private function languages(): array
    {
        return [
            [
                'code' => 'lt',
                'name' => 'Lithuanian',
                'native_name' => 'Lietuvių',
                'support_language_code' => 'en',
                'support_language_name' => 'English',
                'status' => 'active',
                'default_voice' => null,
                'sort_order' => 10,
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'support_language_code' => 'en',
                'support_language_name' => 'English',
                'status' => 'active',
                'default_voice' => null,
                'sort_order' => 20,
            ],
        ];
    }
}
