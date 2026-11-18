<?php

use App\Models\Essay;
use App\Models\User;
use App\Services\EssayRubric;
use App\Services\LanguageToolGrammarChecker;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.ollama.base_url' => 'http://127.0.0.1:11434', 'services.ollama.model' => 'test-model']);
    config(['services.languagetool.base_url' => 'http://127.0.0.1:8081', 'services.languagetool.language' => 'en-GB']);
    Http::preventStrayRequests();
});

function rubricAssessment(?int $taskScore = null): array
{
    $rubric = json_decode(file_get_contents(base_path('tests/Fixtures/ollama-feedback.json')), true)['rubric'];
    if ($taskScore !== null) {
        $rubric['task_fulfilment'] = ['score' => $taskScore, 'feedback' => 'Address both benefits and drawbacks with specific supporting examples.'];
    }

    return $rubric;
}

function fakeRubricEvaluation(?array $rubric, array $extra = []): void
{
    $feedback = json_decode(file_get_contents(base_path('tests/Fixtures/ollama-feedback.json')), true);
    $feedback['rubric'] = $rubric;
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(['matches' => []]),
        'http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true, 'message' => ['content' => json_encode([...$feedback, ...$extra])],
        ]),
    ]);
}

test('task essays receive three component marks out of sixty without another deduction', function (int $taskScore, int $language, int $organisation, int $total, string $band) {
    $rubric = rubricAssessment($taskScore);
    $rubric['language']['score'] = $language;
    $rubric['organisation']['score'] = $organisation;
    fakeRubricEvaluation($rubric, ['score' => 99, 'task_review' => ['deduction' => 30]]);
    $user = User::factory()->create();
    $content = 'Online learning lets students study anywhere and arrange lessons around their other commitments.';
    $task = "Discuss online learning.\nInclude benefits and drawbacks with examples.";

    $this->actingAs($user)->post('/essays', ['content' => $content, 'task' => $task])->assertSessionHasNoErrors();
    $essay = Essay::firstOrFail();
    expect($essay->task)->toBe($task);
    expect($essay->score)->toBe($total);
    expect($essay->score_max)->toBe(60);
    expect($essay->analysis)->not->toHaveKey('task_review');
    expect($essay->analysis['rubric'])->toMatchArray([
        'version' => 'uploaded_2026_11_10', 'total' => $total, 'maximum' => 60, 'provisional' => false, 'total_band' => $band,
    ]);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8081/v2/check' && $request['text'] === $content);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
        && json_decode($request['messages'][1]['content'], true)['task'] === $task
        && json_decode($request['messages'][1]['content'], true)['essay'] === $content
        && in_array('task_fulfilment', $request['format']['properties']['rubric']['required'], true));

    $this->get('/dashboard?section=history&essay='.$essay->id)->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('selectedEssay.task', $task)
        ->where('selectedEssay.score', $total)
        ->where('selectedEssay.score_max', 60)
        ->where('selectedEssay.analysis.rubric.components.task_fulfilment.score', $taskScore)
        ->where('selectedEssay.analysis.rubric.components.task_fulfilment.feedback', 'Address both benefits and drawbacks with specific supporting examples.')
        ->where('dashboardSummary.latest_score', fn ($value) => $value == round($total / 60 * 100, 1)));
})->with([
    'maximum' => [20, 20, 20, 60, '51–60'],
    'partial task fulfilment' => [8, 16, 14, 38, '31–40'],
    'off-topic does not incur a second penalty' => [0, 20, 20, 40, '31–40'],
    'low score' => [1, 2, 3, 6, '0–10'],
    'zero' => [0, 0, 0, 0, '0–10'],
]);

test('an absent or blank task receives only a provisional score out of forty', function (array $input) {
    fakeRubricEvaluation(rubricAssessment(20), ['score' => 100, 'task_review' => ['deduction' => 30]]);
    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'Online learning gives students flexibility to study wherever they live.', ...$input,
    ])->assertSessionHasNoErrors();
    $essay = Essay::firstOrFail();
    expect($essay->score)->toBe(29);
    expect($essay->score_max)->toBe(40);
    expect($essay->task)->toBeNull();
    expect($essay->analysis)->not->toHaveKey('task_review');
    expect($essay->analysis['rubric']['provisional'])->toBeTrue();
    expect($essay->analysis['rubric']['total_band'])->toBeNull();
    expect($essay->analysis['rubric']['components'])->not->toHaveKey('task_fulfilment');
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
        && ! array_key_exists('task', json_decode($request['messages'][1]['content'], true))
        && ! array_key_exists('task_fulfilment', $request['format']['properties']['rubric']['properties']));
})->with([
    'omitted' => [[]], 'null' => [['task' => null]], 'empty' => [['task' => '']], 'whitespace' => [['task' => " \t\n "]],
]);

