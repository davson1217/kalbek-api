import { MessageCircle, PlayCircle, Route, Sparkles } from 'lucide-react';

import type { GoalRecord, NpcLineRecord, ScenarioDetail, SceneRecord } from '../../../types';
import { StatusBadge } from '../PageChrome';

export function ScenarioPreview({ scenario }: { scenario: ScenarioDetail }) {
    const orderedScenes = previewScenes(scenario);

    return (
        <section className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-xl shadow-slate-200/60 backdrop-blur">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-cyan-700"><PlayCircle className="size-4" /> Learner preview</p>
                    <h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950">Scenario journey</h2>
                    <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Review the conversation as a learner will experience it: scene prompt first, then possible learner goals and character replies.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <StatusBadge>{scenario.start_scene_slug ?? 'no start scene'}</StatusBadge>
                    <StatusBadge tone="cyan">{orderedScenes.length} scenes</StatusBadge>
                </div>
            </div>

            {orderedScenes.length === 0 ? (
                <div className="mt-4 rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm font-medium text-slate-500">Create scenes before previewing the learner journey.</div>
            ) : (
                <div className="mt-5 space-y-4">
                    {orderedScenes.map((scene, index) => (
                        <PreviewScene key={scene.id} index={index} scenario={scenario} scene={scene} />
                    ))}
                </div>
            )}
        </section>
    );
}

function PreviewScene({ index, scenario, scene }: { index: number; scenario: ScenarioDetail; scene: SceneRecord }) {
    const openingLines = scene.lines.filter((line) => !line.trigger_goal_db_id);

    return (
        <article className="overflow-hidden rounded-3xl border border-slate-100 bg-slate-50/80">
            <div className="flex flex-col gap-3 border-b border-slate-100 bg-white/80 p-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex size-7 items-center justify-center rounded-full bg-slate-950 text-xs font-black text-white">{index + 1}</span>
                        <h3 className="text-lg font-black tracking-tight text-slate-950">{scene.slug}</h3>
                        {scene.slug === scenario.start_scene_slug ? <StatusBadge tone="emerald">start</StatusBadge> : null}
                        <StatusBadge tone="cyan">{scene.cefr_level?.toUpperCase() ?? 'inherit'}</StatusBadge>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-slate-600">{scene.setting || 'No setting has been written yet.'}</p>
                </div>
                <StatusBadge>{scene.goals.length} learner choices</StatusBadge>
            </div>

            <div className="grid gap-4 p-4 xl:grid-cols-[minmax(220px,0.75fr)_minmax(0,1.5fr)]">
                <div>
                    <h4 className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-slate-500"><MessageCircle className="size-4 text-cyan-700" /> Character opens with</h4>
                    <div className="mt-3 space-y-2">
                        {openingLines.length === 0 ? <PreviewEmpty>No opening line.</PreviewEmpty> : openingLines.map((line) => <PreviewLine key={line.id} line={line} />)}
                    </div>
                </div>

                <div>
                    <h4 className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-slate-500"><Route className="size-4 text-cyan-700" /> Learner goals and replies</h4>
                    <div className="mt-3 space-y-3">
                        {scene.goals.length === 0 ? <PreviewEmpty>No learner goals.</PreviewEmpty> : scene.goals.map((goal) => <PreviewGoal key={goal.id} goal={goal} />)}
                    </div>
                </div>
            </div>
        </article>
    );
}

function PreviewGoal({ goal }: { goal: GoalRecord }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
            <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <p className="font-black text-slate-950">{goal.label}</p>
                    <p className="mt-1 text-sm leading-6 text-slate-600">{goal.intent}</p>
                </div>
                <div className="flex shrink-0 flex-wrap gap-2">
                    <StatusBadge>{goal.cefr_level?.toUpperCase() ?? 'inherit'}</StatusBadge>
                    <StatusBadge tone={goal.next_scene_slug ? 'violet' : 'slate'}>{goal.next_scene_slug ? `next: ${goal.next_scene_slug}` : 'ends'}</StatusBadge>
                </div>
            </div>
            <p className="mt-3 rounded-2xl bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-950">Learner may say: {goal.example}</p>
            <div className="mt-3 space-y-2 border-t border-dashed border-slate-200 pt-3">
                <p className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500"><Sparkles className="size-3.5 text-violet-600" /> Possible character replies</p>
                {goal.response_lines.length === 0 ? <PreviewEmpty>No replies attached to this goal.</PreviewEmpty> : goal.response_lines.map((line) => <PreviewLine key={line.id} line={line} />)}
            </div>
        </div>
    );
}

function PreviewLine({ line }: { line: NpcLineRecord }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-white px-3 py-2 shadow-sm">
            <p className="font-bold leading-6 text-slate-950">{line.target_text}</p>
            <p className="text-sm leading-6 text-slate-500">{line.support_translation}</p>
        </div>
    );
}

function PreviewEmpty({ children }: React.PropsWithChildren) {
    return <p className="rounded-2xl border border-dashed border-slate-200 bg-white/70 p-3 text-sm font-medium text-slate-500">{children}</p>;
}

function previewScenes(scenario: ScenarioDetail): SceneRecord[] {
    const start = scenario.scenes.find((scene) => scene.slug === scenario.start_scene_slug);
    if (!start) return scenario.scenes;

    return [start, ...scenario.scenes.filter((scene) => scene.id !== start.id)];
}
