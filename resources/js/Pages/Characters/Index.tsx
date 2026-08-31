import { Head, useForm } from '@inertiajs/react';

import { CmsLayout, FieldError, InputLabel } from '../../Layouts/CmsLayout';
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
    const create = useForm({ ...emptyCharacter, language_id: languages[0]?.id ?? 0 });

    return (
        <CmsLayout>
            <Head title="Characters" />
            <div>
                <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">Characters</p>
                <h1 className="text-3xl font-semibold tracking-tight">Scenario cast</h1>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    create.post('/cms/characters', { onSuccess: () => create.reset() });
                }}
                className="mt-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 lg:grid-cols-4"
            >
                <TextField label="Slug" value={create.data.slug} onChange={(value) => create.setData('slug', value)} error={create.errors.slug} />
                <TextField label="Name" value={create.data.name} onChange={(value) => create.setData('name', value)} error={create.errors.name} />
                <TextField label="Role" value={create.data.role} onChange={(value) => create.setData('role', value)} error={create.errors.role} />
                <LanguageSelect value={create.data.language_id} languages={languages} onChange={(value) => create.setData('language_id', Number(value))} />
                <SelectField label="Status" value={create.data.status} onChange={(value) => create.setData('status', value)} options={statuses} />
                <div className="lg:col-span-4">
                    <button className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white" type="submit">
                        Create character
                    </button>
                </div>
            </form>

            <div className="mt-6 space-y-4">
                {characters.map((character) => (
                    <CharacterForm key={character.id} character={character} statuses={statuses} languages={languages} />
                ))}
            </div>
        </CmsLayout>
    );
}

function CharacterForm({ character, statuses, languages }: { character: CharacterRecord; statuses: string[]; languages: LanguageOption[] }) {
    const form = useForm({
        language_id: character.language_id ?? languages[0]?.id ?? 0,
        slug: character.slug,
        name: character.name,
        role: character.role,
        image_path: character.image_path ?? '',
        intro: character.intro ?? '',
        praise_lines: character.praise_lines.join('\n'),
        encouragement_lines: character.encouragement_lines.join('\n'),
        sort_order: character.sort_order,
        status: character.status,
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/cms/characters/${character.slug}`);
            }}
            className="rounded-lg border border-slate-200 bg-white p-4"
        >
            <div className="grid gap-3 lg:grid-cols-4">
                <TextField label="Slug" value={form.data.slug} onChange={(value) => form.setData('slug', value)} error={form.errors.slug} />
                <TextField label="Name" value={form.data.name} onChange={(value) => form.setData('name', value)} error={form.errors.name} />
                <TextField label="Role" value={form.data.role} onChange={(value) => form.setData('role', value)} error={form.errors.role} />
                <LanguageSelect value={form.data.language_id} languages={languages} onChange={(value) => form.setData('language_id', Number(value))} />
                <SelectField label="Status" value={form.data.status} onChange={(value) => form.setData('status', value)} options={statuses} />
                <TextField label="Image path" value={form.data.image_path} onChange={(value) => form.setData('image_path', value)} />
                <TextField label="Sort" type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} />
                <div className="lg:col-span-4">
                    <InputLabel>Intro</InputLabel>
                    <textarea value={form.data.intro} onChange={(event) => form.setData('intro', event.target.value)} className="mt-1 min-h-20 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div className="lg:col-span-2">
                    <InputLabel>Praise lines</InputLabel>
                    <textarea value={form.data.praise_lines} onChange={(event) => form.setData('praise_lines', event.target.value)} className="mt-1 min-h-24 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
                <div className="lg:col-span-2">
                    <InputLabel>Encouragement lines</InputLabel>
                    <textarea value={form.data.encouragement_lines} onChange={(event) => form.setData('encouragement_lines', event.target.value)} className="mt-1 min-h-24 w-full rounded-md border border-slate-300 px-3 py-2" />
                </div>
            </div>
            <button className="mt-4 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white" type="submit">
                Save character
            </button>
        </form>
    );
}

function LanguageSelect({ value, languages, onChange }: { value: number; languages: LanguageOption[]; onChange: (value: string) => void }) {
    return (
        <label className="block">
            <InputLabel>Language</InputLabel>
            <select value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                {languages.map((language) => <option key={language.id} value={language.id}>{language.name} ({language.code})</option>)}
            </select>
        </label>
    );
}

function TextField({ label, value, onChange, error, type = 'text' }: { label: string; value: string | number; onChange: (value: string) => void; error?: string; type?: string }) {
    return (
        <label className="block">
            <InputLabel>{label}</InputLabel>
            <input value={value} onChange={(event) => onChange(event.target.value)} type={type} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
            <FieldError message={error} />
        </label>
    );
}

function SelectField({ label, value, onChange, options }: { label: string; value: string; onChange: (value: string) => void; options: string[] }) {
    return (
        <label className="block">
            <InputLabel>{label}</InputLabel>
            <select value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                {options.map((option) => <option key={option} value={option}>{option}</option>)}
            </select>
        </label>
    );
}
