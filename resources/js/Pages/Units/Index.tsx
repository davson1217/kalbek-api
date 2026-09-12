import { Head, useForm } from '@inertiajs/react';
import { Edit3, Layers3, Plus, Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import { blankOptions, SelectField, Textarea, TextField } from '../../Components/Cms/FormControls';
import { Modal } from '../../Components/Cms/Modal';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { TranslationFields } from '../../Components/Cms/Scenarios/ContentForms';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { LanguageOption, TranslationMap, UnitRecord } from '../../types';

interface Props {
    units: UnitRecord[];
    languages: LanguageOption[];
    statuses: string[];
    levels: string[];
}

const emptyUnit = {
    language_id: 0,
    slug: '',
    title: '',
    description: '',
    cefr_level: 'a1',
    status: 'draft',
    sort_order: 0,
    translations: {} as TranslationMap,
};

export default function UnitsIndex({ units, languages, statuses, levels }: Props) {
    const [query, setQuery] = useState('');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<UnitRecord | null>(null);

    const filteredUnits = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) return units;

        return units.filter((unit) => [
            unit.title,
            unit.slug,
            unit.description,
            unit.cefr_level,
            unit.status,
            unit.language?.name,
            unit.language?.native_name,
            unit.language?.code,
        ].some((value) => value?.toLowerCase().includes(needle)));
    }, [query, units]);

    return (
        <CmsLayout>
            <Head title="Units" />
            <div className="space-y-5">
                <Breadcrumbs items={[{ label: 'CMS', href: '/cms' }, { label: 'Units' }]} />
                <PageHeader
                    eyebrow="Curriculum"
                    title="Units"
                    actions={<PrimaryButton icon={Plus} onClick={() => setCreating(true)}>Create unit</PrimaryButton>}
                >
                    Group scenarios into intentional learning blocks, such as introductions, food, travel, health, and work.
                </PageHeader>

                <section className="rounded-3xl border border-white/80 bg-white/85 p-4 shadow-xl shadow-slate-200/60 backdrop-blur">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-start gap-3">
                            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-800">
                                <Layers3 className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-lg font-black tracking-tight text-slate-950">Learning units</h2>
                                <p className="text-sm text-slate-500">{filteredUnits.length} of {units.length} units shown</p>
                            </div>
                        </div>
                        <label className="relative w-full lg:max-w-sm">
                            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                <Search className="size-4 text-slate-400" />
                            </span>
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search units"
                                className="h-10 w-full rounded-full border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                            />
                        </label>
                    </div>

                    <div className="mt-4 grid gap-3">
                        {filteredUnits.length ? filteredUnits.map((unit) => (
                            <UnitRow key={unit.id} unit={unit} onEdit={() => setEditing(unit)} />
                        )) : (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                                <p className="font-bold text-slate-800">No units found</p>
                                <p className="mt-1 text-sm text-slate-500">Create a unit before grouping scenarios into a curriculum block.</p>
                            </div>
                        )}
                    </div>
                </section>
            </div>

            <Modal
                open={creating}
                title="Create unit"
                description="Create the curriculum block first. Scenarios can then be assigned to it from the scenario form."
                onClose={() => setCreating(false)}
            >
                <UnitForm languages={languages} statuses={statuses} levels={levels} onSuccess={() => setCreating(false)} />
            </Modal>

            <Modal
                open={Boolean(editing)}
                title={editing ? `Edit ${editing.title}` : 'Edit unit'}
                description="Keep units broad enough to hold several short speaking scenarios."
                onClose={() => setEditing(null)}
            >
                {editing ? <UnitForm unit={editing} languages={languages} statuses={statuses} levels={levels} onSuccess={() => setEditing(null)} /> : null}
            </Modal>
        </CmsLayout>
    );
}

