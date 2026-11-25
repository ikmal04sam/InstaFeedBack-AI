<?php

namespace App\Services;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use SplFileObject;

class AnnotatedEssayReferences
{
    /** @return array<string, array{title: string, text: string, reference: array{essay_id: string, source_cefr: string, provisional_muet_band: string, essay_fingerprint: string}}> */
    public function load(): array
    {
        $path = config('essay_retrieval.reference_path');
        if (! filled($path)) {
            return [];
        }
        if (! is_file($path)) {
            throw new RuntimeException('The configured annotated essay reference file does not exist.');
        }

        $references = [];
        foreach (new SplFileObject($path) as $lineNumber => $line) {
            if (! is_string($line)) {
                continue;
            }
            $line = $lineNumber === 0 && str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
            if (trim($line) === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Invalid JSON on annotated essay reference line '.($lineNumber + 1).'.', 0, $exception);
            }

            $id = $record['essay_id'] ?? null;
            $messages = $record['messages'] ?? null;
            if (! is_string($id) || ! preg_match('/\A[A-Za-z0-9_-]+\z/', $id)
                || ! is_array($messages) || ($messages[1]['role'] ?? null) !== 'user' || ($messages[2]['role'] ?? null) !== 'assistant'
                || ! is_string($messages[1]['content'] ?? null) || ! is_string($messages[2]['content'] ?? null)
                || ! preg_match('/^Source CEFR label: ([ABC][12]\+?)\RExperimental mapped band: (Band [1-5]\.0\+?)\R\REssay:\R(.+)\z/msu', $messages[1]['content'], $source)) {
                throw new RuntimeException('Invalid annotated essay reference on line '.($lineNumber + 1).'.');
            }

            $example = $this->example($source[3], $messages[2]['content']);
            if ($example !== null) {
                $references['icle-'.$id] = [...$example, 'reference' => [
                    'essay_id' => $id,
                    'source_cefr' => $source[1],
                    'provisional_muet_band' => $source[2],
                    'essay_fingerprint' => $this->fingerprint($source[3]),
                ]];
            }
        }

        if ($references === []) {
            throw new RuntimeException('The annotated essay file contains no verified examples.');
        }

        return $references;
    }

    public function fingerprint(string $essay): string
    {
        return hash('sha256', $this->normalize($essay));
    }

    /** @return array{title: string, text: string}|null */
    private function example(string $essay, string $feedback): ?array
    {
        $fields = <<<'REGEX'
~^[ \t]*(?:[-*+][ \t]+)?(?:\*\*)?Original[ ]phrases?:(?:\*\*)?[ \t]*(?<quote>[^\r\n]+)\R(?:[ \t]*\R)*
[ \t]*(?:[-*+][ \t]+)?(?:\*\*)?Suggested[ ]corrections?:(?:\*\*)?[ \t]*(?<rewrite>[^\r\n]+)\R(?:[ \t]*\R)*
[ \t]*(?:[-*+][ \t]+)?(?:\*\*)?Explanation:(?:\*\*)?[ \t]*(?<detail>[^\r\n]+)~imux
REGEX;
        preg_match_all($fields, $feedback, $matches, PREG_SET_ORDER);
        $normalizedEssay = $this->normalize($essay);

        foreach ($matches as $match) {
            $quote = Str::squish(trim($match['quote'], " \t`*\""));
            $searchableQuote = trim($this->normalize($quote), ' ."');
            $rewrite = Str::squish(trim($match['rewrite'], " \t`*\""));
            $detail = Str::squish($match['detail']);

            if (mb_strlen($searchableQuote) < 12 || mb_strlen($quote) > 350 || ! str_contains($normalizedEssay, $searchableQuote)
                || $rewrite === '' || mb_strlen($rewrite) > 400 || $this->normalize($rewrite) === $this->normalize($quote)
                || mb_strlen($detail) < 20 || mb_strlen($detail) > 500 || str_ends_with($detail, ':')
                || preg_match('/\b(?:CEFR|MUET|Band\s+[1-5]\.0)\b/i', $quote.' '.$rewrite.' '.$detail)) {
                continue;
            }

            return [
                'title' => 'Annotated essay revision',
                'text' => "Reference excerpt: {$quote}\nSuggested revision: {$rewrite}\nExplanation: {$detail}",
            ];
        }

        return null;
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(Str::squish(strtr($text, ['‘' => "'", '’' => "'", '“' => '"', '”' => '"'])));
    }
}
