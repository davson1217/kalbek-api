import { Head, useForm } from '@inertiajs/react';
import { Edit3, Globe2, Languages, Plus, Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import { SelectField, TextField } from '../../Components/Cms/FormControls';
import { Modal } from '../../Components/Cms/Modal';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { LanguageRecord } from '../../types';

interface Props {
    languages: LanguageRecord[];
    statuses: string[];
}

const emptyLanguage = {
    code: '',
    name: '',
    native_name: '',
    support_language_code: 'en',
    support_language_name: 'English',
    status: 'active',
    default_voice: '',
    sort_order: 0,
};

export default function LanguagesIndex({ languages, statuses }: Props) {
    const [query, setQuery] = useState('');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LanguageRecord | null>(null);

    const filteredLanguages = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) return languages;

        return languages.filter((language) => [
            language.code,
            language.name,
            language.native_name,
            language.support_language_code,
            language.support_language_name,
            language.status,
            language.default_voice,
        ].some((value) => value?.toLowerCase().includes(needle)));
    }, [languages, query]);

    return (
        <CmsLayout>
            <Head title="Languages" />
            <div className="space-y-5">
                <Breadcrumbs items={[{ label: 'CMS', href: '/cms' }, { label: 'Languages' }]} />
                <PageHeader
                    eyebrow="Language Catalog"
                    title="Languages"
                    actions={<PrimaryButton icon={Plus} onClick={() => setCreating(true)}>Create language</PrimaryButton>}
                >
                    Manage target languages once, then attach characters, scenarios, CEFR records, speech settings, and support translations to them.
                </PageHeader>

                <section className="rounded-3xl border border-white/80 bg-white/85 p-4 shadow-xl shadow-slate-200/60 backdrop-blur">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-start gap-3">
                            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-800">
                                <Languages className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-lg font-black tracking-tight text-slate-950">Available languages</h2>
                                <p className="text-sm text-slate-500">{filteredLanguages.length} of {languages.length} languages shown</p>
                            </div>
                        </div>
                        <label className="relative w-full lg:max-w-sm">
                            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                <Search className="size-4 text-slate-400" />
                            </span>
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search languages"
                                className="h-10 w-full rounded-full border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                            />
                        </label>
                    </div>

                    <div className="mt-4 grid gap-3">
                        {filteredLanguages.length ? filteredLanguages.map((language) => (
                            <LanguageRow key={language.id} language={language} onEdit={() => setEditing(language)} />
                        )) : (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                                <p className="font-bold text-slate-800">No languages found</p>
                                <p className="mt-1 text-sm text-slate-500">Try another search term or create a new language.</p>
                            </div>
                        )}
                    </div>
                </section>
            </div>

            <Modal
                open={creating}
                title="Create language"
                description="Start with a short code such as lt, en, es, fr, or de. English remains the default support translation language unless changed."
                onClose={() => setCreating(false)}
            >
                <LanguageForm statuses={statuses} onSuccess={() => setCreating(false)} />
            </Modal>

            <Modal
                open={Boolean(editing)}
                title={editing ? `Edit ${editing.name}` : 'Edit language'}
                description="Changes affect how scenarios, speech services, support translations, and learner progress are grouped."
                onClose={() => setEditing(null)}
            >
                {editing ? <LanguageForm language={editing} statuses={statuses} onSuccess={() => setEditing(null)} /> : null}
            </Modal>
        </CmsLayout>
    );
}