test('invalid component assessments cannot be saved', function (string $failure) {
    $rubric = rubricAssessment(12);
    match ($failure) {
        'missing rubric' => $rubric = null,
        'missing task component' => $rubric = rubricAssessment(),
        'missing language' => $rubric['language'] = null,
        'missing organisation' => $rubric['organisation'] = null,
        'negative' => $rubric['language']['score'] = -1,
        'over maximum' => $rubric['organisation']['score'] = 21,
        'fractional' => $rubric['task_fulfilment']['score'] = 1.5,
        'missing score' => $rubric['language']['score'] = null,
        'missing guidance' => $rubric['task_fulfilment']['feedback'] = '',
    };
    fakeRubricEvaluation($rubric);
    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'Online learning gives students flexibility to study wherever they live.',
        'task' => 'Discuss the benefits and drawbacks of online learning.',
    ])->assertSessionHasErrors('content');
    expect(Essay::count())->toBe(0);
})->with(['missing rubric', 'missing task component', 'missing language', 'missing organisation', 'negative', 'over maximum', 'fractional', 'missing score', 'missing guidance']);

test('task input is validated before calling either service', function (mixed $task) {
    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'Online learning gives students flexibility to study wherever they live.', 'task' => $task,
    ])->assertSessionHasErrors('task');
    Http::assertNothingSent();
    expect(Essay::count())->toBe(0);
})->with(['too long' => [str_repeat('x', 3001)], 'array' => [['invalid']], 'number' => [123]]);

test('a task at the length limit can be submitted', function () {
    fakeRubricEvaluation(rubricAssessment(20));
    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'Online learning gives students flexibility to study wherever they live.', 'task' => str_repeat('x', 3000),
    ])->assertSessionHasNoErrors();
    expect(mb_strlen(Essay::firstOrFail()->task))->toBe(3000);
});

test('authenticated users can submit an essay and receive stored feedback', function () {
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/languagetool-feedback.json')), true)),
        'http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true,
            'message' => ['content' => file_get_contents(base_path('tests/Fixtures/ollama-feedback.json'))],
        ])]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/essays', [
        'content' => 'i believe online learning is useful because students can study anywhere. However, the essay essay needs clear examples and better structure.',
    ]);

    $essay = Essay::query()->first();

    $response->assertRedirect(route('dashboard', [
        'section' => 'essay-review',
        'essay' => $essay->id,
    ], false));

    expect($essay)
        ->not->toBeNull()
        ->user_id->toBe($user->id)
        ->score->toBeGreaterThan(0)
        ->level->toBeIn(['Weak', 'Average', 'Good'])
        ->feedback->not->toBeEmpty()
        ->suggestions->not->toBeEmpty();

    expect($essay->score)->toBe(29);
    expect($essay->analysis['source'])->toBe('ollama');
    expect($essay->analysis['cefr']['level'])->toBe('B1');
    expect($essay->analysis['grammar'])->toMatchArray(['source' => 'languagetool', 'issue_count' => 1, 'language' => 'en-GB']);
    expect($essay->analysis['grammar']['issues'][0])->toMatchArray(['text' => 'i', 'replacements' => ['I']]);
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8081/v2/check'
        && $request->method() === 'POST'
        && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
        && $request['text'] === $essay->content
        && $request['language'] === 'en-GB');
    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
        && $request['model'] === 'test-model'
        && $request['stream'] === false
        && str_contains($request['messages'][1]['content'], 'online learning')
        && json_decode($request['messages'][1]['content'], true)['grammar_check']['issues'][0]['replacements'] === ['I']);

    $this->get('/dashboard?section=history&essay='.$essay->id)
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('selectedEssay.analysis.cefr.level', 'B1')
            ->where('selectedEssay.analysis.grammar.issues.0.text', 'i'));
});

test('failed Ollama requests do not create an essay or fall back to local grading', function () {
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(['matches' => []]),
        'http://127.0.0.1:11434/api/chat' => Http::response(['error' => 'model not found'], 404),
    ]);

    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'This essay is long enough to submit but the configured model is missing.',
    ])->assertSessionHasErrors('content');

    expect(Essay::count())->toBe(0);
});

test('invalid or incomplete Ollama feedback is rejected', function () {
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(['matches' => []]),
        'http://127.0.0.1:11434/api/chat' => Http::response(['done' => true, 'message' => ['content' => '{"score": 120}']]),
    ]);

    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'This essay is long enough to submit but the model response is invalid.',
    ])->assertSessionHasErrors('content');

    expect(Essay::count())->toBe(0);
});

