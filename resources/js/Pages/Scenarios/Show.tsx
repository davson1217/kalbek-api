import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, BookOpenText, CheckCircle2, Edit3, PlayCircle, Plus, Trash2, Volume2 } from 'lucide-react';
import { useState } from 'react';

import { Modal } from '../../Components/Cms/Modal';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { SceneForm, ScenarioNoteForm } from '../../Components/Cms/Scenarios/ContentForms';
import { ScenarioForm } from '../../Components/Cms/Scenarios/ScenarioForm';
import { ScenarioPreview } from '../../Components/Cms/Scenarios/ScenarioPreview';
import { ScenePanel } from '../../Components/Cms/Scenarios/ScenePanel';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { CharacterOption, LanguageOption, ScenarioAuditIssue, ScenarioDetail, UnitOption } from '../../types';

interface Props {
    scenario: ScenarioDetail;
    auditIssues: ScenarioAuditIssue[];
    characters: CharacterOption[];
    languages: LanguageOption[];
    units: UnitOption[];
    statuses: string[];
    levels: string[];
}

export default function ScenarioShow({ scenario, auditIssues, characters, languages, statuses, levels, units }: Props) {
    const [editingScenario, setEditingScenario] = useState(false);
    const [creatingScene, setCreatingScene] = useState(false);
    const [editingNote, setEditingNote] = useState(false);
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
                        <StatusBadge tone={scenario.is_free ? 'emerald' : 'slate'}>{scenario.is_free ? 'Free scenario' : 'Paid scenario'}</StatusBadge>
                        <StatusBadge tone="violet">{scenario.unit ? scenario.unit.title : 'No unit'}</StatusBadge>
                        <StatusBadge>{scenario.language ? `${scenario.language.name} (${scenario.language.code})` : 'No language'}</StatusBadge>
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

                <FlowAuditPanel issues={auditIssues} />

                <ScenarioNotePanel scenario={scenario} onEdit={() => setEditingNote(true)} />

                <AudioPreparationPanel scenario={scenario} />

                <ScenarioPreview scenario={scenario} />

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
                            <ScenePanel key={scene.id} scenario={scenario} scene={scene} auditIssues={auditIssues.filter((issue) => issue.scene_slug === scene.slug)} levels={levels} sceneOptions={sceneOptions} goalOptions={goalOptions} />
                        ))
                    )}
                </section>
            </div>

            <Modal open={editingScenario} title="Edit scenario" onClose={() => setEditingScenario(false)}>
                <ScenarioForm action={`/cms/scenarios/${scenario.slug}`} method="put" scenario={scenario} characters={characters} languages={languages} units={units} statuses={statuses} levels={levels} onSuccess={() => setEditingScenario(false)} />
            </Modal>

            <Modal open={creatingScene} title="Create scene" description="Scenes are the steps in a speaking scenario." onClose={() => setCreatingScene(false)}>
                <SceneForm action={`/cms/scenarios/${scenario.slug}/scenes`} levels={levels} onSuccess={() => setCreatingScene(false)} />
            </Modal>

            <Modal open={editingNote} title={scenario.note ? 'Edit preparation note' : 'Create preparation note'} description="Preparation notes teach the learner what this scenario will test before they start speaking." onClose={() => setEditingNote(false)}>
                <ScenarioNoteForm action={scenario.note ? `/cms/scenarios/${scenario.slug}/note/${scenario.note.id}` : `/cms/scenarios/${scenario.slug}/note`} method={scenario.note ? 'put' : 'post'} note={scenario.note ?? undefined} levels={levels} statuses={statuses} onSuccess={() => setEditingNote(false)} />
            </Modal>
        </CmsLayout>
    );
}

