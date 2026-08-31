import { router, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';

import { blankOptions, SelectField, Textarea, TextField } from '../FormControls';
import { PrimaryButton, SecondaryButton } from '../PageChrome';
import type { GoalRecord, NpcLineRecord, ScenePropRecord, SceneRecord } from '../../../types';

type Method = 'post' | 'put';

export function SceneForm({ action, levels, method = 'post', onSuccess, scene }: { action: string; levels: string[]; method?: Method; onSuccess?: () => void; scene?: SceneRecord }) {
    const form = useForm({ slug: scene?.slug ?? '', setting: scene?.setting ?? '', cefr_level: scene?.cefr_level ?? '', sort_order: scene?.sort_order ?? 0 });

    return (
        <form onSubmit={(event) => submit(event, form, action, method, onSuccess)} className="grid gap-4 md:grid-cols-2">
            <TextField label="Scene slug" placeholder="arrival" help="A short internal name for this scene. Use lowercase letters, numbers, and hyphens, such as arrival or order-food." value={form.data.slug} onChange={(value) => form.setData('slug', value)} error={form.errors.slug} />
            <SelectField label="CEFR" help="The intended difficulty of this scene. Use Inherit when the scene should use the scenario's level." value={form.data.cefr_level} onChange={(value) => form.setData('cefr_level', String(value))} options={blankOptions(levels)} />
            <TextField label="Sort" placeholder="0" help="Controls the scene order in the CMS and app. Lower numbers appear earlier." type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} />
            <div className="md:col-span-2">
                <Textarea label="Setting" placeholder="You are at the restaurant entrance. The waiter greets you and asks if you have a reservation." help="Describe what is happening in the scene. This helps editors understand the context and can guide AI-assisted behavior later." value={form.data.setting} onChange={(value) => form.setData('setting', value)} error={form.errors.setting} rows={4} />
            </div>
            <div className="md:col-span-2"><PrimaryButton type="submit" icon={Save} disabled={form.processing}>{scene ? 'Save scene' : 'Create scene'}</PrimaryButton></div>
        </form>
    );
}

export function GoalForm({ action, goal, levels, method = 'post', onSuccess, sceneOptions }: { action: string; goal?: GoalRecord; levels: string[]; method?: Method; onSuccess?: () => void; sceneOptions: Array<{ value: number; label: string }> }) {
    const form = useForm({ slug: goal?.slug ?? '', label: goal?.label ?? '', intent: goal?.intent ?? '', example: goal?.example ?? '', cefr_level: goal?.cefr_level ?? '', next_scene_id: goal?.next_scene_id ?? '', sort_order: goal?.sort_order ?? 0 });

    return (
        <form onSubmit={(event) => submit(event, form, action, method, onSuccess)} className="grid gap-4 md:grid-cols-2">
            <TextField label="Goal slug" placeholder="ask-for-table" help="A short internal name for this learner goal. It helps connect learner intent to character replies." value={form.data.slug} onChange={(value) => form.setData('slug', value)} error={form.errors.slug} />
            <TextField label="Label" placeholder="Ask for a table" help="A friendly name editors can scan quickly inside the scene." value={form.data.label} onChange={(value) => form.setData('label', value)} />
            <SelectField label="Next scene" help="Where the learner should go after this goal is satisfied. Choose End conversation if this goal finishes the scenario." value={form.data.next_scene_id} onChange={(value) => form.setData('next_scene_id', value === '' ? '' : Number(value))} options={[{ value: '', label: 'End conversation' }, ...sceneOptions]} />
            <SelectField label="CEFR" help="The difficulty level expected for this learner goal. Use Inherit when it follows the scene level." value={form.data.cefr_level} onChange={(value) => form.setData('cefr_level', String(value))} options={blankOptions(levels)} />
            <TextField label="Sort" placeholder="0" help="Controls the display order of goals in this scene." type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} />
            <div className="md:col-span-2"><Textarea label="What the learner is trying to say" placeholder="The learner wants to know whether a table is available." help="Describe the meaning we should accept from the learner, not just one exact sentence." value={form.data.intent} onChange={(value) => form.setData('intent', value)} /></div>
            <div className="md:col-span-2"><Textarea label="Example learner phrase" placeholder="Ar turite laisvą staliuką?" help="One good Lithuanian example that expresses this goal." value={form.data.example} onChange={(value) => form.setData('example', value)} /></div>
            <div className="md:col-span-2"><PrimaryButton type="submit" icon={Save} disabled={form.processing}>{goal ? 'Save goal' : 'Create goal'}</PrimaryButton></div>
        </form>
    );
}