test('Ollama connection failures are shown on the essay form', function () {
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(['matches' => []]),
        'http://127.0.0.1:11434/api/chat' => Http::failedConnection(),
    ]);

    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'This essay is long enough to submit but Ollama is not running.',
    ])->assertSessionHasErrors('content');

    expect(Essay::count())->toBe(0);
});

test('essay submission requires enough text to analyze', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/essays', ['content' => 'Too short.'])
        ->assertSessionHasErrors('content');
    Http::assertNothingSent();
});

test('LanguageTool failures stop evaluation and are shown without saving an essay', function (string $failure) {
    $response = match ($failure) {
        'connection' => Http::failedConnection(),
        'rate limit' => Http::response([], 429),
        'server error' => Http::response([], 503),
        'malformed' => Http::response(['unexpected' => true]),
        'incomplete' => Http::response(['matches' => [], 'warnings' => ['incompleteResults' => true]]),
        'invalid positions' => Http::response(['matches' => [[
            'message' => 'Invalid position', 'offset' => 9999, 'length' => 1,
            'replacements' => [], 'rule' => ['id' => 'TEST'],
        ]]]),
    };
    Http::fake(['http://127.0.0.1:8081/v2/check' => $response]);

    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'This essay is long enough to submit for a grammar check.',
    ])->assertSessionHasErrors('content');
    expect(session('errors')->first('content'))->toContain('LanguageTool');

    expect(Essay::count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/chat'));
})->with(['connection', 'rate limit', 'server error', 'malformed', 'incomplete', 'invalid positions']);

test('valid essays without grammar matches still receive CEFR feedback', function () {
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(['matches' => []]),
        'http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true, 'message' => ['content' => file_get_contents(base_path('tests/Fixtures/ollama-feedback.json'))],
        ]),
    ]);

    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'Online learning gives students flexibility to study wherever they live.',
    ])->assertSessionHasNoErrors();

    expect(Essay::first()->analysis['grammar']['issues'])->toBe([]);
    expect(Essay::first()->analysis['cefr']['level'])->toBe('B1');
});

test('invalid CEFR assessments are rejected', function (mixed $cefr) {
    $feedback = json_decode(file_get_contents(base_path('tests/Fixtures/ollama-feedback.json')), true);
    $feedback['cefr'] = $cefr;
    Http::fake([
        'http://127.0.0.1:8081/v2/check' => Http::response(['matches' => []]),
        'http://127.0.0.1:11434/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode($feedback)]]),
    ]);

    $this->actingAs(User::factory()->create())->post('/essays', [
        'content' => 'This essay is long enough to submit but the CEFR assessment is invalid.',
    ])->assertSessionHasErrors('content');
    expect(Essay::count())->toBe(0);
})->with([
    'missing assessment' => [null],
    'invalid band' => [['level' => 'B3', 'explanation' => 'Invalid band.']],
    'missing evidence' => [['level' => 'B1']],
    'band repeated instead of evidence' => [['level' => 'B1', 'explanation' => 'B1']],
    'contradictory assessment' => [['level' => 'A1', 'explanation' => 'This essay demonstrates B1 writing with connected ideas and explanations.']],
]);

test('LanguageTool positions preserve Unicode text after emoji', function () {
    Http::fake(['http://127.0.0.1:8081/v2/check' => Http::response(['matches' => [[
        'message' => 'Use an before a vowel sound.', 'offset' => 11, 'length' => 1,
        'replacements' => [['value' => 'an']], 'rule' => ['id' => 'EN_A_VS_AN', 'issueType' => 'grammar'],
    ]]])]);

    $grammar = app(LanguageToolGrammarChecker::class)->check('😀 This is a example of learning.');
    expect($grammar['issues'][0]['text'])->toBe('a');
    expect($grammar['issues'][0]['replacements'])->toBe(['an']);
});