function ScenarioNotePanel({ onEdit, scenario }: { onEdit: () => void; scenario: ScenarioDetail }) {
    const note = scenario.note;

    return (
        <section className="rounded-3xl border border-emerald-100 bg-emerald-50/80 p-5 shadow-lg shadow-emerald-100/50">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex items-start gap-3">
                    <span className="mt-1 inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-white text-emerald-700 shadow-sm">
                        <BookOpenText className="size-5" />
                    </span>
                    <div>
                        <p className="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Preparation note</p>
                        <h2 className="mt-1 text-lg font-black text-emerald-950">
                            {note ? note.title : 'No preparation note yet'}
                        </h2>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-emerald-900">
                            {note
                                ? 'This material appears before the learner starts the scenario.'
                                : 'Add short teaching material so learners know what language patterns, vocabulary, or grammar they are about to practise.'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={onEdit} className="inline-flex items-center justify-center gap-2 rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-black text-emerald-800 shadow-sm transition hover:-translate-y-0.5">
                        {note ? <Edit3 className="size-4" /> : <Plus className="size-4" />}
                        {note ? 'Edit note' : 'Add note'}
                    </button>
                    {note ? (
                        <button type="button" onClick={() => router.delete(`/cms/scenarios/${scenario.slug}/note/${note.id}`, { preserveScroll: true })} className="inline-flex items-center justify-center gap-2 rounded-full border border-rose-100 bg-white px-4 py-2 text-sm font-black text-rose-700 shadow-sm transition hover:-translate-y-0.5 hover:border-rose-200 hover:bg-rose-50">
                            <Trash2 className="size-4" /> Delete
                        </button>
                    ) : null}
                </div>
            </div>

            {note ? (
                <div className="mt-4 rounded-2xl border border-white/80 bg-white/75 p-4">
                    <div className="flex flex-wrap gap-2">
                        <StatusBadge tone={note.status === 'published' ? 'emerald' : note.status === 'draft' ? 'amber' : 'slate'}>{note.status}</StatusBadge>
                        <StatusBadge>{note.cefr_level?.toUpperCase() ?? scenario.cefr_level?.toUpperCase() ?? 'UNSET'}</StatusBadge>
                        <StatusBadge>{note.estimated_minutes} min read</StatusBadge>
                    </div>
                    <div className="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">
                        {note.body}
                    </div>
                </div>
            ) : null}
        </section>
    );
}

function AudioPreparationPanel({ scenario }: { scenario: ScenarioDetail }) {
    const openingLineCount = scenario.scenes.reduce((total, scene) => total + scene.lines.filter((line) => !line.trigger_goal_db_id).length, 0);
    const goalReplyIds = new Set(scenario.scenes.flatMap((scene) => scene.goals.flatMap((goal) => goal.response_lines.map((line) => line.id))));
    const goalReplyCount = goalReplyIds.size;
    const propLineCount = scenario.scenes.reduce((total, scene) => total + scene.props.filter((prop) => prop.target_text).length, 0);
    const totalItems = openingLineCount + goalReplyCount + propLineCount;

    return (
        <section className="rounded-3xl border border-sky-100 bg-sky-50/80 p-5 shadow-lg shadow-sky-100/50">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex items-start gap-3">
                    <span className="mt-1 inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-white text-sky-700 shadow-sm">
                        <Volume2 className="size-5" />
                    </span>
                    <div>
                        <p className="text-xs font-black uppercase tracking-[0.18em] text-sky-700">Audio preparation</p>
                        <h2 className="mt-1 text-lg font-black text-sky-950">Pre-generation skeleton</h2>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-sky-900">
                            Future releases can generate and cache audio for selected published content before learners open it. This placeholder keeps the workflow visible without spending TTS tokens yet.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    disabled
                    className="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-full border border-sky-200 bg-white/70 px-4 py-2 text-sm font-black text-sky-500 opacity-70 shadow-sm"
                >
                    <Volume2 className="size-4" /> Generate audio
                </button>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-4">
                <AudioMetric label="Opening lines" value={openingLineCount} />
                <AudioMetric label="Goal replies" value={goalReplyCount} />
                <AudioMetric label="Props/menu text" value={propLineCount} />
                <AudioMetric label="Total candidates" value={totalItems} />
            </div>

            <div className="mt-4 rounded-2xl border border-white/80 bg-white/75 p-4">
                <div className="flex flex-wrap gap-2">
                    <StatusBadge tone="amber">Not implemented</StatusBadge>
                    <StatusBadge>Lazy cache remains active</StatusBadge>
                    <StatusBadge>{scenario.status} content</StatusBadge>
                </div>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    Intended long-term behavior: editors explicitly generate audio for published scenarios, Redis queues run the work in the background, Redis locks prevent duplicate token spend, and stale audio is regenerated only when content or voice settings change.
                </p>
            </div>
        </section>
    );
}

function AudioMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-2xl border border-white/80 bg-white/75 p-4 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-black tracking-tight text-slate-950">{value}</p>
        </div>
    );
}

