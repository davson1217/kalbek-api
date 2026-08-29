<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ScenarioResource;
use App\Http\Resources\Api\V1\ScenarioSummaryResource;
use App\Models\Scenario;
use App\Services\Content\ScenarioPayloadBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScenarioController extends Controller
{
    public function index(ScenarioPayloadBuilder $payloadBuilder): AnonymousResourceCollection
    {
        return ScenarioSummaryResource::collection($payloadBuilder->publishedSummaries());
    }

    public function show(Scenario $scenario, ScenarioPayloadBuilder $payloadBuilder): ScenarioResource
    {
        return ScenarioResource::make($payloadBuilder->publishedDetail($scenario));
    }
}
