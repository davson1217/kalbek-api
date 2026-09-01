export interface SharedProps extends Record<string, unknown> {
    auth: {
        user: null | {
            id: number;
            name: string;
            email: string;
            role: string;
        };
    };
    flash: {
        success?: string | null;
    };
}

export interface CharacterOption {
    id: number;
    language_id: number | null;
    name: string;
    slug: string;
}

export interface LanguageOption {
    id: number;
    code: string;
    name: string;
    native_name: string;
    support_language_code: string;
    support_language_name: string;
}

export interface LanguageRecord extends LanguageOption {
    status: string;
    support_language_code: string;
    support_language_name: string;
    default_voice: string | null;
    sort_order: number;
    characters_count: number;
    scenarios_count: number;
    updated_at: string | null;
}

export interface CharacterRecord extends CharacterOption {
    language: LanguageOption | null;
    role: string;
    image_path: string | null;
    intro: string | null;
    praise_lines: string[];
    encouragement_lines: string[];
    sort_order: number;
    status: string;
    scenarios_count: number;
}

export interface ScenarioSummary {
    id: number;
    slug: string;
    title: string;
    subtitle: string;
    description: string;
    emoji: string;
    tone: string;
    cefr_level: string | null;
    start_scene_slug: string | null;
    status: string;
    is_free: boolean;
    sort_order: number;
    language_id: number;
    language: LanguageOption | null;
    character_id: number;
    character: string | null;
    scenes_count: number | null;
    updated_at: string | null;
}

export interface ScenarioDetail extends ScenarioSummary {
    scenes: SceneRecord[];
}

export interface ScenarioAuditIssue {
    severity: 'critical' | 'warning';
    scope: 'scenario' | 'scene' | 'goal' | 'line';
    scenario_slug: string;
    scene_slug?: string;
    goal_slug?: string;
    line_id?: number;
    message: string;
}

export interface SceneRecord {
    id: number;
    slug: string;
    setting: string;
    cefr_level: string | null;
    sort_order: number;
    lines: NpcLineRecord[];
    goals: GoalRecord[];
    props: ScenePropRecord[];
}

export interface GoalRecord {
    id: number;
    slug: string;
    label: string;
    intent: string;
    example: string;
    cefr_level: string | null;
    next_scene_id: number | null;
    next_scene_slug: string | null;
    response_lines: NpcLineRecord[];
    sort_order: number;
}

export interface NpcLineRecord {
    id: number;
    target_text: string;
    support_translation: string;
    cefr_level: string | null;
    trigger_goal_id: string | null;
    trigger_goal_db_id: number | null;
    priority: number;
    sort_order: number;
}

export interface ScenePropRecord {
    id: number;
    type: string;
    target_text: string;
    support_translation: string;
    price: string | null;
    metadata: Record<string, unknown> | null;
    sort_order: number;
}
