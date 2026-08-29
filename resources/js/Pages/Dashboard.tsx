import { Head } from '@inertiajs/react';
import { Bot, Database, Gauge, LibraryBig } from 'lucide-react';

const cards = [
    {
        title: 'Content model',
        description: 'Scenarios, scenes, characters, goals, and localized copy will live here.',
        icon: LibraryBig,
    },
    {
        title: 'AI agents',
        description: 'Laravel AI SDK will orchestrate transcription, grading, hints, and TTS.',
        icon: Bot,
    },
    {
        title: 'Operational data',
        description: 'MySQL owns users, progress, attempts, and publishing workflow state.',
        icon: Database,
    },
    {
        title: 'Fast delivery',
        description: 'Redis backs cache, queues, rate limits, and frequently served published content.',
        icon: Gauge,
    },
];

export default function Dashboard() {
    return (
        <>
            <Head title="CMS Dashboard" />
            <main className="min-h-screen bg-slate-50 text-slate-950">
                <section className="mx-auto w-full max-w-6xl px-6 py-10">
                    <div className="flex flex-col gap-2 border-b border-slate-200 pb-6">
                        <p className="text-sm font-semibold uppercase tracking-wide text-blue-700">
                            Kalbek CMS
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Content and AI operations
                        </h1>
                        <p className="max-w-2xl text-sm leading-6 text-slate-600">
                            This Inertia React surface will manage the backend source of truth for
                            learner-facing scenarios, characters, AI prompts, and publishing state.
                        </p>
                    </div>

                    <div className="mt-8 grid gap-4 md:grid-cols-2">
                        {cards.map((card) => {
                            const Icon = card.icon;

                            return (
                                <article
                                    key={card.title}
                                    className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div className="flex items-start gap-4">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                                            <Icon className="size-5" />
                                        </span>
                                        <div>
                                            <h2 className="font-semibold">{card.title}</h2>
                                            <p className="mt-1 text-sm leading-6 text-slate-600">
                                                {card.description}
                                            </p>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </section>
            </main>
        </>
    );
}
