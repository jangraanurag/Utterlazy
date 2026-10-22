<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderTrainingModule;
use App\Models\RiderTrainingProgress;
use Illuminate\Http\Request;

class RiderTrainingController extends Controller
{
    public function modules(Request $request)
    {
        $rider = $request->user();

        $modules = RiderTrainingModule::where('is_active', true)
            ->with(['lessons', 'quizQuestions'])
            ->orderBy('sort_order')
            ->get();

        $progress = RiderTrainingProgress::where('rider_id', $rider->id)
            ->get()
            ->keyBy('module_id');

        $modules->each(function ($module) use ($progress) {
            $p = $progress->get($module->id);
            $module->completed_lessons = $p?->completed_lessons ?? [];
            $module->quiz_passed = $p?->quiz_passed ?? false;
            $module->quiz_score = $p?->quiz_score ?? 0;
            $module->completed = $p?->completed_at !== null;
            if ($module->quizQuestions) {
                $module->quizQuestions->makeHidden(['correct_index']);
            }
        });

        return response()->json(['modules' => $modules]);
    }

    public function markLessonComplete(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'module_id' => 'required|uuid',
            'lesson_id' => 'required|uuid',
        ]);

        $progress = RiderTrainingProgress::firstOrCreate(
            ['rider_id' => $rider->id, 'module_id' => $validated['module_id']],
            ['completed_lessons' => []]
        );

        $completed = $progress->completed_lessons ?? [];
        if (! in_array($validated['lesson_id'], $completed)) {
            $completed[] = $validated['lesson_id'];
            $progress->update(['completed_lessons' => $completed]);
        }

        return response()->json([
            'message' => 'Lesson marked complete.',
            'completed_lessons' => $completed,
        ]);
    }

    public function submitQuiz(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'module_id' => 'required|uuid',
            'answers' => 'required|array',
            'answers.*' => 'integer',
        ]);

        $questions = RiderTrainingModule::find($validated['module_id'])->quizQuestions;
        $correct = 0;
        foreach ($questions as $i => $q) {
            if (isset($validated['answers'][$i]) && $validated['answers'][$i] === $q->correct_index) {
                $correct++;
            }
        }

        $total = $questions->count();
        $score = $total > 0 ? round(($correct / $total) * 100) : 0;
        $passed = $score >= 70;

        $progress = RiderTrainingProgress::firstOrCreate(
            ['rider_id' => $rider->id, 'module_id' => $validated['module_id']],
            ['completed_lessons' => []]
        );

        $progress->update([
            'quiz_score' => $score,
            'quiz_passed' => $passed,
            'completed_at' => $passed ? now() : null,
        ]);

        return response()->json([
            'score' => $score,
            'correct' => $correct,
            'total' => $total,
            'passed' => $passed,
            'message' => $passed ? 'Quiz passed! Module completed.' : 'Quiz failed. Need 70% to pass.',
        ]);
    }

    public function progress(Request $request)
    {
        $rider = $request->user();

        $progress = RiderTrainingProgress::where('rider_id', $rider->id)
            ->with('module:id,title')
            ->get();

        $totalModules = RiderTrainingModule::where('is_active', true)->count();
        $completedModules = $progress->whereNotNull('completed_at')->count();

        return response()->json([
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'all_complete' => $totalModules > 0 && $completedModules >= $totalModules,
            'progress' => $progress,
        ]);
    }
}
