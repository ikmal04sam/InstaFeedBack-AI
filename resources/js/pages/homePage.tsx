import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpenCheck, BrainCircuit, CheckCircle2, Clock3, FileText, Sparkles, Target } from 'lucide-react';

const myIcon = new URL('../../assets/logo.png', import.meta.url).href;

const feedbackPillars = [
    {
        icon: Target,
        title: 'Argument clarity',
        description: 'Pinpoint weak claims, missing evidence, and places where your reasoning needs a sharper line.',
    },
    {
        icon: BookOpenCheck,
        title: 'Writing quality',
        description: 'Get practical notes on structure, tone, transitions, grammar, and paragraph flow.',
    },
    {
        icon: BrainCircuit,
        title: 'Personalized guidance',
        description: 'Feedback adapts to your essay goal, level, rubric, and revision history.',
    },
];

const reviewSteps = ['Upload or paste your essay', 'Set the assignment rubric', 'Receive focused AI feedback', 'Revise with a clear action plan'];

export default function HomePage() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="InstaFeedBack AI">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
            </Head>

            <main className="min-h-screen bg-gray-300 text-[#17201c]">
                <header className="mx-auto flex w-full max-w-screen items-center justify-between px-6 py-5 lg:px-8 bg-gray-100 border-b border-[#acacab]">
                    <Link href={route('home')} className="flex items-center gap-3 font-semibold">
                        <img src={myIcon} alt="" className="size-10 rounded-md object-contain" />
                        <span className="text-lg">InstaFeedBack AI</span>
                    </Link>

                    <nav className="flex items-center gap-3 text-sm font-medium">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="inline-flex items-center gap-2 rounded-md bg-[#17201c] px-4 py-2 text-white transition hover:bg-[#2d4037]"
                            >
                                Dashboard
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="rounded-md px-4 py-2 text-[#31453b] transition hover:bg-white/70">
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="inline-flex items-center gap-2 rounded-md bg-[#17201c] px-4 py-2 text-white transition hover:bg-[#2d4037]"
                                >
                                    Get started
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <section className="mx-auto grid w-full max-w-7xl items-center gap-10 px-6 pt-8 pb-14 lg:grid-cols-[1fr_0.92fr] lg:px-8 lg:pt-16 lg:pb-20">
                    <div className="max-w-3xl">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d9d5c8] bg-white px-3 py-1 text-sm font-medium text-[#516458]">
                            <Clock3 className="size-4 text-[#c46b38]" aria-hidden="true" />
                            AI essay feedback in minutes
                        </div>
                        <img src={myIcon} alt="InstaFeedBack AI" className="size-50 rounded-md object-cover" />

                        <h1 className="max-w-4xl text-5xl leading-[1.02] font-semibold tracking-normal text-[#111815] md:text-6xl lg:text-7xl">
                            InstaFeedBack AI
                        </h1>
                        
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-[#516458] md:text-xl">
                            Personalized essay feedback that helps students understand what to improve, why it matters, and how to revise with
                            confidence.
                        </p>

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                href={auth.user ? route('dashboard') : route('register')}
                                className="inline-flex items-center justify-center gap-2 rounded-md bg-[#17201c] px-6 py-3 text-base font-semibold text-white transition hover:bg-[#2d4037]"
                            >
                                Review an essay
                                <ArrowRight className="size-5" aria-hidden="true" />
                            </Link>
                            <Link
                                href={auth.user ? route('dashboard') : route('login')}
                                className="inline-flex items-center justify-center rounded-md border border-[#cfc9b8] bg-white px-6 py-3 text-base font-semibold text-[#17201c] transition hover:border-[#a9a18e]"
                            >
                                View dashboard
                            </Link>
                        </div>

                        <div className="mt-10 grid max-w-xl grid-cols-3 gap-4 border-4 border-[#5d5b5b] py-5 p-4 rounded-2xl">
                            <div className= "border-2 rounded-md p-2 place-content-center bg-gray-100">
                                <p className="text-2xl font-semibold">4x</p>
                                <p className="mt-1 text-base text-[#65766c]">Faster Review Cycles</p>
                            </div>
                            <div className= "border-2 rounded-md p-2 place-content-center bg-gray-100">
                                <p className="text-2xl font-semibold">24/7</p>
                                <p className="mt-1 text-base text-[#65766c]">Revision Support</p>
                            </div>
                            <div className= "border-2 rounded-md p-2 place-content-center bg-gray-100">
                                <p className="text-2xl font-semibold">Rubric</p>
                                <p className="mt-1 text-base text-[#65766c]">Aligned Feedback</p>
                            </div>
                        </div>
                    </div>

                    <div className="relative">
                        <div className="absolute top-8 -left-4 h-32 w-32 rounded-full bg-[#f1c84b]/55 blur-3xl" />
                        <div className="absolute -right-4 bottom-10 h-36 w-36 rounded-full bg-[#5ba891]/45 blur-3xl" />

                        <div className="relative overflow-hidden rounded-lg border border-[#d6d0bf] bg-[#fffdf8] shadow-2xl shadow-[#17201c]/10">
                            <div className="flex items-center justify-between border-b border-[#ebe5d5] px-5 py-4">
                                <div className="flex items-center gap-2">
                                    <span className="size-3 rounded-full bg-[#e85d4f]" />
                                    <span className="size-3 rounded-full bg-[#f3b23d]" />
                                    <span className="size-3 rounded-full bg-[#5ba891]" />
                                </div>
                                <span className="text-sm font-medium text-[#65766c]">Essay Review</span>
                            </div>

                            <div className="grid gap-0 md:grid-cols-[1.05fr_0.95fr]">
                                <div className="border-b border-[#ebe5d5] p-5 md:border-r md:border-b-0">
                                    <div className="mb-4 flex items-center gap-2 text-sm font-semibold text-[#17201c]">
                                        <FileText className="size-4 text-[#c46b38]" aria-hidden="true" />
                                        Draft excerpt
                                    </div>
                                    <div className="space-y-3 text-sm leading-6 text-[#425249]">
                                        <p>
                                            The essay argues that online learning can improve access, but the introduction needs a clearer position
                                            and stronger context.
                                        </p>
                                        <p className="rounded-md border-l-4 border-[#c46b38] bg-[#f8efe3] p-3">
                                            Add a thesis that connects access, flexibility, and measurable learning outcomes.
                                        </p>
                                        <p>
                                            Body paragraph two includes evidence, but the source is introduced late. Move the evidence earlier and
                                            explain its relevance.
                                        </p>
                                    </div>
                                </div>

                                <div className="bg-[#17201c] p-5 text-white">
                                    <div className="mb-4 flex items-center gap-2 text-sm font-semibold">
                                        <Sparkles className="size-4 text-[#f1c84b]" aria-hidden="true" />
                                        AI feedback
                                    </div>
                                    <div className="space-y-3">
                                        {[
                                            'Clarify the thesis in one sentence.',
                                            'Add topic sentences to guide each paragraph.',
                                            'Explain how each source supports your claim.',
                                            'End with a stronger reflection, not a summary.',
                                        ].map((item) => (
                                            <div key={item} className="flex gap-3 rounded-md bg-white/8 p-3 text-sm leading-5">
                                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-[#77d0b4]" aria-hidden="true" />
                                                <span>{item}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="bg-white">
                    <div className="mx-auto grid w-full max-w-7xl gap-8 px-6 py-14 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
                        <div>
                            <p className="text-sm font-semibold tracking-[0.18em] text-[#c46b38] uppercase">Feedback that teaches</p>
                            <h2 className="mt-3 text-3xl font-semibold text-[#17201c] md:text-4xl">
                                Built for better revisions, not just better scores.
                            </h2>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            {feedbackPillars.map((pillar) => {
                                const Icon = pillar.icon;

                                return (
                                    <article key={pillar.title} className="rounded-lg border border-[#e5dfcf] bg-[#fffdf8] p-5">
                                        <Icon className="size-7 text-[#2d806a]" aria-hidden="true" />
                                        <h3 className="mt-5 text-lg font-semibold text-[#17201c]">{pillar.title}</h3>
                                        <p className="mt-3 text-sm leading-6 text-[#65766c]">{pillar.description}</p>
                                    </article>
                                );
                            })}
                        </div>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-7xl px-6 py-14 lg:px-8">
                    <div className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
                        <div>
                            <p className="text-sm font-semibold tracking-[0.18em] text-[#2d806a] uppercase">How it works</p>
                            <h2 className="mt-3 text-3xl font-semibold text-[#17201c] md:text-4xl">
                                A simple path from first draft to confident submission.
                            </h2>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {reviewSteps.map((step, index) => (
                                <div key={step} className="flex items-center gap-4 rounded-lg border border-[#d9d5c8] bg-white p-4">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-[#f1c84b] text-sm font-bold text-[#17201c]">
                                        {index + 1}
                                    </span>
                                    <span className="font-medium text-[#31453b]">{step}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
