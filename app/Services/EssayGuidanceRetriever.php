<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class EssayGuidanceRetriever
{
    private const INDEX_FILE = 'essay-guidance-index.json';

    private const MIN_SIMILARITY = 0.4;

    public function __construct(private AnnotatedEssayReferences $annotatedReferences) {}

    /** @return array{guides: int, references: int} */
    public function rebuild(): array
    {
        $guides = $this->guides();
        $references = $this->annotatedReferences->load();
        $model = (string) config('essay_retrieval.model');
        $vectors = [];
        foreach (array_chunk($guides + $references, 24, true) as $batch) {
            $inputs = array_values(array_map(fn (array $guide) => 'search_document: '.$guide['title']."\n".$guide['text'], $batch));
            $embeddings = $this->embed($model, $inputs, max(60, $this->timeout()));
            $vectors += array_combine(array_keys($batch), $embeddings);
        }
        $path = Storage::disk('local')->path(self::INDEX_FILE);

        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode([
            'model' => $model,
            'corpus' => $this->corpusVersion($guides),
            'vectors' => $vectors,
            'references' => $references,
        ], JSON_THROW_ON_ERROR));

        return ['guides' => count($guides), 'references' => count($references)];
    }

    /** @return list<array{id: string, title: string, text: string, reference?: array{essay_id: string, source_cefr: string, provisional_muet_band: string}}> */
    public function retrieve(string $essay, ?string $task): array
    {
        if (! config('essay_retrieval.enabled')) {
            return [];
        }

        $disk = Storage::disk('local');

        try {
            $contents = $disk->get(self::INDEX_FILE);
            if ($contents === null) {
                return [];
            }

            $index = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $guides = $this->guides();
            $model = (string) config('essay_retrieval.model');
            if (($index['model'] ?? null) !== $model || ($index['corpus'] ?? null) !== $this->corpusVersion($guides)) {
                throw new RuntimeException('The essay guidance index is outdated. Run php artisan essay:rag-index.');
            }
            $references = filled(config('essay_retrieval.reference_path')) ? ($index['references'] ?? []) : [];
            if (! is_array($references)) {
                throw new RuntimeException('The essay guidance index contains invalid references. Run php artisan essay:rag-index.');
            }
            $passages = $guides + $references;
            $fingerprint = $references === [] ? null : $this->annotatedReferences->fingerprint($essay);

            $excerpt = mb_substr($essay, 0, 1000);
            $remaining = mb_strlen($essay) - 1000;
            if ($remaining > 0) {
                $excerpt .= "\n".mb_substr($essay, -min(500, $remaining));
            }
            $query = 'search_query: '.($task === null ? '' : 'Assignment: '.mb_substr($task, 0, 500)."\n").'Essay: '.$excerpt;
            [$queryVector] = $this->embed($model, [$query], $this->timeout());
            $scores = [];

            foreach ($passages as $id => $guide) {
                if ($task === null && ($guide['requires_task'] ?? false)) {
                    continue;
                }
                if ($fingerprint !== null && ($guide['reference']['essay_fingerprint'] ?? null) === $fingerprint) {
                    continue;
                }

                $vector = $this->unitVector($index['vectors'][$id] ?? null);
                if ($vector === null || count($vector) !== count($queryVector)) {
                    throw new RuntimeException('The essay guidance index contains an invalid vector. Run php artisan essay:rag-index.');
                }

                $score = 0.0;
                foreach ($vector as $position => $value) {
                    $score += $value * $queryVector[$position];
                }
                if ($score >= self::MIN_SIMILARITY) {
                    $scores[$id] = $score;
                }
            }

            arsort($scores, SORT_NUMERIC);

            $results = [];
            $addedReference = false;
            foreach (array_keys($scores) as $id) {
                $guide = $passages[$id];
                if ($addedReference && isset($guide['reference'])) {
                    continue;
                }
                $passage = ['id' => $id, 'title' => $guide['title'], 'text' => $guide['text']];
                if (isset($guide['reference'])) {
                    $passage['reference'] = $guide['reference'];
                    unset($passage['reference']['essay_fingerprint']);
                    $addedReference = true;
                }
                $results[] = $passage;
                if (count($results) === 3) {
                    break;
                }
            }

            return $results;
        } catch (JsonException|RuntimeException $exception) {
            Log::warning('Essay guidance retrieval skipped.', ['reason' => $exception->getMessage()]);

            return [];
        }
    }

    /** @return array<string, array{title: string, text: string, requires_task?: bool}> */
    private function guides(): array
    {
        return require resource_path('writing-guidance.php');
    }

    private function corpusVersion(array $guides): string
    {
        return hash('sha256', json_encode($guides, JSON_THROW_ON_ERROR));
    }

    private function timeout(): int
    {
        return max(1, (int) config('essay_retrieval.timeout'));
    }

    /** @param list<string> $inputs
     * @return list<list<float>>
     */
    private function embed(string $model, array $inputs, int $timeout): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout($timeout)
                ->post(rtrim(config('services.ollama.base_url'), '/').'/api/embed', [
                    'model' => $model,
                    'input' => $inputs,
                    'keep_alive' => '2m',
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The Ollama writing guidance embedding request could not connect or timed out.', 0, $exception);
        }

        if ($response->notFound()) {
            throw new RuntimeException('Install the configured Ollama embedding model: '.$model);
        }
        if (! $response->successful()) {
            throw new RuntimeException('Ollama embedding request failed (HTTP '.$response->status().').');
        }

        $vectors = $response->json('embeddings');
        if (! is_array($vectors) || ! array_is_list($vectors) || count($vectors) !== count($inputs)) {
            throw new RuntimeException('Ollama returned an unexpected number of embeddings.');
        }

        $normalized = [];
        foreach ($vectors as $vector) {
            $unit = $this->unitVector($vector);
            if ($unit === null || ($normalized !== [] && count($unit) !== count($normalized[0]))) {
                throw new RuntimeException('Ollama returned an invalid embedding vector.');
            }
            $normalized[] = $unit;
        }

        return $normalized;
    }

    /** @return list<float>|null */
    private function unitVector(mixed $vector): ?array
    {
        if (! is_array($vector) || ! array_is_list($vector) || $vector === []) {
            return null;
        }

        $squaredLength = 0.0;
        foreach ($vector as $value) {
            if (! is_int($value) && ! is_float($value)) {
                return null;
            }
            $squaredLength += $value * $value;
        }
        if ($squaredLength <= 0 || ! is_finite($squaredLength)) {
            return null;
        }

        $length = sqrt($squaredLength);

        return array_map(fn (int|float $value) => $value / $length, $vector);
    }
}
