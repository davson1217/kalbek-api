# Scenarios

Kalbek scenarios are authored speaking journeys. They describe what a learner sees, what the character says, what the learner is expected to communicate, and how the conversation advances after a successful response.

The business goal is predictable language practice: teachers and content editors should be able to design realistic role-play flows without relying on an AI agent to invent the conversation. AI supports transcription and judging, but authored CMS content remains the source of truth for sequence, goals, and character replies.

## Core Concepts

### Language

A language defines the course language being practised, for example Lithuanian or English.

Database: `languages`

Important columns:

- `code`: target language code, for example `lt` or `en`.
- `name`: language name in the CMS/API.
- `native_name`: language name in its own language.
- `support_language_code`: translation/support language, English by default.
- `support_language_name`: display name for the support language.
- `default_voice`: default TTS voice when configured.
- `status`: whether the language is available.

### Character

A character is the role-play speaker, such as a waiter, pharmacist, or friendly Lithuanian speaker. Characters provide personality and image assets, but they do not determine flow by themselves.

Database: `characters`

Important columns:

- `language_id`: optional language ownership.
- `slug`: stable identifier used by CMS/API code.
- `name`: display name.
- `role`: business-facing role, for example `Waiter`.
- `image_path`: character image path.
- `intro`, `praise_lines`, `encouragement_lines`: supporting UX copy.
- `status`, `sort_order`: CMS availability and ordering.

### Scenario

A scenario is the complete lesson container, for example “At the restaurant” or “At the shop.” It owns scenes and determines the first scene.

Database: `scenarios`

Important columns:

- `language_id`: target language for the scenario.
- `character_id`: character used in the scenario.
- `slug`: public stable identifier used by API routes and frontend URLs.
- `title`, `subtitle`, `description`, `emoji`, `tone`: learner-facing presentation.
- `cefr_level`: expected content level, for example `a1`.
- `start_scene_slug`: slug of the first scene the learner enters.
- `status`: draft or published content state.
- `is_free`: whether this scenario counts as a free scenario.
- `sort_order`, `published_at`: ordering and release metadata.

Business rule: a published scenario should have a valid `start_scene_slug`, at least one scene, and a coherent CEFR level.

### Scene

A scene is one step in the conversation. It represents a local situation inside the scenario, such as greeting, ordering, payment, or farewell.

Database: `scenes`

Important columns:

- `scenario_id`: owner scenario.
- `slug`: stable scene identifier, unique within the scenario.
- `setting`: context sent to the judge, for example “The learner is greeting a pharmacist.”
- `cefr_level`: difficulty for this scene.
- `sort_order`: CMS/API ordering.

Business rule: a scene normally has opening character lines and learner goals. A scene with no goals behaves like a terminal scene if entered.

### Goal

A goal is what the learner is trying to communicate at a scene. It should be an intent, not an exact phrase requirement.

Database: `goals`

Important columns:

- `scene_id`: scene where the learner can choose this goal.
- `next_scene_id`: next scene after successful completion; nullable when the flow should end.
- `slug`: stable goal identifier, unique within the scene.
- `label`: short UI option shown to the learner.
- `intent`: judge-facing communicative goal.
- `example`: model answer or hint for the learner.
- `cefr_level`: expected difficulty for judging.
- `sort_order`: display order.

Business rule: the judge evaluates whether the learner satisfied `intent`, not whether they repeated `example`. For example, if the goal is “Say your name,” “My name is David but people call me Dazza” can satisfy the intent even though it adds extra social detail.

### NPC Line

An NPC line is a character message. “NPC” means non-player character: the app-controlled character in the role-play.

Database: `npc_lines`

Important columns:

- `scene_id`: scene where the line belongs.
- `trigger_goal_id`: nullable goal relationship.
- `target_text`: character line in the target language.
- `support_translation`: support-language translation.
- `cefr_level`: line difficulty.
- `priority`: higher-priority lines are preferred when multiple replies fit.
- `sort_order`: stable ordering among similar priority lines.

There are two kinds of NPC lines:

- Opening or generic lines: `trigger_goal_id` is `null`. These can be used when a scene opens.
- Goal replies: `trigger_goal_id` points to a goal in the same scene. These are used after the learner completes that goal.

