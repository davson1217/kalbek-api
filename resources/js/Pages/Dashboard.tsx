import { Head, Link } from '@inertiajs/react';
import { BookOpen, MessageSquareText, Mic, Target, UsersRound } from 'lucide-react';

import { CmsLayout } from '../Layouts/CmsLayout';
import type { ScenarioSummary } from '../types';

interface Props {
    stats: {
        scenarios: number;
        characters: number;
        goals: number;
        npcLines: number;
        speakingAttempts: number;
    };
    recentScenarios: ScenarioSummary[];
}

export default function Dashboard({ stats, recentScenarios }: Props) {
    const cards = [
        { label: 'Scenarios', value: stats.scenarios, icon: BookOpen },
        { label: 'Characters', value: stats.characters, icon: UsersRound },
        { label: 'Goals', value: stats.goals, icon: Target },
        { label: 'Character replies', value: stats.npcLines, icon: MessageSquareText },
        { label: 'Speaking attempts', value: stats.speakingAttempts, icon: Mic },
    ];

    return (
        <CmsLayout>
            <Head title="CMS Dashboard" />
            <div className="flex flex-col gap-2">
                <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Dashboard
                </p>
                <h1 className="text-3xl font-semibold tracking-tight">Content operations</h1>
                <p className="max-w-3xl text-sm leading-6 text-slate-600">
                    Manage teacher-authored Lithuanian scenarios, dialogue, CEFR metadata, and
                    publishing state from Laravel, the app source of truth.
                </p>
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {cards.map((card) => {
                    const Icon = card.icon;

                    return (
                        <article key={card.label} className="rounded-lg border border-slate-200 bg-white p-4">
                            <Icon className="size-5 text-slate-500" />
                            <p className="mt-3 text-2xl font-semibold">{card.value}</p>
                            <p className="text-sm text-slate-500">{card.label}</p>
                        </article>
                    );
                })}
            </div>

            <section className="mt-8 rounded-lg border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 className="font-semibold">Recent scenarios</h2>
                    <Link href="/cms/scenarios" className="text-sm font-medium text-blue-700">
                        View all
                    </Link>
                </div>
                <div className="divide-y divide-slate-100">
                    {recentScenarios.map((scenario) => (
                        <Link
                            key={scenario.id}
                            href={`/cms/scenarios/${scenario.slug}`}
                            className="grid gap-2 px-4 py-3 text-sm hover:bg-slate-50 md:grid-cols-[1fr_120px_120px]"
                        >
                            <span>
                                <span className="font-medium">{scenario.title}</span>
                                <span className="ml-2 text-slate-500">{scenario.character}</span>
                            </span>
                            <span className="text-slate-600">{scenario.cefr_level ?? 'unleveled'}</span>
                            <span className="text-slate-600">{scenario.status}</span>
                        </Link>
                    ))}
                </div>
            </section>
        </CmsLayout>
    );
}
