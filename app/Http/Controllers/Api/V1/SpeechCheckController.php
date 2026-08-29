<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SpeechCheckRequest;
use App\Services\Ai\SpeechEvaluator;
use Illuminate\Http\JsonResponse;

class SpeechCheckController extends Controller
{
    public function __invoke(SpeechCheckRequest $request, SpeechEvaluator $evaluator): JsonResponse
    {
        $data = $request->validated();

        return response()->json($evaluator->evaluate(
            $data['audio'],
            $data['intent'],
            $data['example'] ?? '',
            $data['context'] ?? '',
        ));
    }
}
