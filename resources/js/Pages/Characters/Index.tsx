import { Head, useForm } from '@inertiajs/react';
import { Edit3, Plus, Save, Search, Sparkles, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';

import { SelectField, Textarea, TextField } from '../../Components/Cms/FormControls';
import { Modal } from '../../Components/Cms/Modal';
import { Breadcrumbs, PageHeader, PrimaryButton, SecondaryButton, StatusBadge } from '../../Components/Cms/PageChrome';
import { CmsLayout } from '../../Layouts/CmsLayout';
import type { CharacterRecord, LanguageOption } from '../../types';

interface Props {
    characters: CharacterRecord[];
    statuses: string[];
    languages: LanguageOption[];
}

const emptyCharacter = {
    language_id: 0,
    slug: '',
    name: '',
    role: '',
    image_path: '',
    intro: '',
    praise_lines: '',
    encouragement_lines: '',
    sort_order: 0,
    status: 'draft',
};

export default function CharactersIndex({ characters, statuses, languages }: Props) {
    const [query, setQuery] = useState('');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<CharacterRecord | null>(null);

    const filteredCharacters = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) return characters;

        return characters.filter((character) => [
            character.name,
            character.slug,
            character.role,
            character.status,
            languageLabel(character, languages),
        ].some((value) => value?.toLowerCase().includes(needle)));
    }, [characters, languages, query]);

    return (
        <CmsLayout>
            <Head title="Characters" />
            <div className="space-y-5">
                <Breadcrumbs items={[{ label: 'CMS', href: '/cms' }, { label: 'Characters' }]} />
                <PageHeader
                    eyebrow="Scenario Cast"
                    title="Characters"
                    actions={<PrimaryButton icon={Plus} onClick={() => setCreating(true)}>Create character</PrimaryButton>}
                >
                    Manage the people learners speak with. Character status controls whether a character is draft, active in content, or archived.
                </PageHeader>

                <section className="rounded-3xl border border-white/80 bg-white/85 p-4 shadow-xl shadow-slate-200/60 backdrop-blur">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-start gap-3">
                            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-800">
                                <Sparkles className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-lg font-black tracking-tight text-slate-950">Available characters</h2>
                                <p className="text-sm text-slate-500">{filteredCharacters.length} of {characters.length} characters shown</p>
                            </div>
                        </div>
                        <label className="relative w-full lg:max-w-sm">
                            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                <Search className="size-4 text-slate-400" />
                            </span>
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search characters"
                                className="h-10 w-full rounded-full border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                            />
                        </label>
                    </div>

                    <div className="mt-4 grid gap-3">
                        {filteredCharacters.length ? filteredCharacters.map((character) => (
                            <CharacterRow key={character.id} character={character} languages={languages} onEdit={() => setEditing(character)} />
                        )) : (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                                <p className="font-bold text-slate-800">No characters found</p>
                                <p className="mt-1 text-sm text-slate-500">Try another search term or create a new character.</p>
                            </div>
                        )}
                    </div>
                </section>
            </div>

            <Modal
                open={creating}
                title="Create character"
                description="Create a speaker that can be attached to scenarios in the selected target language."
                onClose={() => setCreating(false)}
            >
                <CharacterForm languages={languages} statuses={statuses} onSuccess={() => setCreating(false)} />
            </Modal>

            <Modal
                open={Boolean(editing)}
                title={editing ? `Edit ${editing.name}` : 'Edit character'}
                description="Tune the character profile, status, and short feedback lines used during speaking practice."
                onClose={() => setEditing(null)}
            >
                {editing ? <CharacterForm character={editing} languages={languages} statuses={statuses} onSuccess={() => setEditing(null)} /> : null}
            </Modal>
        </CmsLayout>
    );
}

function CharacterRow({ character, languages, onEdit }: { character: CharacterRecord; languages: LanguageOption[]; onEdit: () => void }) {
    return (
        <article className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-cyan-100 hover:bg-cyan-50/30">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-3">
                    <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-800">
                        <UserRound className="size-5" />
                    </span>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-black tracking-tight text-slate-950">{character.name}</h2>
                            <StatusBadge tone="cyan">{character.slug}</StatusBadge>
                            <StatusBadge tone={statusTone(character.status)}>{statusLabel(character.status)}</StatusBadge>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            {character.role} · {languageLabel(character, languages)}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <StatusBadge>{character.scenarios_count} scenarios</StatusBadge>
                            <StatusBadge>{character.praise_lines.length} praise lines</StatusBadge>
                            <StatusBadge>{character.encouragement_lines.length} encouragement lines</StatusBadge>
                            <StatusBadge>sort {character.sort_order}</StatusBadge>
                        </div>
                    </div>
                </div>
                <SecondaryButton icon={Edit3} onClick={onEdit}>Edit</SecondaryButton>
            </div>
        </article>
    );
}

