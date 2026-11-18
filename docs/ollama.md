# Ollama essay feedback

Laravel first sends the original essay to `POST {LANGUAGETOOL_BASE_URL}/v2/check`. It then sends the original essay and grammar evidence to `POST {OLLAMA_BASE_URL}/api/chat`. Ollama returns rubric component marks, vocabulary and structure feedback, suggestions, and an estimated CEFR writing band with an explanation. Responses are validated before the result is stored with the essay in PostgreSQL.

LanguageTool suggestions are saved separately from AI feedback in `analysis.grammar`. The prompt includes at most 20 findings with bounded text and an omitted count to leave room for the essay; all findings remain available in the saved grammar result. LanguageTool suggestions can be false positives, so Ollama is instructed to consider their context. PHP only computes descriptive word and sentence statistics.

Set these values in `.env`:

```dotenv
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.1:8b
OLLAMA_TIMEOUT=180
LANGUAGETOOL_BASE_URL=http://127.0.0.1:8081
LANGUAGETOOL_LANGUAGE=en-GB
LANGUAGETOOL_TIMEOUT=30
```

Keep the Ollama app running and use `ollama list` to see installed models. Set `OLLAMA_MODEL` to an installed model's exact name. This machine already has `muet-grader:latest` and `llama3.1:8b`. The default is now `llama3.1:8b`: it returned consistent structured CEFR feedback in the live integration check, while the custom model returned contradictory band and explanation values. Conflicting CEFR labels are rejected instead of saved. Model installation and names may differ on another computer.

LanguageTool 6.6 is installed locally in `storage/app/languagetool/LanguageTool-6.6` (ignored by Git). Start it from the project root after restarting your computer:

```powershell
java -Xmx1g -cp storage/app/languagetool/LanguageTool-6.6/languagetool-server.jar org.languagetool.server.HTTPServer --port 8081
```

Keep that terminal open alongside Ollama. On another computer, download and extract the [LanguageTool server](https://dev.languagetool.org/http-server) and adjust the JAR path. Java is required. The server listens locally; Laravel makes the HTTP calls, so browser CORS access is unnecessary. British English is the default; use `en-US` for American English.

After configuration changes, run `php artisan config:clear`. A LanguageTool connection failure, incomplete grammar response, missing Ollama model, timeout, or invalid AI response displays an error without saving a result. There is no silent fallback to unchecked grammar. The PHP application must be able to reach both configured servers. With the default URLs, essays stay on this computer. The public LanguageTool API is not used: its [usage policy](https://dev.languagetool.org/public-http-api.html) directs automated integrations to self-hosted or enterprise services.

The estimated CEFR writing level (A1, A2, B1, B2, C1, C2) stays independent of rubric marks and task compliance. This is an AI estimate of one writing sample, not an official CEFR certification. The existing CEFR field and explanation remain available; old records without one show "Not assessed".

The current rubric is based on the image supplied on 10 November 2026. The user selected equal weights: Task Fulfilment 20, Language 20, Organisation 20, for a total of 60. `config/essay_rubric.php` contains the adapted descriptors and version. Each component receives an integer mark and evidence-based revision guidance. The server adds component marks; it does not trust a model-generated overall total or apply the previous extra 30-mark deduction.

The image's 30-point descriptor thresholds are proportionally rescaled to a 20-point integer scale. Each lower threshold is multiplied by 20/30 and rounded up; upper endpoints stop just before the next threshold:

| Image descriptor range | Component marks out of 20 |
| --- | --- |
| 27-30 | 18-20 |
| 23-26 | 16-17 |
| 19-22 | 13-15 |
| 15-18 | 10-12 |
| 11-14 | 8-9 |
| 0-10 | 0-7 |

The sheet's total ranges remain 51-60, 41-50, 31-40, 21-30, 11-20, and 0-10. They apply only to a complete score out of 60. LanguageTool flags inform the assessment; their count is not a fixed mark deduction.

The question/task box remains optional (maximum 3,000 characters). With a task, Task Fulfilment considers relevance, required content, viewpoints, and assessable instructions. Task text is a reference, not instructions that can override evaluation. Only the essay is sent to LanguageTool. Without a task, Task Fulfilment is not assessed or given automatic marks: Language and Organisation produce a clearly labelled provisional score out of 40, with no full-rubric total band.

Run `php artisan migrate` after updating. `essays.score_max` defaults to 100 to preserve saved results from the previous scheme; new results use 60 or 40. Scores are stored in their native units. `analysis.rubric` stores version, component marks, explanations, provisional status, and the applicable total band. Legacy task-deduction records retain their old display. Historical essays are not automatically regraded.

Dashboard summaries and bar heights use percentages (`score / score_max * 100`); individual results show their native denominator. Provisional results are included and labelled. Percentages do not make assessments from different rubrics directly equivalent.
