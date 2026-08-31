import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit3, Plus } from 'lucide-react';
import { useState } from 'react';

import { Modal } from '../../Components/Cms/Modal';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { SceneForm } from '../../Components/Cms/Scenarios/ContentForms';
import { ScenarioForm } from '../../Components/Cms/Scenarios/ScenarioForm';
import { ScenePanel } from '../../Components/Cms/Scenarios/ScenePanel';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { CharacterOption, ScenarioDetail } from '../../types';

interface Props {
    scenario: ScenarioDetail;
    characters: CharacterOption[];
    statuses: string[];
    levels: string[];
}

export default function ScenarioShow({ scenario, characters, statuses, levels }: Props) {
    const [editingScenario, setEditingScenario] = useState(false);
    const [creatingScene, setCreatingScene] = useState(false);
    const sceneOptions = scenario.scenes.map((scene) => ({ value: scene.id, label: scene.slug }));
    const goalOptions = scenario.scenes.flatMap((scene) => scene.goals.map((goal) => ({ value: goal.id, label: `${scene.slug}: ${goal.slug}` })));

    return (
        <CmsLayout>
            <Head title={scenario.title} />
            <div className="space-y-5">
                <Breadcrumbs items={[{ label: 'CMS', href: '/cms' }, { label: 'Scenarios', href: '/cms/scenarios' }, { label: scenario.title }]} />

                <PageHeader
                    eyebrow="Scenario Map"
                    title={`${scenario.emoji} ${scenario.title}`}
                    actions={(
                        <>
                            <Link href="/cms/scenarios" className="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:text-slate-950">
                                <ArrowLeft className="size-4" /> Back
                            </Link>
                            <SecondaryButton icon={Edit3} onClick={() => setEditingScenario(true)}>Edit scenario</SecondaryButton>
                            <PrimaryButton icon={Plus} onClick={() => setCreatingScene(true)}>Create scene</PrimaryButton>
                        </>
                    )}
                >
                    <div className="flex flex-wrap gap-2 pt-1">
                        <StatusBadge tone="cyan">{scenario.cefr_level?.toUpperCase() ?? 'UNSET'}</StatusBadge>
                        <StatusBadge tone="emerald">{scenario.status}</StatusBadge>
                        <StatusBadge>{scenario.character ?? 'No character'}</StatusBadge>
                        <StatusBadge>{scenario.scenes.length} scenes</StatusBadge>
                    </div>
                    {scenario.description ? <p className="mt-3">{scenario.description}</p> : null}
                </PageHeader>

                <section className="grid gap-4 lg:grid-cols-4">
                    <Metric label="Scenes" value={scenario.scenes.length} />
                    <Metric label="Goals" value={scenario.scenes.reduce((total, scene) => total + scene.goals.length, 0)} />
                    <Metric label="Character replies" value={scenario.scenes.reduce((total, scene) => total + scene.lines.length, 0)} />
                    <Metric label="Props/menu" value={scenario.scenes.reduce((total, scene) => total + scene.props.length, 0)} />
                </section>

                <section className="space-y-3">
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <h2 className="text-2xl font-black tracking-tight text-slate-950">Scene flow</h2>
                            <p className="text-sm text-slate-600">Open a scene to manage its learner goals, character replies, and props.</p>
                        </div>
                    </div>

                    {scenario.scenes.length === 0 ? (
                        <div className="rounded-3xl border border-dashed border-slate-200 bg-white/80 p-8 text-center shadow-xl shadow-slate-200/50">
                            <h3 className="text-xl font-black text-slate-950">No scenes yet</h3>
                            <p className="mt-2 text-sm text-slate-500">Create the first scene to start building this speaking journey.</p>
                            <PrimaryButton icon={Plus} onClick={() => setCreatingScene(true)} className="mt-4">Create scene</PrimaryButton>
                        </div>
                    ) : (
                        scenario.scenes.map((scene) => (
                            <ScenePanel key={scene.id} scenario={scenario} scene={scene} levels={levels} sceneOptions={sceneOptions} goalOptions={goalOptions} />
                        ))
                    )}
                </section>
            </div>

            <Modal open={editingScenario} title="Edit scenario" onClose={() => setEditingScenario(false)}>
                <ScenarioForm action={`/cms/scenarios/${scenario.slug}`} method="put" scenario={scenario} characters={characters} statuses={statuses} levels={levels} onSuccess={() => setEditingScenario(false)} />
            </Modal>

            <Modal open={creatingScene} title="Create scene" description="Scenes are the steps in a speaking scenario." onClose={() => setCreatingScene(false)}>
                <SceneForm action={`/cms/scenarios/${scenario.slug}/scenes`} levels={levels} onSuccess={() => setCreatingScene(false)} />
            </Modal>
        </CmsLayout>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-3xl border border-white/80 bg-white/75 p-4 shadow-lg shadow-slate-200/50 backdrop-blur">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{label}</p>
            <p className="mt-1 text-3xl font-black tracking-tight text-slate-950">{value}</p>
        </div>
    );
}