function LanguageRow({ language, onEdit }: { language: LanguageRecord; onEdit: () => void }) {
    return (
        <article className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-cyan-100 hover:bg-cyan-50/30">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-3">
                    <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-800">
                        <Globe2 className="size-5" />
                    </span>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-black tracking-tight text-slate-950">{language.name}</h2>
                            <StatusBadge tone="cyan">{language.code}</StatusBadge>
                            <StatusBadge tone={language.status === 'active' ? 'emerald' : 'slate'}>{language.status}</StatusBadge>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Native name: <span className="font-semibold text-slate-700">{language.native_name}</span>
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <StatusBadge>{language.support_language_name} support</StatusBadge>
                            <StatusBadge>{language.scenarios_count} scenarios</StatusBadge>
                            <StatusBadge>{language.characters_count} characters</StatusBadge>
                            <StatusBadge>sort {language.sort_order}</StatusBadge>
                            {language.default_voice ? <StatusBadge tone="violet">{language.default_voice}</StatusBadge> : null}
                        </div>
                    </div>
                </div>
                <SecondaryButton icon={Edit3} onClick={onEdit}>Edit</SecondaryButton>
            </div>
        </article>
    );
}

function LanguageForm({ language, statuses, onSuccess }: { language?: LanguageRecord; statuses: string[]; onSuccess: () => void }) {
    const form = useForm({
        code: language?.code ?? emptyLanguage.code,
        name: language?.name ?? emptyLanguage.name,
        native_name: language?.native_name ?? emptyLanguage.native_name,
        support_language_code: language?.support_language_code ?? emptyLanguage.support_language_code,
        support_language_name: language?.support_language_name ?? emptyLanguage.support_language_name,
        status: language?.status ?? emptyLanguage.status,
        default_voice: language?.default_voice ?? emptyLanguage.default_voice,
        sort_order: language?.sort_order ?? emptyLanguage.sort_order,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!language) form.reset();
                onSuccess();
            },
        };

        if (language) {
            form.put(`/cms/languages/${language.code}`, options);
            return;
        }

        form.post('/cms/languages', options);
    }

    return (
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
            <TextField
                label="Code"
                placeholder="lt"
                help="Short language code used by APIs, speech providers, route filters, and translation lookup."
                value={form.data.code}
                onChange={(value) => form.setData('code', value.toLowerCase())}
                error={form.errors.code}
            />
            <TextField
                label="Name"
                placeholder="Lithuanian"
                help="Public language name used in the CMS and app language selectors."
                value={form.data.name}
                onChange={(value) => form.setData('name', value)}
                error={form.errors.name}
            />
            <TextField
                label="Native name"
                placeholder="Lietuviu"
                help="Language name as speakers of that language usually write it."
                value={form.data.native_name}
                onChange={(value) => form.setData('native_name', value)}
                error={form.errors.native_name}
            />
            <SelectField
                label="Status"
                help="Inactive languages stay in the CMS but should not be exposed as playable catalog content."
                value={form.data.status}
                onChange={(value) => form.setData('status', String(value))}
                options={statuses.map((status) => ({ value: status, label: status }))}
                error={form.errors.status}
            />
            <TextField
                label="Support code"
                placeholder="en"
                help="Fallback translation language shown under target-language text. English is the default."
                value={form.data.support_language_code}
                onChange={(value) => form.setData('support_language_code', value.toLowerCase())}
                error={form.errors.support_language_code}
            />
            <TextField
                label="Support language"
                placeholder="English"
                help="Readable name for the support translation language."
                value={form.data.support_language_name}
                onChange={(value) => form.setData('support_language_name', value)}
                error={form.errors.support_language_name}
            />
            <TextField
                label="Default voice"
                placeholder="alloy or provider voice id"
                help="Optional voice identifier used when generated audio does not specify a more exact voice."
                value={form.data.default_voice}
                onChange={(value) => form.setData('default_voice', value)}
                error={form.errors.default_voice}
            />
            <TextField
                label="Sort"
                placeholder="0"
                type="number"
                help="Lower numbers appear first in language lists."
                value={form.data.sort_order}
                onChange={(value) => form.setData('sort_order', Number(value))}
                error={form.errors.sort_order}
            />
            <div className="md:col-span-2">
                <PrimaryButton type="submit" icon={Save} disabled={form.processing}>
                    {language ? 'Save language' : 'Create language'}
                </PrimaryButton>
            </div>
        </form>
    );
}
