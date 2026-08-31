import { AlertTriangle, ChevronDown, Edit3, MessageCircle, Plus, Route, ShoppingBag } from 'lucide-react';
import { useState } from 'react';

import { Modal } from '../Modal';
import { PrimaryButton, SecondaryButton, StatusBadge } from '../PageChrome';
import type { GoalRecord, NpcLineRecord, ScenarioAuditIssue, ScenarioDetail, ScenePropRecord, SceneRecord } from '../../../types';
import { DeleteButton, GoalForm, LineForm, PropForm, SceneForm } from './ContentForms';

type ModalState =
    | { type: 'edit-scene' }
    | { type: 'create-goal' }
    | { goal: GoalRecord; type: 'edit-goal' }
    | { goal?: GoalRecord; type: 'create-line' }
    | { line: NpcLineRecord; type: 'edit-line' }
    | { type: 'create-prop' }
    | { prop: ScenePropRecord; type: 'edit-prop' }
    | null;

export function ScenePanel({ auditIssues, goalOptions, levels, scenario, scene, sceneOptions }: { auditIssues: ScenarioAuditIssue[]; goalOptions: Array<{ value: number; label: string }>; levels: string[]; scenario: ScenarioDetail; scene: SceneRecord; sceneOptions: Array<{ value: number; label: string }> }) {
    const [open, setOpen] = useState(false);
    const [modal, setModal] = useState<ModalState>(null);
    const closeModal = () => setModal(null);
    const sceneGoalOptions = scene.goals.map((goal) => ({ value: goal.id, label: goal.slug }));
    const genericLines = scene.lines.filter((line) => !line.trigger_goal_db_id);
    const criticalIssueCount = auditIssues.filter((issue) => issue.severity === 'critical').length;
    const warningIssueCount = auditIssues.filter((issue) => issue.severity === 'warning').length;

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/85 shadow-xl shadow-slate-200/60 backdrop-blur transition hover:-translate-y-0.5 hover:shadow-2xl hover:shadow-slate-200">
            <button type="button" onClick={() => setOpen((value) => !value)} className="flex w-full items-start justify-between gap-4 p-5 text-left">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-xl font-black tracking-tight text-slate-950">{scene.slug}</h2>
                        <StatusBadge tone="cyan">{scene.cefr_level?.toUpperCase() ?? 'INHERIT'}</StatusBadge>
                        <StatusBadge>{scene.goals.length} goals</StatusBadge>
                        <StatusBadge>{scene.lines.length} lines</StatusBadge>
                        {criticalIssueCount > 0 ? <StatusBadge tone="rose">{criticalIssueCount} critical</StatusBadge> : null}
                        {warningIssueCount > 0 ? <StatusBadge tone="amber">{warningIssueCount} warnings</StatusBadge> : null}
                    </div>
                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{scene.setting || 'No setting has been written yet.'}</p>
                </div>
                <ChevronDown className={`mt-1 size-5 shrink-0 text-slate-500 transition ${open ? 'rotate-180' : ''}`} />
            </button>

            {open ? (
                <div className="border-t border-slate-100 px-5 pb-5 animate-in fade-in slide-in-from-top-2 duration-200">
                    {auditIssues.length > 0 ? <SceneIssueList issues={auditIssues} /> : null}

                    <div className="mt-4 flex flex-wrap gap-2">
                        <SecondaryButton icon={Edit3} onClick={() => setModal({ type: 'edit-scene' })}>Edit scene</SecondaryButton>
                        <PrimaryButton icon={Plus} onClick={() => setModal({ type: 'create-goal' })}>Add goal</PrimaryButton>
                        <SecondaryButton icon={MessageCircle} onClick={() => setModal({ type: 'create-line' })}>Add character reply</SecondaryButton>
                        <SecondaryButton icon={ShoppingBag} onClick={() => setModal({ type: 'create-prop' })}>Add prop</SecondaryButton>
                        <DeleteButton action={`/cms/scenarios/${scenario.slug}/scenes/${scene.id}`} label="Delete scene" />
                    </div>

                    <div className="mt-5 grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                        <ContentGroup icon={Route} title="Goal flow and replies" count={scene.goals.length}>
                            {scene.goals.length === 0 ? <EmptyCopy>No learner goals yet. Add a goal first, then attach character replies to it.</EmptyCopy> : scene.goals.map((goal) => (
                                <article key={goal.id} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                    <div className="mb-3 flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.16em] text-cyan-700">
                                        <Route className="size-3.5" /> Learner goal
                                    </div>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <button type="button" onClick={() => setModal({ type: 'edit-goal', goal })} className="min-w-0 text-left">
                                            <p className="font-bold text-slate-950">{goal.label}</p>
                                            <p className="mt-1 text-xs text-slate-500">{goal.slug} {'->'} {goal.next_scene_slug ?? 'end conversation'}</p>
                                        </button>
                                        <div className="flex flex-wrap gap-2">
                                            <StatusBadge>{goal.cefr_level?.toUpperCase() ?? 'INHERIT'}</StatusBadge>
                                            <StatusBadge tone={goal.response_lines.length > 0 ? 'emerald' : 'amber'}>{goal.response_lines.length} replies</StatusBadge>
                                        </div>
                                    </div>
                                    <p className="mt-3 text-sm leading-6 text-slate-600">{goal.intent}</p>
                                    <p className="mt-2 rounded-xl bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-900">Example: {goal.example}</p>

                                    <div className="mt-5 border-t border-dashed border-slate-200 pt-4">
                                        <div className="mb-3 flex items-center justify-between gap-3">
                                            <h4 className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.14em] text-slate-500"><MessageCircle className="size-3.5 text-violet-600" /> Character replies for this goal</h4>
                                            <StatusBadge tone={goal.response_lines.length > 0 ? 'emerald' : 'amber'}>{goal.response_lines.length}</StatusBadge>
                                        </div>
                                        <div className="space-y-2">
                                        {goal.response_lines.length === 0 ? <EmptyCopy>No replies are attached to this goal yet.</EmptyCopy> : goal.response_lines.map((line) => <LineCard key={line.id} line={line} onClick={() => setModal({ type: 'edit-line', line })} />)}
                                        </div>
                                    </div>

                                    <SecondaryButton icon={MessageCircle} onClick={() => setModal({ type: 'create-line', goal })} className="mt-3">Add reply for this goal</SecondaryButton>
                                </article>
                            ))}
                        </ContentGroup>

                        <div className="space-y-4">
                            <ContentGroup icon={MessageCircle} title="Opening lines" count={genericLines.length}>
                                {genericLines.length === 0 ? <EmptyCopy>No opening lines yet.</EmptyCopy> : genericLines.map((line) => <LineCard key={line.id} line={line} onClick={() => setModal({ type: 'edit-line', line })} />)}
                            </ContentGroup>

                        <ContentGroup icon={ShoppingBag} title="Props and menu" count={scene.props.length}>
                            {scene.props.length === 0 ? <EmptyCopy>No props or menu items yet.</EmptyCopy> : scene.props.map((prop) => (
                                <button key={prop.id} type="button" onClick={() => setModal({ type: 'edit-prop', prop })} className="w-full rounded-2xl border border-slate-100 bg-white p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-100 hover:shadow-md">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-bold text-slate-950">{prop.target_text}</p>
                                            <p className="text-sm text-slate-500">{prop.support_translation}</p>
                                        </div>
                                        {prop.price ? <StatusBadge tone="emerald">{prop.price}</StatusBadge> : null}
                                    </div>
                                    <p className="mt-2 text-xs uppercase tracking-[0.14em] text-slate-400">{prop.type}</p>
                                </button>
                            ))}
                        </ContentGroup>
                        </div>
                    </div>
                </div>
            ) : null}

            <SceneModals modal={modal} closeModal={closeModal} goalOptions={sceneGoalOptions.length > 0 ? sceneGoalOptions : goalOptions} levels={levels} scenario={scenario} scene={scene} sceneOptions={sceneOptions} />
        </section>
    );
}