Business rule: goal replies must stay valid for any learner answer that satisfies the goal. Avoid hardcoding values from the goal example unless the learner is explicitly required to say that value. For example, if the goal example says “I am from Nigeria,” the reply should not say “Nigeria is far from Lithuania” unless every learner is expected to say Nigeria.

### Scene Props

Scene props are supporting content attached to a scene, such as menu items or products. They provide context and vocabulary, but they do not drive conversation flow.

Database: `scene_props`

Important columns:

- `scene_id`: owner scene.
- `type`: prop type, for example `menu_item`.
- `target_text`: target-language label.
- `support_translation`: support translation.
- `price`: optional display price.
- `metadata`: optional structured details.
- `sort_order`: display order.

## Runtime Flow

1. The frontend loads a scenario by `scenarios.slug` from the API.
2. The frontend enters `scenarios.start_scene_slug`.
3. The scene selects an opening `npc_lines` record where `trigger_goal_id` is `null`.
4. The learner chooses one `goals` option and records speech.
5. The API transcribes the audio and asks the SpeakingJudge whether the transcript satisfies `goals.intent` at the relevant CEFR level.
6. If the response can continue, the authored flow advances by `goals.next_scene_id`.
7. The character reply is selected from `npc_lines` where `trigger_goal_id` matches the completed goal.
8. If there is no next scene, the conversation prepares to finish after the final reply.

Important: AI does not choose the next scene. Authored CMS relationships control progression.

## Speaking Judge Contract

The judge receives the scene context, current character line, learner goal, model example, CEFR levels, and transcript.

The judge returns language-quality information such as:

- `transcript`: what STT heard.
- `normalized_transcript`: conservative interpretation of likely speech-to-text artifacts.
- `pass`: whether the answer satisfied the language goal.
- `can_continue`: whether the conversation should advance.
- `should_retry`: whether the learner should try again first.
- `suggested_response`: a better phrase to try, not a replacement for what the learner said.
- `scores`: grammar, vocabulary, cohesion, task completion, and pronunciation when available.

Product rule: never display the suggested response as if it were the learner's own speech. It should be labelled as a suggestion, for example “Try saying...” or “More natural...”.

## Content QA

The service `App\Services\Content\AuditScenarioContent` audits scenario content without spending AI tokens.

It currently checks for:

- invalid or missing start scenes;
- scenarios without scenes;
- unreachable scenes;
- scenes without opening lines;
- scenes with no goals, which behave as terminal scenes;
- goals that link outside the scenario;
- goals without character replies;
- NPC replies attached to goals from another scene;
- missing or inconsistent CEFR levels;
- empty required content fields;
- replies that appear to hardcode values from goal examples.

Run from CLI:

```bash
php artisan kalbek:audit-content
php artisan kalbek:audit-content --fail
```

In the CMS, open a scenario and use the Flow QA panel. Critical issues should block publishing. Warnings identify content quality risks that can make conversations feel unnatural or too difficult.

## Authoring Guidelines

- Write goals as communicative intentions, not exact string matches.
- Keep A1 goals short, practical, and concrete.
- Provide multiple NPC reply variants when the same intent can be answered naturally in several ways.
- Keep replies tied to the current goal. Do not use generic replies when a goal-specific reply is needed.
- Avoid learner-specific assumptions in replies unless the goal requires that specific answer.
- Set CEFR levels on scenario, scene, goal, and NPC lines so future learner-level logic can select content safely.
- Use scene props for visible vocabulary/context, not for conversation control.

## Suggested Documentation Backlog

The `documentations` directory should also include:

- `Authentication.md`: email/password auth, Google OAuth, password reset, welcome email, token handling.
- `Subscriptions.md`: trial rules, free scenario limits, Stripe checkout, webhooks, grace period, access decisions.
- `AI.md`: provider configuration, fake/live mode, STT, TTS, SpeakingJudge, strict mode, token-cost boundaries.
- `Audio.md`: browser microphone requirements, audio readiness checks, mobile playback constraints, TTS byte-range support.
- `CEFR.md`: scoring dimensions, learner level recalculation, evidence windows, how levels influence judging.
- `CMS.md`: content editor workflows, roles/permissions, publish checklist, scenario QA workflow.
- `Deployment.md`: environment variables, production readiness command, staging vs production setup.
- `API.md`: public frontend API routes, request/response contracts, error handling.