export function LineForm({ action, goalOptions, initialGoalId = '', levels, line, method = 'post', onSuccess }: { action: string; goalOptions: Array<{ value: number; label: string }>; initialGoalId?: number | ''; levels: string[]; line?: NpcLineRecord; method?: Method; onSuccess?: () => void }) {
    const form = useForm({ lt: line?.lt ?? '', en: line?.en ?? '', cefr_level: line?.cefr_level ?? '', trigger_goal_id: line?.trigger_goal_db_id ?? initialGoalId, priority: line?.priority ?? 0, sort_order: line?.sort_order ?? 0 });

    return (
        <form onSubmit={(event) => submit(event, form, action, method, onSuccess)} className="grid gap-4 md:grid-cols-2">
            <div className="md:col-span-2"><Textarea label="Lithuanian character reply" placeholder="Žinoma. Prašau eiti paskui mane, čia yra staliukas prie lango." help="What the scenario character says to the learner in Lithuanian." value={form.data.lt} onChange={(value) => form.setData('lt', value)} rows={4} /></div>
            <div className="md:col-span-2"><Textarea label="English meaning" placeholder="Of course. Please follow me, here is a table by the window." help="A clear English meaning for editors and debugging. This is not shown as the main learner challenge." value={form.data.en} onChange={(value) => form.setData('en', value)} rows={3} /></div>
            <SelectField label="Triggered after goal" help="Choose the learner goal that should cause this reply. Opening lines can stay generic; response lines should be tied to the exact learner goal they answer." value={form.data.trigger_goal_id} onChange={(value) => form.setData('trigger_goal_id', value === '' ? '' : Number(value))} options={[{ value: '', label: 'Opening / generic scene line' }, ...goalOptions]} />
            <SelectField label="CEFR" help="The language difficulty of this character reply. Use Inherit when it follows the scene level." value={form.data.cefr_level} onChange={(value) => form.setData('cefr_level', String(value))} options={blankOptions(levels)} />
            <TextField label="Priority" placeholder="100" help="When several replies match, higher priority wins. Use this to prefer the best authored response." type="number" value={form.data.priority} onChange={(value) => form.setData('priority', Number(value))} />
            <TextField label="Sort" placeholder="0" help="Controls the display order of replies inside the CMS." type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} />
            <div className="md:col-span-2"><PrimaryButton type="submit" icon={Save} disabled={form.processing}>{line ? 'Save character reply' : 'Create character reply'}</PrimaryButton></div>
        </form>
    );
}

export function PropForm({ action, method = 'post', onSuccess, prop }: { action: string; method?: Method; onSuccess?: () => void; prop?: ScenePropRecord }) {
    const form = useForm({ type: prop?.type ?? 'menu_item', lt: prop?.lt ?? '', en: prop?.en ?? '', price: prop?.price ?? '', sort_order: prop?.sort_order ?? 0 });

    return (
        <form onSubmit={(event) => submit(event, form, action, method, onSuccess)} className="grid gap-4 md:grid-cols-2">
            <TextField label="Type" placeholder="menu_item" help="The kind of supporting content, such as menu_item, sign, object, or hint." value={form.data.type} onChange={(value) => form.setData('type', value)} />
            <TextField label="Lithuanian" placeholder="Cepelinai" help="The Lithuanian text shown for this prop or menu item." value={form.data.lt} onChange={(value) => form.setData('lt', value)} />
            <TextField label="English" placeholder="Potato dumplings" help="English meaning for editors and learner support." value={form.data.en} onChange={(value) => form.setData('en', value)} />
            <TextField label="Price" placeholder="€8.50" help="Optional price or short value shown with menu-style items." value={form.data.price} onChange={(value) => form.setData('price', value)} />
            <TextField label="Sort" placeholder="0" help="Controls the display order of props and menu items." type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} />
            <div className="md:col-span-2"><PrimaryButton type="submit" icon={Save} disabled={form.processing}>{prop ? 'Save prop' : 'Create prop'}</PrimaryButton></div>
        </form>
    );
}

export function DeleteButton({ action, label = 'Delete' }: { action: string; label?: string }) {
    return <SecondaryButton icon={Trash2} onClick={() => router.delete(action, { preserveScroll: true })} className="border-rose-100 text-rose-700 hover:border-rose-200 hover:bg-rose-50">{label}</SecondaryButton>;
}

function submit(event: React.FormEvent, form: ReturnType<typeof useForm>, action: string, method: Method, onSuccess?: () => void) {
    event.preventDefault();
    form[method](action, { preserveScroll: true, onSuccess });
}
