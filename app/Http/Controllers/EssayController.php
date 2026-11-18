<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Services\OllamaEssayEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EssayController extends Controller
{
    public function index(Request $request): Response
    {
        $essays = $request->user()
            ->essays()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Essay $essay) => $this->serializeEssay($essay));

        $selectedEssay = $essays->firstWhere('id', (int) $request->query('essay')) ?? $essays->first();

        return Inertia::render('dashboard', [
            'essays' => $essays,
            'selectedEssay' => $selectedEssay,
            'dashboardSummary' => $this->dashboardSummary($essays),
        ]);
    }

    public function store(Request $request, OllamaEssayEvaluator $evaluator): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:30', 'max:15000'],
            'task' => ['nullable', 'string', 'max:3000'],
        ]);
        $task = filled($validated['task'] ?? null) ? trim($validated['task']) : null;

        try {
            $result = $evaluator->analyze($validated['content'], $task);
        } catch (\RuntimeException $exception) {
            Log::warning('Essay evaluation failed.', ['reason' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['content' => $exception->getMessage()]);
        }

        $essay = $request->user()->essays()->create([
            'content' => $validated['content'],
            'task' => $task,
            ...$result,
        ]);

        return redirect()->route('dashboard', [
            'section' => 'essay-review',
            'essay' => $essay->id,
        ])->with('success', 'Essay reviewed with LanguageTool and Ollama successfully.');
    }

    /** @return array<string, mixed> */
    private function serializeEssay(Essay $essay): array
    {
        return [
            'id' => $essay->id,
            'content' => $essay->content,
            'task' => $essay->task,
            'excerpt' => str($essay->content)->squish()->limit(180)->toString(),
            'word_count' => $essay->word_count,
            'sentence_count' => $essay->sentence_count,
            'score' => $essay->score,
            'score_max' => $essay->score_max,
            'score_percentage' => round($essay->score / $essay->score_max * 100, 1),
            'level' => $essay->level,
            'analysis' => $essay->analysis,
            'feedback' => $essay->feedback,
            'suggestions' => $essay->suggestions,
            'created_at' => $essay->created_at?->format('M j, Y g:i A'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $essays
     * @return array<string, mixed>
     */
    private function dashboardSummary($essays): array
    {
        $latestScore = $essays->first()['score_percentage'] ?? null;
        $previousScore = $essays->skip(1)->first()['score_percentage'] ?? null;
        $oldestScore = $essays->last()['score_percentage'] ?? null;
        $averageScore = $essays->isNotEmpty() ? round($essays->avg('score_percentage'), 1) : null;
        $bestScore = $essays->isNotEmpty() ? $essays->max('score_percentage') : null;
        $progressChange = $latestScore !== null && $oldestScore !== null ? round($latestScore - $oldestScore, 1) : 0;

        return [
            'total_essays' => $essays->count(),
            'average_score' => $averageScore,
            'best_score' => $bestScore,
            'latest_score' => $latestScore,
            'previous_score' => $previousScore,
            'progress_change' => $progressChange,
            'progress_label' => match (true) {
                $essays->count() < 2 => 'Need more submissions',
                $progressChange > 0 => 'Improving',
                $progressChange < 0 => 'Needs attention',
                default => 'Stable',
            },
            'score_history' => $essays
                ->reverse()
                ->values()
                ->map(fn (array $essay) => [
                    'id' => $essay['id'],
                    'score' => $essay['score'],
                    'score_max' => $essay['score_max'],
                    'score_percentage' => $essay['score_percentage'],
                    'level' => $essay['level'],
                    'created_at' => $essay['created_at'],
                ])
                ->all(),
            'recent_submissions' => $essays->take(5)->values()->all(),
        ];
    }
}
