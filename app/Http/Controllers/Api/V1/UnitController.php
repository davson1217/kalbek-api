<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UnitResource;
use App\Models\Scenario;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function index(Request $request, SubscriptionManager $subscriptions): AnonymousResourceCollection
    {
        $languageCode = $request->string('language')->trim()->lower()->value() ?: null;

        $units = Unit::query()
            ->published()
            ->when($languageCode, fn ($query) => $query->whereHas('language', fn ($language) => $language->where('code', $languageCode)))
            ->with([
                'language',
                'translations',
                'scenarios' => fn ($query) => $query->published()->with(['character', 'language', 'unit.translations', 'translations']),
            ])
            ->withCount(['scenarios' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            $access = $subscriptions->accessFor($user);
            $units->each(fn (Unit $unit) => $unit->scenarios->each(function (Scenario $scenario) use ($access): void {
                $available = $access->canAccessScenario($scenario);
                $scenario->setAttribute('available_for_user', $available);
                $scenario->setAttribute('availability_reason', $available ? null : 'subscription_required');
            }));
        }

        return UnitResource::collection($units);
    }
}
