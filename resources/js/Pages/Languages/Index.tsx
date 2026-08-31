import { Head, useForm } from '@inertiajs/react';
import { Globe2, Plus, Save } from 'lucide-react';

import { SelectField, TextField } from '../../Components/Cms/FormControls';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { LanguageRecord } from '../../types';

interface Props {
    languages: LanguageRecord[];
    statuses: string[];
}

export default function LanguagesIndex({ languages, statuses }: Props) {
    const create = useForm({
        code: '',
        name: '',
        native_name: '',
        support_language_code: 'en',
        support_language_name: 'English',
        status: 'active',
        default_voice: '',
        sort_order: 0,
    });

    return (
        <CmsLayout>
            <Head title="Languages" />
            <div className="space-y-5">
                <Breadcrumbs items={[{ label: 'CMS', href: '/cms' }, { label: 'Languages' }]} />
                <PageHeader eyebrow="Language Catalog" title="Languages">
                    Add target languages once, then attach characters and scenarios to them. The code is used for transcription, API filtering, learner CEFR records, and generated audio caching.
                </PageHeader>

                <section className="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-xl shadow-slate-200/60 backdrop-blur">
                    <div className="flex items-center gap-2">
                        <span className="inline-flex size-9 items-center justify-center rounded-2xl bg-slate-950 text-white"><Plus className="size-4" /></span>
                        <div>
                            <h2 className="text-lg font-black tracking-tight text-slate-950">Create language</h2>
                            <p className="text-sm text-slate-500">Start with an ISO-style code such as lt, en, es, fr, or de.</p>
                        </div>
                    </div>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            create.post('/cms/languages', { preserveScroll: true, onSuccess: () => create.reset() });
                        }}
                        className="mt-4 grid gap-4 md:grid-cols-3"
                    >
                        <TextField label="Code" placeholder="en" help="Short language code used by APIs and speech services." value={create.data.code} onChange={(value) => create.setData('code', value.toLowerCase())} error={create.errors.code} />
                        <TextField label="Name" placeholder="English" value={create.data.name} onChange={(value) => create.setData('name', value)} error={create.errors.name} />
                        <TextField label="Native name" placeholder="English" value={create.data.native_name} onChange={(value) => create.setData('native_name', value)} error={create.errors.native_name} />
                        <TextField label="Support code" placeholder="en" help="Language code for the support translation shown under target-language text. English is the default." value={create.data.support_language_code} onChange={(value) => create.setData('support_language_code', value.toLowerCase())} error={create.errors.support_language_code} />
                        <TextField label="Support language" placeholder="English" value={create.data.support_language_name} onChange={(value) => create.setData('support_language_name', value)} error={create.errors.support_language_name} />
                        <SelectField label="Status" value={create.data.status} onChange={(value) => create.setData('status', String(value))} options={statuses.map((status) => ({ value: status, label: status }))} />
                        <TextField label="Default voice" placeholder="default-female" help="Optional provider voice for generated audio in this language." value={create.data.default_voice} onChange={(value) => create.setData('default_voice', value)} error={create.errors.default_voice} />
                        <TextField label="Sort" placeholder="0" type="number" value={create.data.sort_order} onChange={(value) => create.setData('sort_order', Number(value))} error={create.errors.sort_order} />
                        <div className="md:col-span-3"><PrimaryButton type="submit" icon={Save} disabled={create.processing}>Create language</PrimaryButton></div>
                    </form>
                </section>

                <section className="grid gap-4">
                    {languages.map((language) => <LanguageCard key={language.id} language={language} statuses={statuses} />)}
                </section>
            </div>
        </CmsLayout>
    );
}

function LanguageCard({ language, statuses }: { language: LanguageRecord; statuses: string[] }) {
    const form = useForm({
        code: language.code,
        name: language.name,
        native_name: language.native_name,
        support_language_code: language.support_language_code,
        support_language_name: language.support_language_name,
        status: language.status,
        default_voice: language.default_voice ?? '',
        sort_order: language.sort_order,
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/cms/languages/${language.code}`, { preserveScroll: true });
            }}
            className="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-xl shadow-slate-200/50 backdrop-blur"
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex items-start gap-3">
                    <span className="inline-flex size-11 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-800"><Globe2 className="size-5" /></span>
                    <div>
                        <h2 className="text-xl font-black tracking-tight text-slate-950">{language.name}</h2>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <StatusBadge tone="cyan">{language.code}</StatusBadge>
                            <StatusBadge>{language.support_language_name} support</StatusBadge>
                            <StatusBadge tone={language.status === 'active' ? 'emerald' : 'slate'}>{language.status}</StatusBadge>
                            <StatusBadge>{language.scenarios_count} scenarios</StatusBadge>
                            <StatusBadge>{language.characters_count} characters</StatusBadge>
                        </div>
                    </div>
                </div>
                <SecondaryButton type="submit" icon={Save} disabled={form.processing}>Save</SecondaryButton>
            </div>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
                <TextField label="Code" value={form.data.code} onChange={(value) => form.setData('code', value.toLowerCase())} error={form.errors.code} />
                <TextField label="Name" value={form.data.name} onChange={(value) => form.setData('name', value)} error={form.errors.name} />
                <TextField label="Native name" value={form.data.native_name} onChange={(value) => form.setData('native_name', value)} error={form.errors.native_name} />
                <TextField label="Support code" value={form.data.support_language_code} onChange={(value) => form.setData('support_language_code', value.toLowerCase())} error={form.errors.support_language_code} />
                <TextField label="Support language" value={form.data.support_language_name} onChange={(value) => form.setData('support_language_name', value)} error={form.errors.support_language_name} />
                <SelectField label="Status" value={form.data.status} onChange={(value) => form.setData('status', String(value))} options={statuses.map((status) => ({ value: status, label: status }))} />
                <TextField label="Default voice" value={form.data.default_voice} onChange={(value) => form.setData('default_voice', value)} error={form.errors.default_voice} />
                <TextField label="Sort" type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} error={form.errors.sort_order} />
            </div>
        </form>
    );
}
