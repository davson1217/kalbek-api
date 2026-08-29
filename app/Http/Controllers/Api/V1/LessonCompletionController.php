<?php

namespace App\Http\Controllers\Api\V1;

use App\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteLessonRequest;
use App\Http\Resources\Api\V1\LessonProgressResource;
use App\Models\Scenario;
use App\Models\User;
use App\Services\Progress\CompleteLesson;
use Illuminate\Http\JsonResponse;

class LessonCompletionController extends Controller
{
    public function __invoke(
        CompleteLessonRequest $request,
        Scenario $scenario,
        CompleteLesson $completeLesson,
    ): JsonResponse {
        abort_unless($scenario->status === ContentStatus::Published, 404);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $progress = $completeLesson->handle($user, $scenario, $data['score'], $data['xp']);

        return response()->json([
            'data' => LessonProgressResource::make($progress)->resolve(),
        ]);
    }
}
