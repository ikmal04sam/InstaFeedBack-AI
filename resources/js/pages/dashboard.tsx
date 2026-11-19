import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    BarChart3,
    BookOpenText,
    BrainCircuit,
    CheckCircle2,
    FileText,
    History,
    Lightbulb,
    ListChecks,
    LoaderCircle,
    Send,
    Sparkles,
    TrendingUp,
} from 'lucide-react';
import { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type SectionKey = 'overview' | 'essay-review' | 'history';

type EssayResult = {
    id: number;
    content: string;
    task?: string | null;
    excerpt: string;
    word_count: number;
    sentence_count: number;
    score: number;
    score_max?: number;
    score_percentage?: number;
    level: 'Weak' | 'Average' | 'Good';
    analysis: {
        source?: string;
        model?: string;
        rubric?: {
            version: string;
            name: string;
            provisional: boolean;
            total: number;
            maximum: number;
            total_band: string | null;
            components: Record<string, { name: string; score: number; maximum: number; band: string; feedback: string }>;
        };
        task_review?: {
            status: 'met' | 'partially_met' | 'not_met';
            base_score: number;
            requested_deduction: number;
            deduction: number;
            feedback: string[];
        };
        cefr?: {
            level: 'A1' | 'A2' | 'B1' | 'B2' | 'C1' | 'C2';
            explanation: string;
            strength?: { quote: string; detail: string };
            limitation?: { quote: string; detail: string; rewrite: string };
        };
        grammar: {
            source?: string;
            issue_count: number;
            issues?: { type: string; message: string; text?: string; replacements?: string[] }[];
        };
        vocabulary: {
            unique_words: number;
            lexical_diversity: number;
            repetition_rate?: number;
            repeated_words?: string[];
            feedback?: string[];
        };
        structure: {
            average_sentence_length?: number;
            paragraph_count?: number;
            transition_count?: number;
            issues?: string[];
            feedback?: string[];
        };
    };
    feedback: string[];
    suggestions: string[];
    created_at: string;
};

type DashboardProps = {
    essays?: EssayResult[];
    selectedEssay?: EssayResult | null;
    dashboardSummary?: DashboardSummary;
};

type DashboardSummary = {
    total_essays: number;
    average_score: number | null;
    best_score: number | null;
    latest_score: number | null;
    previous_score: number | null;
    progress_change: number;
    progress_label: string;
    score_history: Pick<EssayResult, 'id' | 'score' | 'score_max' | 'score_percentage' | 'level' | 'created_at'>[];
    recent_submissions: EssayResult[];
};

function getSectionKey(url: string): SectionKey {
    const query = url.split('?')[1] ?? '';
    const section = new URLSearchParams(query).get('section') as SectionKey | null;

    return section === 'essay-review' || section === 'history' ? section : 'overview';
}

function levelStyles(level: EssayResult['level']) {
    if (level === 'Good') {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }

    if (level === 'Average') {
        return 'bg-amber-100 text-amber-800 border-amber-200';
    }

    return 'bg-rose-100 text-rose-700 border-rose-200';
}

function formatEssayScore(essay: { score: number; score_max?: number }) {
    return `${essay.score}/${essay.score_max ?? 100}`;
}

function scorePercentage(essay: { score: number; score_max?: number; score_percentage?: number }) {
    return essay.score_percentage ?? (essay.score / (essay.score_max ?? 100)) * 100;
}

/*({ essay }): This Component expects to be handed some data (called "props" in React). It is asking for the essay data.

: { essay: EssayResult | null }: This is the TypeScript rule. It says, "The essay data you hand me must either perfectly match our EssayResult blueprint, OR it can be null (completely empty/non-existent)."
*/
function ResultPanel({ essay }: { essay: EssayResult | null }) {
    if (!essay) {
        return (
            <section className="bg-card rounded-lg border p-5 shadow-sm">
                <div className="flex items-center gap-2">
                    <BrainCircuit className="size-5 text-rose-700" aria-hidden="true" />
                    <h2 className="text-lg font-semibold">Analysis result</h2>
                </div>
                <div className="text-muted-foreground mt-5 rounded-lg border border-dashed p-5 text-sm leading-6">
                    Submit an essay to generate grammar, vocabulary, structure, score, and feedback results.
                </div>
            </section>
        );
    }

    return (
        <section className="bg-card rounded-lg border p-5 shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <BrainCircuit className="size-5 text-sky-600" aria-hidden="true" />
                        <h2 className="text-lg font-semibold">Analysis result</h2>
                    </div>
                    <p className="text-muted-foreground mt-1 text-sm">Reviewed {essay.created_at}</p>
                    {essay.analysis.source === 'ollama' && (
                        <p className="text-muted-foreground mt-1 text-sm">Feedback by Ollama ({essay.analysis.model})</p>
                    )}
                </div>
                <div className={`w-fit rounded-md border px-3 py-2 text-sm font-semibold ${levelStyles(essay.level)}`}>
                    {formatEssayScore(essay)} · {essay.analysis.rubric?.provisional ? 'Provisional' : essay.level}
                </div>
            </div>

            {essay.analysis.rubric ? (
                <div className="bg-background mt-5 space-y-4 rounded-lg border p-4">
                    <h3 className="font-semibold">{essay.analysis.rubric.name}</h3>
                    <p className="text-muted-foreground text-sm">
                        {essay.analysis.rubric.provisional
                            ? 'Provisional writing score out of 40. No question/task was provided, so Task Fulfilment is not assessed.'
                            : `Total out of 60 · Rubric total band ${essay.analysis.rubric.total_band}. Each component is worth 20 marks.`}
                    </p>
                    {essay.task && (
                        <details>
                            <summary className="cursor-pointer text-sm font-medium">View submitted question/task</summary>
                            <p className="text-muted-foreground mt-2 text-sm leading-6 break-words whitespace-pre-wrap">{essay.task}</p>
                        </details>
                    )}
                    <div className="grid gap-3 lg:grid-cols-3">
                        {essay.analysis.rubric.provisional && (
                            <AnalysisCard
                                icon={ListChecks}
                                title="Task Fulfilment · Not assessed"
                                items={['Add a question/task to receive a complete score out of 60.']}
                            />
                        )}
                        {Object.entries(essay.analysis.rubric.components).map(([key, component]) => (
                            <AnalysisCard
                                key={key}
                                icon={ListChecks}
                                title={`${component.name} · ${component.score}/${component.maximum}`}
                                items={[component.feedback]}
                            />
                        ))}
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Based on your uploaded rubric, with its descriptor ranges rescaled to 20 marks per component.
                    </p>
                </div>
            ) : essay.task && essay.analysis.task_review ? (
                <div className="bg-background mt-5 space-y-4 rounded-lg border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-semibold">Task requirements</h3>
                        <span className="bg-secondary text-secondary-foreground rounded-md px-3 py-1 text-sm font-medium">
                            {essay.analysis.task_review.status === 'met'
                                ? 'Met'
                                : essay.analysis.task_review.status === 'partially_met'
                                  ? 'Partly met'
                                  : 'Not met'}
                        </span>
                    </div>
                    <details>
                        <summary className="cursor-pointer text-sm font-medium">View submitted question/task</summary>
                        <p className="text-muted-foreground mt-2 text-sm leading-6 break-words whitespace-pre-wrap">{essay.task}</p>
                    </details>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Metric label="Writing score" value={`${essay.analysis.task_review.base_score}/100`} />
                        <Metric label="Task deduction" value={`−${essay.analysis.task_review.deduction}`} />
                        <Metric label="Final score" value={`${essay.score}/100`} />
                    </div>
                    {essay.analysis.task_review.requested_deduction > essay.analysis.task_review.deduction && (
                        <p className="text-muted-foreground text-xs">
                            The assessed deduction was {essay.analysis.task_review.requested_deduction} marks. The applied deduction is limited so the
                            final score stays at zero.
                        </p>
                    )}
                    <FeedbackList title="Task feedback and guidance" icon={ListChecks} items={essay.analysis.task_review.feedback} />
                </div>
            ) : (
                <p className="text-muted-foreground mt-5 text-sm">Saved using the previous 100-point marking scheme.</p>
            )}

            <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Metric label="Words" value={essay.word_count} />
                <Metric label="Sentences" value={essay.sentence_count} />
                <Metric label="Lexical diversity" value={`${essay.analysis.vocabulary.lexical_diversity}%`} />
                <Metric label="Estimated CEFR band" value={essay.analysis.cefr?.level ?? 'Not assessed'} />
            </div>

            {essay.analysis.cefr && (
                <div className="bg-background mt-5 rounded-lg border p-4">
                    <h3 className="font-semibold">CEFR writing assessment</h3>
                    <p className="text-muted-foreground mt-2 text-sm leading-6">{essay.analysis.cefr.explanation}</p>
                    {(essay.analysis.cefr.strength || essay.analysis.cefr.limitation) && (
                        <dl className="mt-3 space-y-3 text-sm leading-6">
                            {essay.analysis.cefr.strength && (
                                <div>
                                    <dt className="font-medium">What works</dt>
                                    <dd className="text-muted-foreground break-words">
                                        <q>{essay.analysis.cefr.strength.quote}</q> {essay.analysis.cefr.strength.detail}
                                    </dd>
                                </div>
                            )}
                            {essay.analysis.cefr.limitation && (
                                <>
                                    <div>
                                        <dt className="font-medium">What to improve</dt>
                                        <dd className="text-muted-foreground break-words">
                                            <q>{essay.analysis.cefr.limitation.quote}</q> {essay.analysis.cefr.limitation.detail}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">Suggested rewrite</dt>
                                        <dd className="text-muted-foreground break-words">{essay.analysis.cefr.limitation.rewrite}</dd>
                                    </div>
                                </>
                            )}
                        </dl>
                    )}
                    <p className="text-muted-foreground mt-2 text-xs">AI estimate based on this essay, not an official CEFR certification.</p>
                </div>
            )}

            <div className="mt-5 grid gap-4 lg:grid-cols-3">
                <AnalysisCard
                    icon={AlertCircle}
                    title={essay.analysis.grammar.source === 'languagetool' ? 'Grammar · LanguageTool' : 'Grammar'}
                    items={
                        (essay.analysis.grammar.issues?.length ?? 0) > 0
                            ? essay.analysis.grammar.issues!.map(
                                  (issue) =>
                                      `${issue.text ? `“${issue.text}”: ` : `${issue.type}: `}${issue.message}${
                                          issue.replacements?.length ? ` Suggested: ${issue.replacements.join(', ')}.` : ''
                                      }`,
                              )
                            : ['No issues flagged by the grammar checker. This does not guarantee error-free writing.']
                    }
                />
                <AnalysisCard
                    icon={BarChart3}
                    title="Vocabulary"
                    items={
                        essay.analysis.vocabulary.feedback ?? [
                            `${essay.analysis.vocabulary.unique_words} unique words detected.`,
                            (essay.analysis.vocabulary.repeated_words?.length ?? 0) > 0
                                ? `Repeated words: ${essay.analysis.vocabulary.repeated_words?.join(', ')}.`
                                : 'No strong repetition pattern detected.',
                        ]
                    }
                />
                <AnalysisCard
                    icon={ListChecks}
                    title="Structure"
                    items={
                        essay.analysis.structure.feedback ??
                        ((essay.analysis.structure.issues?.length ?? 0) > 0
                            ? essay.analysis.structure.issues!
                            : [`Average sentence length is ${essay.analysis.structure.average_sentence_length} words.`])
                    }
                />
            </div>

            <div className="mt-5 grid gap-4 lg:grid-cols-2">
                <FeedbackList title="Personalized feedback" icon={Sparkles} items={essay.feedback} />
                <FeedbackList title="Improvement suggestions" icon={Lightbulb} items={essay.suggestions} />
            </div>
        </section>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="bg-background rounded-lg border p-4">
            <p className="text-2xl font-semibold">{value}</p>
            <p className="text-muted-foreground mt-1 text-sm">{label}</p>
        </div>
    );
}

function AnalysisCard({ icon: Icon, title, items }: { icon: typeof AlertCircle; title: string; items: string[] }) {
    return (
        <div className="bg-background rounded-lg border p-4">
            <div className="flex items-center gap-2">
                <Icon className="text-primary size-4" aria-hidden="true" />
                <h3 className="font-semibold">{title}</h3>
            </div>
            <div className="mt-4 space-y-2">
                {items.map((item) => (
                    <p key={item} className="text-muted-foreground text-sm leading-6 break-words">
                        {item}
                    </p>
                ))}
            </div>
        </div>
    );
}

function FeedbackList({ title, icon: Icon, items }: { title: string; icon: typeof Sparkles; items: string[] }) {
    return (
        <div className="bg-background rounded-lg border p-4">
            <div className="flex items-center gap-2">
                <Icon className="size-4 text-emerald-600" aria-hidden="true" />
                <h3 className="font-semibold">{title}</h3>
            </div>
            <div className="mt-4 space-y-3">
                {items.map((item) => (
                    <div key={item} className="flex gap-3 text-sm leading-6">
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" aria-hidden="true" />
                        <span>{item}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DashboardOverview({ summary }: { summary: DashboardSummary }) {
    const scoreHistory = summary.score_history.slice(-6);
    const progressValue = summary.progress_change > 0 ? `+${summary.progress_change}` : summary.progress_change;

    return (
        <div className="grid gap-5 xl:grid-cols-[1fr_0.9fr]">
            <section className="bg-card rounded-lg border p-5 shadow-sm">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-5 text-emerald-600" aria-hidden="true" />
                    <h2 className="text-lg font-semibold">Performance summary</h2>
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                    <Metric label="Average score" value={summary.average_score !== null ? `${summary.average_score}%` : 'None'} />
                    <Metric label="Best score" value={summary.best_score !== null ? `${summary.best_score}%` : 'None'} />
                    <Metric label="Progress" value={summary.total_essays > 1 ? `${progressValue} pp` : 'Pending'} />
                </div>

                <p className="text-muted-foreground mt-3 text-xs">
                    Summary scores use percentages, including provisional results. Different rubric versions are not directly equivalent.
                </p>

                <div className="bg-background mt-5 rounded-lg border p-4">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <h3 className="font-semibold">Progress monitoring</h3>
                        <span className="bg-secondary text-secondary-foreground w-fit rounded-md px-3 py-1 text-xs font-semibold">
                            {summary.progress_label}
                        </span>
                    </div>

                    {scoreHistory.length === 0 ? (
                        <p className="text-muted-foreground mt-4 text-sm leading-6">Submit essays to build a visible score history.</p>
                    ) : (
                        <div className="mt-4 flex h-36 items-end gap-3">
                            {scoreHistory.map((item) => (
                                <div key={item.id} className="flex min-w-12 flex-1 flex-col items-center gap-2">
                                    <div className="bg-muted flex h-24 w-full items-end rounded-md">
                                        <div
                                            className="bg-primary w-full rounded-md"
                                            style={{ height: `${scorePercentage(item)}%` }}
                                            aria-label={`Essay ${item.id} score ${formatEssayScore(item)}`}
                                        />
                                    </div>
                                    <span className="text-xs font-semibold">{formatEssayScore(item)}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            <section className="bg-card rounded-lg border p-5 shadow-sm">
                <div className="flex items-center gap-2">
                    <History className="size-5 text-indigo-600" aria-hidden="true" />
                    <h2 className="text-lg font-semibold">Recent submissions</h2>
                </div>

                {summary.recent_submissions.length === 0 ? (
                    <div className="text-muted-foreground mt-5 rounded-lg border border-dashed p-5 text-sm leading-6">
                        No essay records yet. Submit an essay to create your first stored history item.
                    </div>
                ) : (
                    <div className="mt-5 space-y-3">
                        {summary.recent_submissions.map((essay) => (
                            <Link
                                key={essay.id}
                                href={`/dashboard?section=history&essay=${essay.id}`}
                                className="bg-background hover:border-primary/50 block rounded-lg border p-4 transition"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold">Essay #{essay.id}</h3>
                                        <p className="text-muted-foreground mt-1 text-xs">{essay.created_at}</p>
                                    </div>
                                    <span className={`rounded-full border px-3 py-1 text-xs font-semibold ${levelStyles(essay.level)}`}>
                                        {formatEssayScore(essay)}
                                    </span>
                                </div>
                                <p className="text-muted-foreground mt-3 text-sm leading-6">{essay.excerpt}</p>
                            </Link>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

export default function Dashboard({ essays = [], selectedEssay = null, dashboardSummary }: DashboardProps) {
    const page = usePage();
    const sectionKey = getSectionKey(page.url);
    const summary =
        dashboardSummary ??
        ({
            total_essays: essays.length,
            average_score: null,
            best_score: null,
            latest_score: selectedEssay ? scorePercentage(selectedEssay) : null,
            previous_score: null,
            progress_change: 0,
            progress_label: 'Need more submissions',
            score_history: [],
            recent_submissions: essays.slice(0, 5),
        } satisfies DashboardSummary);
    const { data, setData, post, processing, errors } = useForm({
        content: selectedEssay?.content ?? '',
        task: selectedEssay?.task ?? '',
    });

    function submitEssay(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/essays', {
            preserveScroll: true,
            onSuccess: () => setData({ content: '', task: '' }),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Essay Feedback Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-5 p-4">
                <section className="bg-card text-card-foreground rounded-lg border p-5 shadow-sm">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div className="max-w-3xl">
                            <div className="text-muted-foreground mb-3 flex items-center gap-2 text-sm font-semibold">
                                <FileText className="size-4 text-sky-600" aria-hidden="true" />
                                <span>Modules 6 and 7 enabled</span>
                            </div>
                            <h1 className="text-3xl font-semibold tracking-normal md:text-4xl">Essay storage and progress dashboard</h1>
                            <p className="text-muted-foreground mt-3 max-w-2xl text-base leading-7">
                                Store submitted essays, keep generated feedback linked to each record, and monitor writing improvement over time.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Link
                                href="/dashboard?section=essay-review"
                                className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold"
                            >
                                <Send className="size-4" aria-hidden="true" />
                                Analyze essay
                            </Link>
                            <Link
                                href="/dashboard?section=history"
                                className="bg-secondary text-secondary-foreground inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold"
                            >
                                <History className="size-4" aria-hidden="true" />
                                View history
                            </Link>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-3 md:grid-cols-3">
                        <Metric label="Saved essays" value={essays.length} />
                        <Metric label="Average score" value={summary.average_score !== null ? `${summary.average_score}%` : 'None'} />
                        <Metric label="Latest score" value={summary.latest_score !== null ? `${summary.latest_score}%` : 'None'} />
                    </div>
                </section>

                {sectionKey === 'overview' && <DashboardOverview summary={summary} />}

                {sectionKey === 'essay-review' && (
                    <div className="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
                        <section className="bg-card rounded-lg border p-5 shadow-sm">
                            <div className="flex items-center gap-2">
                                <BookOpenText className="size-5 text-emerald-600" aria-hidden="true" />
                                <h2 className="text-lg font-semibold">Essay submission</h2>
                            </div>
                            <form onSubmit={submitEssay} className="mt-5 space-y-4">
                                <p className="text-muted-foreground text-sm leading-6">
                                    Get LanguageTool grammar suggestions, personalized feedback, and an estimated CEFR writing band from A1 to C2.
                                </p>
                                <div className="space-y-2">
                                    <label htmlFor="essay-task" className="block text-sm font-semibold">
                                        Question/task and guidance (optional)
                                    </label>
                                    <textarea
                                        id="essay-task"
                                        name="task"
                                        value={data.task}
                                        onChange={(event) => setData('task', event.target.value)}
                                        maxLength={3000}
                                        aria-describedby="essay-task-help"
                                        aria-invalid={Boolean(errors.task)}
                                        className="bg-background focus:border-primary min-h-32 w-full resize-y rounded-lg border p-4 text-sm leading-6 transition outline-none"
                                        placeholder="Paste the question, required points, word limit, or marking guidance here..."
                                    />
                                    <p id="essay-task-help" className="text-muted-foreground text-xs leading-5">
                                        With a task: Task Fulfilment, Language, and Organisation are each marked out of 20 (60 total). Leave blank for
                                        a provisional Language and Organisation score out of 40. Maximum 3,000 characters.
                                    </p>
                                    {errors.task && (
                                        <p role="alert" className="text-sm font-medium text-rose-600">
                                            {errors.task}
                                        </p>
                                    )}
                                </div>
                                <label htmlFor="essay-content" className="block text-sm font-semibold">
                                    Your essay
                                </label>
                                <textarea
                                    id="essay-content"
                                    name="content"
                                    value={data.content}
                                    onChange={(event) => setData('content', event.target.value)}
                                    className="bg-background focus:border-primary min-h-72 w-full resize-y rounded-lg border p-4 text-sm leading-6 transition outline-none"
                                    placeholder="Paste your essay here..."
                                />
                                {errors.content && <p className="text-sm font-medium text-rose-600">{errors.content}</p>}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {processing ? (
                                        <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
                                    ) : (
                                        <Send className="size-4" aria-hidden="true" />
                                    )}
                                    Submit for analysis
                                </button>
                            </form>
                        </section>

                        <ResultPanel essay={selectedEssay} />
                    </div>
                )}

                {sectionKey === 'history' && (
                    <section className="bg-card rounded-lg border p-5 shadow-sm">
                        <div className="flex items-center gap-2">
                            <History className="size-5 text-indigo-600" aria-hidden="true" />
                            <h2 className="text-lg font-semibold">Essay history</h2>
                        </div>

                        {essays.length === 0 ? (
                            <div className="text-muted-foreground mt-5 rounded-lg border border-dashed p-5 text-sm leading-6">
                                No saved essay submissions yet. Analyze one essay to create history.
                            </div>
                        ) : (
                            <div className="mt-5 grid gap-5 xl:grid-cols-[0.85fr_1.15fr]">
                                <div className="grid gap-3">
                                    {essays.map((essay) => {
                                        const isSelected = essay.id === selectedEssay?.id;

                                        return (
                                            <Link
                                                key={essay.id}
                                                href={`/dashboard?section=history&essay=${essay.id}`}
                                                className={`hover:border-primary/50 rounded-lg border p-4 transition ${
                                                    isSelected ? 'bg-primary text-primary-foreground' : 'bg-background'
                                                }`}
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <h3 className="font-semibold">Essay #{essay.id}</h3>
                                                        <p
                                                            className={`mt-1 text-xs ${isSelected ? 'text-primary-foreground/75' : 'text-muted-foreground'}`}
                                                        >
                                                            {essay.created_at}
                                                        </p>
                                                    </div>
                                                    <span
                                                        className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                            isSelected ? 'bg-primary-foreground text-primary' : levelStyles(essay.level)
                                                        }`}
                                                    >
                                                        {formatEssayScore(essay)}
                                                    </span>
                                                </div>
                                                <p
                                                    className={`mt-3 text-sm leading-6 ${isSelected ? 'text-primary-foreground/80' : 'text-muted-foreground'}`}
                                                >
                                                    {essay.excerpt}
                                                </p>
                                            </Link>
                                        );
                                    })}
                                </div>

                                <ResultPanel essay={selectedEssay} />
                            </div>
                        )}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