function UnitRow({ unit, onEdit }: { unit: UnitRecord; onEdit: () => void }) {
    return (
        <article className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-cyan-100 hover:bg-cyan-50/30">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-3">
                    <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-800">
                        <Layers3 className="size-5" />
                    </span>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-black tracking-tight text-slate-950">{unit.title}</h2>
                            <StatusBadge tone="cyan">{unit.cefr_level?.toUpperCase() ?? 'UNSET'}</StatusBadge>
                            <StatusBadge tone={unit.status === 'published' ? 'emerald' : unit.status === 'archived' ? 'rose' : 'slate'}>{unit.status}</StatusBadge>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">{unit.description || 'No description yet.'}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <StatusBadge>{unit.slug}</StatusBadge>
                            <StatusBadge>{unit.language ? `${unit.language.name} (${unit.language.code})` : 'No language'}</StatusBadge>
                            <StatusBadge>{unit.scenarios_count} scenarios</StatusBadge>
                            <StatusBadge>sort {unit.sort_order}</StatusBadge>
                        </div>
                    </div>
                </div>
                <SecondaryButton icon={Edit3} onClick={onEdit}>Edit</SecondaryButton>
            </div>
        </article>
    );
}

function UnitForm({ unit, languages, statuses, levels, onSuccess }: { unit?: UnitRecord; languages: LanguageOption[]; statuses: string[]; levels: string[]; onSuccess: () => void }) {
    const form = useForm({
        language_id: unit?.language_id ?? languages[0]?.id ?? emptyUnit.language_id,
        slug: unit?.slug ?? emptyUnit.slug,
        title: unit?.title ?? emptyUnit.title,
        description: unit?.description ?? emptyUnit.description,
        cefr_level: unit?.cefr_level ?? emptyUnit.cefr_level,
        status: unit?.status ?? emptyUnit.status,
        sort_order: unit?.sort_order ?? emptyUnit.sort_order,
        translations: unit?.translations ?? emptyUnit.translations,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!unit) form.reset();
                onSuccess();
            },
        };

        if (unit) {
            form.put(`/cms/units/${unit.slug}`, options);
            return;
        }

        form.post('/cms/units', options);
    }

    return (
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
            <TextField
                label="Title"
                placeholder="Susipažinkime"
                help="The learner-facing name for this curriculum block."
                value={form.data.title}
                onChange={(value) => form.setData('title', value)}
                error={form.errors.title}
            />
            <TextField
                label="Slug"
                placeholder="susipazinkime"
                help="A stable internal name used to group scenarios. Use lowercase letters, numbers, and hyphens."
                value={form.data.slug}
                onChange={(value) => form.setData('slug', value)}
                error={form.errors.slug}
            />
            <SelectField
                label="Language"
                help="The target language this unit teaches. Only scenarios in the same language should be assigned to it."
                value={form.data.language_id}
                onChange={(value) => form.setData('language_id', Number(value))}
                options={languages.map((language) => ({ value: language.id, label: `${language.name} (${language.code})` }))}
                error={form.errors.language_id}
            />
            <SelectField
                label="CEFR"
                help="The intended difficulty band for this unit."
                value={form.data.cefr_level}
                onChange={(value) => form.setData('cefr_level', String(value))}
                options={blankOptions(levels, 'Unset')}
                error={form.errors.cefr_level}
            />
            <SelectField
                label="Status"
                help="Draft units stay in the CMS. Published units can be shown in learner-facing curriculum views."
                value={form.data.status}
                onChange={(value) => form.setData('status', String(value))}
                options={statuses.map((status) => ({ value: status, label: status }))}
                error={form.errors.status}
            />
            <TextField
                label="Sort"
                placeholder="0"
                type="number"
                help="Lower numbers appear earlier in the curriculum."
                value={form.data.sort_order}
                onChange={(value) => form.setData('sort_order', Number(value))}
                error={form.errors.sort_order}
            />
            <div className="md:col-span-2">
                <Textarea
                    label="Description"
                    placeholder="A first speaking unit for greetings, names, simple identity, and basic questions."
                    help="A short explanation of what this unit teaches and why the scenarios belong together."
                    rows={4}
                    value={form.data.description ?? ''}
                    onChange={(value) => form.setData('description', value)}
                    error={form.errors.description}
                />
            </div>
            <TranslationFields fields={['title', 'description']} translations={form.data.translations} onChange={(translations) => form.setData('translations', translations)} />
            <div className="md:col-span-2">
                <PrimaryButton type="submit" icon={Save} disabled={form.processing}>
                    {unit ? 'Save unit' : 'Create unit'}
                </PrimaryButton>
            </div>
        </form>
    );
}