test('dashboard retrieves essay history and performance summary for the user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Essay::query()->create([
        'user_id' => $user->id,
        'content' => 'First stored essay with feedback history.',
        'word_count' => 6,
        'sentence_count' => 1,
        'score' => 55,
        'level' => 'Average',
        'analysis' => ['grammar' => [], 'vocabulary' => [], 'structure' => []],
        'feedback' => ['Improve sentence clarity.'],
        'suggestions' => ['Add more precise vocabulary.'],
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $latestEssay = Essay::query()->create([
        'user_id' => $user->id,
        'content' => 'Second stored essay with stronger score and linked feedback.',
        'word_count' => 9,
        'sentence_count' => 1,
        'score' => 75,
        'level' => 'Good',
        'analysis' => ['grammar' => [], 'vocabulary' => [], 'structure' => []],
        'feedback' => ['Structure is improving.'],
        'suggestions' => ['Keep paragraph transitions clear.'],
    ]);

    Essay::query()->create([
        'user_id' => $otherUser->id,
        'content' => 'This essay belongs to another user.',
        'word_count' => 7,
        'sentence_count' => 1,
        'score' => 20,
        'level' => 'Weak',
        'analysis' => ['grammar' => [], 'vocabulary' => [], 'structure' => []],
        'feedback' => ['Private feedback.'],
        'suggestions' => ['Private suggestion.'],
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('essays', 2)
            ->where('selectedEssay.id', $latestEssay->id)
            ->where('dashboardSummary.total_essays', 2)
            ->where('dashboardSummary.average_score', 65)
            ->where('dashboardSummary.best_score', 75)
            ->where('dashboardSummary.latest_score', 75)
            ->where('dashboardSummary.progress_change', 20)
            ->where('dashboardSummary.progress_label', 'Improving')
            ->has('dashboardSummary.recent_submissions', 2)
            ->has('dashboardSummary.score_history', 2)
        );
});

test('component marks use the adapted twenty-point descriptor boundaries', function (int $score, string $band) {
    $assessment = rubricAssessment();
    $assessment['language']['score'] = $score;
    $result = app(EssayRubric::class)->grade($assessment, false);
    expect($result['rubric']['components']['language']['band'])->toBe($band);
    expect($result['score'])->toBe($score + 14);
    expect($result['score_max'])->toBe(40);
})->with([
    [0, '0–7'], [7, '0–7'], [8, '8–9'], [9, '8–9'], [10, '10–12'], [12, '10–12'],
    [13, '13–15'], [15, '13–15'], [16, '16–17'], [17, '16–17'], [18, '18–20'], [20, '18–20'],
]);

test('full totals use the total bands from the uploaded sheet', function (int $total, string $band) {
    $assessment = rubricAssessment(min(20, $total));
    $assessment['language']['score'] = min(20, max(0, $total - 20));
    $assessment['organisation']['score'] = max(0, $total - 40);
    $result = app(EssayRubric::class)->grade($assessment, true);
    expect($result['score'])->toBe($total);
    expect($result['score_max'])->toBe(60);
    expect($result['rubric']['total_band'])->toBe($band);
})->with([
    [0, '0–10'], [10, '0–10'], [11, '11–20'], [20, '11–20'], [21, '21–30'], [30, '21–30'],
    [31, '31–40'], [40, '31–40'], [41, '41–50'], [50, '41–50'], [51, '51–60'], [60, '51–60'],
]);

test('mixed marking schemes retain their scores and use percentages for summaries', function (bool $hasTask) {
    $user = User::factory()->create();
    $legacy = Essay::create([
        'user_id' => $user->id, 'content' => 'A previously graded essay stays on its original scale.', 'task' => 'The previous task.',
        'score' => 80, 'level' => 'Good',
        'analysis' => ['task_review' => ['base_score' => 90, 'requested_deduction' => 10, 'deduction' => 10, 'status' => 'partially_met', 'feedback' => ['Add supporting examples.']]],
        'feedback' => ['Legacy feedback.'], 'suggestions' => ['Legacy suggestions.'], 'created_at' => now()->subDay(),
    ]);
    $assessment = rubricAssessment($hasTask ? 10 : null);
    $assessment['language']['score'] = 10;
    $assessment['organisation']['score'] = 10;
    $grade = app(EssayRubric::class)->grade($assessment, $hasTask);
    $latest = Essay::create([
        'user_id' => $user->id, 'content' => 'A new essay graded under the uploaded marking scheme.', 'task' => $hasTask ? 'Discuss learning.' : null,
        'score' => $grade['score'], 'score_max' => $grade['score_max'], 'level' => $grade['level'],
        'analysis' => ['rubric' => $grade['rubric']], 'feedback' => ['New feedback.'], 'suggestions' => ['New suggestions.'],
    ]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('selectedEssay.id', $latest->id)
        ->where('selectedEssay.score', $hasTask ? 30 : 20)
        ->where('selectedEssay.score_max', $hasTask ? 60 : 40)
        ->where('selectedEssay.score_percentage', 50)
        ->where('dashboardSummary.average_score', 65)
        ->where('dashboardSummary.best_score', 80)
        ->where('dashboardSummary.latest_score', 50)
        ->where('dashboardSummary.progress_change', -30));

    $this->get('/dashboard?section=history&essay='.$legacy->id)->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('selectedEssay.score', 80)
        ->where('selectedEssay.score_max', 100)
        ->where('selectedEssay.analysis.task_review.deduction', 10));
    expect($legacy->fresh()->score)->toBe(80);
})->with([true, false]);