function SceneIssueList({ issues }: { issues: ScenarioAuditIssue[] }) {
    return (
        <div className="mt-4 rounded-3xl border border-amber-100 bg-amber-50/80 p-4">
            <h3 className="flex items-center gap-2 text-sm font-black text-amber-950"><AlertTriangle className="size-4 text-amber-700" /> Flow QA for this scene</h3>
            <div className="mt-3 space-y-2">
                {issues.map((issue, index) => (
                    <div key={`${issue.message}-${index}`} className="rounded-2xl border border-white/80 bg-white/80 px-3 py-2 text-sm leading-6 text-slate-700">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge tone={issue.severity === 'critical' ? 'rose' : 'amber'}>{issue.severity}</StatusBadge>
                            {issue.goal_slug ? <StatusBadge>{issue.goal_slug}</StatusBadge> : null}
                        </div>
                        <p className="mt-2">{issue.message}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function SceneModals({ closeModal, goalOptions, levels, modal, scenario, scene, sceneOptions }: { closeModal: () => void; goalOptions: Array<{ value: number; label: string }>; levels: string[]; modal: ModalState; scenario: ScenarioDetail; scene: SceneRecord; sceneOptions: Array<{ value: number; label: string }> }) {
    if (!modal) return null;

    const base = `/cms/scenarios/${scenario.slug}/scenes/${scene.id}`;

    if (modal.type === 'edit-scene') {
        return <Modal open title="Edit scene" description="Shape the place, level, and order of this scene." onClose={closeModal}><SceneForm action={base} method="put" scene={scene} levels={levels} onSuccess={closeModal} /></Modal>;
    }

    if (modal.type === 'create-goal') {
        return <Modal open title="Add learner goal" description="Goals describe what the learner is trying to communicate." onClose={closeModal}><GoalForm action={`${base}/goals`} levels={levels} sceneOptions={sceneOptions} onSuccess={closeModal} /></Modal>;
    }

    if (modal.type === 'edit-goal') {
        return <Modal open title="Edit learner goal" onClose={closeModal}><GoalForm action={`${base}/goals/${modal.goal.id}`} method="put" goal={modal.goal} levels={levels} sceneOptions={sceneOptions} onSuccess={closeModal} /><div className="mt-4"><DeleteButton action={`${base}/goals/${modal.goal.id}`} /></div></Modal>;
    }

    if (modal.type === 'create-line') {
        return <Modal open title="Add character reply" description="Character replies should be attached to the learner goal they answer. Leave it generic only for opening lines." onClose={closeModal}><LineForm action={`${base}/lines`} levels={levels} goalOptions={goalOptions} initialGoalId={modal.goal?.id ?? ''} onSuccess={closeModal} /></Modal>;
    }

    if (modal.type === 'edit-line') {
        return <Modal open title="Edit character reply" onClose={closeModal}><LineForm action={`${base}/lines/${modal.line.id}`} method="put" line={modal.line} levels={levels} goalOptions={goalOptions} onSuccess={closeModal} /><div className="mt-4"><DeleteButton action={`${base}/lines/${modal.line.id}`} /></div></Modal>;
    }

    if (modal.type === 'create-prop') {
        return <Modal open title="Add prop or menu item" description="Props are scene materials such as menus, prices, signs, or objects." onClose={closeModal}><PropForm action={`${base}/props`} onSuccess={closeModal} /></Modal>;
    }

    return <Modal open title="Edit prop or menu item" onClose={closeModal}><PropForm action={`${base}/props/${modal.prop.id}`} method="put" prop={modal.prop} onSuccess={closeModal} /><div className="mt-4"><DeleteButton action={`${base}/props/${modal.prop.id}`} /></div></Modal>;
}

function LineCard({ line, onClick }: { line: NpcLineRecord; onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} className="w-full rounded-2xl border border-slate-100 bg-white p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-100 hover:shadow-md">
            <p className="font-bold leading-6 text-slate-950">{line.target_text}</p>
            <p className="mt-1 text-sm text-slate-500">{line.support_translation}</p>
            <div className="mt-3 flex flex-wrap gap-2">
                <StatusBadge tone="violet">priority {line.priority}</StatusBadge>
                <StatusBadge>{line.trigger_goal_id ?? 'opening'}</StatusBadge>
            </div>
        </button>
    );
}

function ContentGroup({ children, count, icon: Icon, title }: React.PropsWithChildren<{ count: number; icon: React.ComponentType<{ className?: string }>; title: string }>) {
    return (
        <section className="rounded-3xl border border-slate-100 bg-slate-50/80 p-3">
            <div className="mb-3 flex items-center justify-between gap-3 px-1">
                <h3 className="flex items-center gap-2 text-sm font-black text-slate-950"><Icon className="size-4 text-cyan-700" /> {title}</h3>
                <StatusBadge>{count}</StatusBadge>
            </div>
            <div className="space-y-3">{children}</div>
        </section>
    );
}

function EmptyCopy({ children }: React.PropsWithChildren) {
    return <p className="rounded-2xl border border-dashed border-slate-200 bg-white/70 p-4 text-sm font-medium text-slate-500">{children}</p>;
}
