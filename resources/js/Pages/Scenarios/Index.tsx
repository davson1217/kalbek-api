import { Head, Link } from '@inertiajs/react';
import { Edit3, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Modal } from '../../Components/Cms/Modal';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { ScenarioForm } from '../../Components/Cms/Scenarios/ScenarioForm';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { CharacterOption, LanguageOption, ScenarioSummary } from '../../types';

interface Props {
    scenarios: ScenarioSummary[];
    characters: CharacterOption[];
    languages: LanguageOption[];
    statuses: string[];
    levels: string[];
}

export default function ScenariosIndex({ scenarios, characters, languages, statuses, levels }: Props) {
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<ScenarioSummary | null>(null);
    const [creating, setCreating] = useState(false);

    const filteredScenarios = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) return scenarios;

        return scenarios.filter((scenario) => [scenario.title, scenario.slug, scenario.character, scenario.language?.name, scenario.language?.native_name, scenario.language?.code, scenario.status].some((value) => value?.toLowerCase().includes(needle)));
    }, [query, scenarios]);

    return (
        <CmsLayout>
            <Head title="Scenarios" />
            <div className="space-y-5">
                <Breadcrumbs items={[{ label: 'CMS', href: '/cms' }, { label: 'Scenarios' }]} />
                <PageHeader
                    eyebrow="Scenario Library"
                    title="Speaking scenarios"
                    actions={<PrimaryButton icon={Plus} onClick={() => setCreating(true)}>Create scenario</PrimaryButton>}
                >
                    Review the learner journeys first. Create and edit flows stay tucked away until you need them.
                </PageHeader>

                <section className="rounded-3xl border border-white/80 bg-white/85 p-4 shadow-xl shadow-slate-200/60 backdrop-blur">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="text-lg font-black tracking-tight text-slate-950">All scenarios</h2>
                            <p className="text-sm text-slate-500">{filteredScenarios.length} of {scenarios.length} scenarios shown</p>
                        </div>
                        <label className="relative w-full md:max-w-sm">
                            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                <Search className="size-4 text-slate-400" />
                            </span>
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search scenarios"
                                className="h-10 w-full rounded-full border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                            />
                        </label>
                    </div>

                    <div className="mt-4 overflow-hidden rounded-2xl border border-slate-100">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50/90 text-xs uppercase tracking-[0.16em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Scenario</th>
                                    <th className="hidden px-4 py-3 md:table-cell">Character</th>
                                    <th className="hidden px-4 py-3 lg:table-cell">Language</th>
                                    <th className="px-4 py-3">Level</th>
                                    <th className="hidden px-4 py-3 xl:table-cell">Access</th>
                                    <th className="hidden px-4 py-3 xl:table-cell">Scenes</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {filteredScenarios.map((scenario) => (
                                    <tr key={scenario.id} className="transition hover:bg-cyan-50/40">
                                        <td className="px-4 py-4">
                                            <Link href={`/cms/scenarios/${scenario.slug}`} className="group inline-flex items-center gap-3">
                                                <span className="inline-flex size-10 items-center justify-center rounded-2xl bg-slate-100 text-xl transition group-hover:bg-white group-hover:shadow-sm">{scenario.emoji}</span>
                                                <span>
                                                    <span className="block font-black text-slate-950 group-hover:text-cyan-800">{scenario.title}</span>
                                                    <span className="block text-xs text-slate-500">{scenario.slug}</span>
                                                </span>
                                            </Link>
                                        </td>
                                        <td className="hidden px-4 py-4 text-slate-600 md:table-cell">{scenario.character ?? 'No character'}</td>
                                        <td className="hidden px-4 py-4 text-slate-600 lg:table-cell">{scenario.language ? `${scenario.language.name} (${scenario.language.code})` : 'No language'}</td>
                                        <td className="px-4 py-4"><StatusBadge tone="cyan">{scenario.cefr_level?.toUpperCase() ?? 'UNSET'}</StatusBadge></td>
                                        <td className="hidden px-4 py-4 xl:table-cell"><StatusBadge tone={scenario.is_free ? 'emerald' : 'slate'}>{scenario.is_free ? 'Free' : 'Paid'}</StatusBadge></td>
                                        <td className="hidden px-4 py-4 xl:table-cell"><StatusBadge>{scenario.scenes_count ?? 0} scenes</StatusBadge></td>
                                        <td className="px-4 py-4 text-right">
                                            <SecondaryButton icon={Edit3} onClick={() => setEditing(scenario)}>Edit</SecondaryButton>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Modal open={creating} title="Create scenario" description="Start with the scenario shell. Scenes and lines can be added after." onClose={() => setCreating(false)}>
                <ScenarioForm action="/cms/scenarios" characters={characters} languages={languages} statuses={statuses} levels={levels} onSuccess={() => setCreating(false)} />
            </Modal>

            <Modal open={Boolean(editing)} title="Edit scenario" onClose={() => setEditing(null)}>
                {editing ? <ScenarioForm action={`/cms/scenarios/${editing.slug}`} method="put" scenario={editing} characters={characters} languages={languages} statuses={statuses} levels={levels} onSuccess={() => setEditing(null)} /> : null}
            </Modal>
        </CmsLayout>
    );
}
