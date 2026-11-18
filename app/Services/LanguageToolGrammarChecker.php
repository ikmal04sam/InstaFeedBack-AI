<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class LanguageToolGrammarChecker
{
    /** @return array<string, mixed> */
    public function check(string $essay): array
    {
        $language = (string) config('services.languagetool.language', 'en-GB');

        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(5)
                ->timeout(max(5, (int) config('services.languagetool.timeout', 30)))
                ->post(rtrim(config('services.languagetool.base_url'), '/').'/v2/check', [
                    'text' => $essay,
                    'language' => $language,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Cannot reach LanguageTool or the grammar check timed out. Start the LanguageTool server and try again.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('LanguageTool could not check grammar (HTTP '.$response->status().'). Please try again.');
        }

        $result = $response->json();
        if (! is_array($result) || Validator::make($result, [
            'matches' => ['present', 'array', 'list'],
            'matches.*.message' => ['required', 'string'],
            'matches.*.offset' => ['required', 'integer', 'min:0'],
            'matches.*.length' => ['required', 'integer', 'min:0'],
            'matches.*.replacements' => ['present', 'array', 'list'],
            'matches.*.replacements.*.value' => ['present', 'string'],
            'matches.*.rule.id' => ['required', 'string'],
            'matches.*.rule.issueType' => ['sometimes', 'string'],
            'warnings.incompleteResults' => ['sometimes', 'boolean'],
        ])->fails() || ($result['warnings']['incompleteResults'] ?? false)) {
            throw new RuntimeException('LanguageTool returned invalid or incomplete grammar results. Please try again.');
        }

        // LanguageTool offsets use UTF-16 code units, including two units for emoji.
        $utf16 = mb_convert_encoding($essay, 'UTF-16LE', 'UTF-8');
        $issues = [];
        foreach ($result['matches'] as $match) {
            if (strlen($utf16) / 2 < $match['offset'] + $match['length']) {
                throw new RuntimeException('LanguageTool returned invalid grammar positions. Please try again.');
            }

            $issues[] = [
                'type' => $match['rule']['issueType'] ?? 'grammar',
                'message' => $match['message'],
                'rule_id' => $match['rule']['id'],
                'offset' => $match['offset'],
                'length' => $match['length'],
                'text' => mb_convert_encoding(substr($utf16, $match['offset'] * 2, $match['length'] * 2), 'UTF-8', 'UTF-16LE'),
                'replacements' => array_slice(array_column($match['replacements'], 'value'), 0, 5),
            ];
        }

        return ['source' => 'languagetool', 'language' => $language, 'issue_count' => count($issues), 'issues' => $issues];
    }
}