function FlowAuditPanel({ issues }: { issues: ScenarioAuditIssue[] }) {
    const criticalCount = issues.filter((issue) => issue.severity === 'critical').length;
    const warningCount = issues.filter((issue) => issue.severity === 'warning').length;
    const runQa = () => router.reload({ only: ['auditIssues'], preserveScroll: true });

    if (issues.length === 0) {
        return (
            <section className="rounded-3xl border border-emerald-100 bg-emerald-50/80 p-5 shadow-lg shadow-emerald-100/50">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-1 size-5 shrink-0 text-emerald-700" />
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Content QA</p>
                            <h2 className="mt-1 text-lg font-black text-emerald-950">Flow QA passed</h2>
                            <p className="mt-1 text-sm leading-6 text-emerald-800">This scenario has a valid start scene, opening lines, goal replies, and goal-to-reply relationships.</p>
                        </div>
                    </div>
                    <button type="button" onClick={runQa} className="inline-flex items-center justify-center gap-2 rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-black text-emerald-800 shadow-sm transition hover:-translate-y-0.5">
                        <PlayCircle className="size-4" /> Run QA
                    </button>
                </div>
            </section>
        );
    }

    return (
        <section className="rounded-3xl border border-amber-100 bg-amber-50/80 p-5 shadow-lg shadow-amber-100/50">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex items-start gap-3">
                    <AlertTriangle className="mt-1 size-5 shrink-0 text-amber-700" />
                    <div>
                        <p className="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Content QA</p>
                        <h2 className="mt-1 text-lg font-black text-amber-950">Flow QA needs attention</h2>
                        <p className="mt-1 text-sm leading-6 text-amber-900">Resolve critical issues before publishing. Warnings are content quality risks that can make conversations feel unnatural.</p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <StatusBadge tone={criticalCount > 0 ? 'rose' : 'emerald'}>{criticalCount} critical</StatusBadge>
                    <StatusBadge tone={warningCount > 0 ? 'amber' : 'emerald'}>{warningCount} warnings</StatusBadge>
                    <button type="button" onClick={runQa} className="inline-flex items-center justify-center gap-2 rounded-full border border-amber-200 bg-white px-4 py-2 text-sm font-black text-amber-800 shadow-sm transition hover:-translate-y-0.5">
                        <PlayCircle className="size-4" /> Run QA
                    </button>
                </div>
            </div>
            <div className="mt-4 grid gap-2">
                {issues.map((issue, index) => (
                    <div key={`${issue.scope}-${issue.message}-${index}`} className="rounded-2xl border border-white/80 bg-white/80 px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge tone={issue.severity === 'critical' ? 'rose' : 'amber'}>{issue.severity}</StatusBadge>
                            <StatusBadge>{issue.category}</StatusBadge>
                            <span className="font-black text-slate-950">{issueLocation(issue)}</span>
                        </div>
                        <p className="mt-2">{issue.message}</p>
                        <p className="mt-1 text-slate-500"><span className="font-bold text-slate-700">Fix:</span> {issue.recommendation}</p>
                    </div>
                ))}
            </div>
        </section>
    );
}

function issueLocation(issue: ScenarioAuditIssue): string {
    return [issue.scenario_slug, issue.scene_slug, issue.goal_slug].filter(Boolean).join(' / ');
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-3xl border border-white/80 bg-white/75 p-4 shadow-lg shadow-slate-200/50 backdrop-blur">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{label}</p>
            <p className="mt-1 text-3xl font-black tracking-tight text-slate-950">{value}</p>
        </div>
    );
}