function CharacterForm({ character, languages, statuses, onSuccess }: { character?: CharacterRecord; languages: LanguageOption[]; statuses: string[]; onSuccess: () => void }) {
    const form = useForm({
        language_id: character?.language_id ?? languages[0]?.id ?? emptyCharacter.language_id,
        slug: character?.slug ?? emptyCharacter.slug,
        name: character?.name ?? emptyCharacter.name,
        role: character?.role ?? emptyCharacter.role,
        image_path: character?.image_path ?? emptyCharacter.image_path,
        intro: character?.intro ?? emptyCharacter.intro,
        praise_lines: character?.praise_lines.join('\n') ?? emptyCharacter.praise_lines,
        encouragement_lines: character?.encouragement_lines.join('\n') ?? emptyCharacter.encouragement_lines,
        sort_order: character?.sort_order ?? emptyCharacter.sort_order,
        status: character?.status ?? emptyCharacter.status,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!character) form.reset();
                onSuccess();
            },
        };

        if (character) {
            form.put(`/cms/characters/${character.slug}`, options);
            return;
        }

        form.post('/cms/characters', options);
    }

    return (
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
            <TextField
                label="Slug"
                placeholder="rasa"
                help="Stable character identifier used in routes, seeders, and scenario relationships."
                value={form.data.slug}
                onChange={(value) => form.setData('slug', value)}
                error={form.errors.slug}
            />
            <TextField
                label="Name"
                placeholder="Rasa"
                help="Display name shown to CMS editors and learners."
                value={form.data.name}
                onChange={(value) => form.setData('name', value)}
                error={form.errors.name}
            />
            <TextField
                label="Role"
                placeholder="Waiter, pharmacist, neighbour"
                help="The character's social role in lessons."
                value={form.data.role}
                onChange={(value) => form.setData('role', value)}
                error={form.errors.role}
            />
            <SelectField
                label="Language"
                help="Target language this character belongs to."
                value={form.data.language_id}
                onChange={(value) => form.setData('language_id', Number(value))}
                options={languages.map((language) => ({ value: language.id, label: `${language.name} (${language.code})` }))}
                error={form.errors.language_id}
            />
            <SelectField
                label="Status"
                help="Draft hides unfinished characters, Active makes them usable, Archived keeps old records without using them for new content."
                value={form.data.status}
                onChange={(value) => form.setData('status', String(value))}
                options={statuses.map((status) => ({ value: status, label: statusLabel(status) }))}
                error={form.errors.status}
            />
            <TextField
                label="Image path"
                placeholder="/images/characters/rasa.png"
                help="Optional image asset path for this character."
                value={form.data.image_path}
                onChange={(value) => form.setData('image_path', value)}
                error={form.errors.image_path}
            />
            <TextField
                label="Sort"
                type="number"
                placeholder="0"
                help="Lower numbers appear first in character lists."
                value={form.data.sort_order}
                onChange={(value) => form.setData('sort_order', Number(value))}
                error={form.errors.sort_order}
            />
            <div className="md:col-span-2">
                <Textarea
                    label="Intro"
                    placeholder="A warm cafe worker who keeps beginner conversations simple."
                    help="Internal description of this character's speaking style and role."
                    value={form.data.intro}
                    onChange={(value) => form.setData('intro', value)}
                    error={form.errors.intro}
                    rows={3}
                />
            </div>
            <Textarea
                label="Praise lines"
                placeholder={'Puiku!\nLabai gerai!'}
                help="One short positive feedback line per row."
                value={form.data.praise_lines}
                onChange={(value) => form.setData('praise_lines', value)}
                error={form.errors.praise_lines}
                rows={4}
            />
            <Textarea
                label="Encouragement lines"
                placeholder={'Pabandykite dar kartą.\nBeveik pavyko.'}
                help="One short supportive retry line per row."
                value={form.data.encouragement_lines}
                onChange={(value) => form.setData('encouragement_lines', value)}
                error={form.errors.encouragement_lines}
                rows={4}
            />
            <div className="md:col-span-2">
                <PrimaryButton type="submit" icon={Save} disabled={form.processing}>
                    {character ? 'Save character' : 'Create character'}
                </PrimaryButton>
            </div>
        </form>
    );
}

function languageLabel(character: CharacterRecord, languages: LanguageOption[]): string {
    const language = languages.find((item) => item.id === character.language_id);

    return language ? `${language.name} (${language.code})` : 'No language';
}

function statusLabel(status: string): string {
    if (status === 'active' || status === 'published') return 'Active';
    if (status === 'archived') return 'Archived';

    return 'Draft';
}

function statusTone(status: string): 'emerald' | 'rose' | 'slate' {
    if (status === 'active' || status === 'published') return 'emerald';
    if (status === 'archived') return 'rose';

    return 'slate';
}
