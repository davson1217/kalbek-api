<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LessonProgressResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LessonProgressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $progress = $user->lessonProgress()
            ->with('scenario')
            ->latest('completed_at')
            ->get();

        return LessonProgressResource::collection($progress);
    }
}
