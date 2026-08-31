import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';

import { blankOptions, SelectField, Textarea, TextField } from '../FormControls';
import { PrimaryButton } from '../PageChrome';
import type { CharacterOption, LanguageOption, ScenarioDetail, ScenarioSummary } from '../../../types';

type ScenarioFormRecord = ScenarioDetail | ScenarioSummary;

export function ScenarioForm({ action, characters, languages, levels, method = 'post', onSuccess, scenario, statuses }: { action: string; characters: CharacterOption[]; languages: LanguageOption[]; levels: string[]; method?: 'post' | 'put'; onSuccess?: () => void; scenario?: ScenarioFormRecord; statuses: string[] }) {
    const form = useForm({
        language_id: scenario?.language_id ?? languages[0]?.id ?? 0,
        character_id: scenario?.character_id ?? characters[0]?.id ?? 0,
        slug: scenario?.slug ?? '',
        title: scenario?.title ?? '',
        subtitle: scenario?.subtitle ?? '',
        description: scenario?.description ?? '',
        emoji: scenario?.emoji ?? '💬',
        tone: scenario?.tone ?? 'primary',
        cefr_level: scenario?.cefr_level ?? 'a1',
        start_scene_slug: scenario?.start_scene_slug ?? '',
        status: scenario?.status ?? 'draft',
        sort_order: scenario?.sort_order ?? 0,
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form[method](action, { preserveScroll: true, onSuccess });
            }}
            className="grid gap-4 md:grid-cols-2"
        >
            <TextField label="Title" placeholder="At the restaurant" value={form.data.title} onChange={(value) => form.setData('title', value)} error={form.errors.title} />
            <TextField label="Slug" placeholder="restaurant-visit" help="A short web-safe name used by the app. Use lowercase letters, numbers, and hyphens. Editors can think of it as the scenario's internal nickname." value={form.data.slug} onChange={(value) => form.setData('slug', value)} error={form.errors.slug} />
            <TextField label="Subtitle" placeholder="Order food and ask for a table" value={form.data.subtitle} onChange={(value) => form.setData('subtitle', value)} error={form.errors.subtitle} />
            <SelectField label="Language" help="The target language learners practise in this scenario. This controls API filtering, transcription, and future voice selection." value={form.data.language_id} onChange={(value) => form.setData('language_id', Number(value))} options={languages.map((language) => ({ value: language.id, label: `${language.name} (${language.code})` }))} />
            <SelectField label="Character" help="The person the learner speaks with in this scenario." value={form.data.character_id} onChange={(value) => form.setData('character_id', Number(value))} options={characters.map((character) => ({ value: character.id, label: character.name }))} />
            <SelectField label="CEFR" help="The target difficulty for this scenario. Learners are still evaluated over time; this setting only describes the content level." value={form.data.cefr_level} onChange={(value) => form.setData('cefr_level', String(value))} options={blankOptions(levels, 'Unset')} />
            <SelectField label="Status" help="Draft content is kept out of learner-facing flows. Published content can be served by the app." value={form.data.status} onChange={(value) => form.setData('status', String(value))} options={statuses.map((status) => ({ value: status, label: status }))} />
            <SelectField label="Tone" help="A visual theme used by the learner app for this scenario card." value={form.data.tone} onChange={(value) => form.setData('tone', String(value))} options={['primary', 'amber', 'berry', 'sky', 'mint'].map((tone) => ({ value: tone, label: tone }))} />
            <TextField label="Emoji" placeholder="🍽️" help="A quick visual marker for the scenario card." value={form.data.emoji} onChange={(value) => form.setData('emoji', value)} />
            <TextField label="Start scene slug" placeholder="arrival" help="The slug of the first scene learners should enter. Leave blank until scenes have been created." value={form.data.start_scene_slug} onChange={(value) => form.setData('start_scene_slug', value)} />
            <TextField label="Sort" placeholder="0" help="Controls display order. Lower numbers appear earlier." type="number" value={form.data.sort_order} onChange={(value) => form.setData('sort_order', Number(value))} />
            <div className="md:col-span-2">
                <Textarea label="Description" placeholder="The learner arrives at a restaurant, asks for a table, reads the menu, and places a simple order." help="A short editor-facing summary of what the learner practices in this scenario." rows={4} value={form.data.description} onChange={(value) => form.setData('description', value)} error={form.errors.description} />
            </div>
            <div className="md:col-span-2">
                <PrimaryButton type="submit" icon={Save} disabled={form.processing}>{scenario ? 'Save scenario' : 'Create scenario'}</PrimaryButton>
            </div>
        </form>
    );
}
