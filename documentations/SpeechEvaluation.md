# Speech Evaluation

Kalbek uses speech evaluation to decide whether a learner's spoken answer satisfies the current scenario goal. This flow includes browser recording, speech-to-text, goal-aware interpretation, AI judging, and attempt storage.

The product rule is simple: authored CMS content remains the source of truth. AI helps understand and judge speech, but it should not invent the lesson flow or replace the active goal.

## Runtime Flow

1. The learner opens a scenario and reaches a scene.
2. The frontend shows the active `goals` choices for that scene.
3. The learner records audio in the browser.
4. The frontend calls `POST /api/v1/speak-check` with:
   - `scenario_id`: `scenarios.slug`
   - `scene_id`: `scenes.slug`
   - `goal_id`: `goals.slug`
   - `audio`: recorded audio blob
   - `intent`, `example`, and `context`: client context kept for compatibility and debugging
   - `audio_readiness`: optional browser-side audio quality metrics
5. `App\Http\Controllers\Api\V1\SpeechCheckController` validates that the scenario, scene, and goal belong together.
6. The backend loads the active `goals` row from MySQL.
7. The backend evaluates against the database-owned `goals.intent`, `goals.example`, and `goals.accepted_phrases`, not the client-sent copies.
8. `App\Services\Ai\SpeechEvaluator` sends the audio to the configured STT provider through Laravel AI transcription.
9. The STT provider returns a raw transcript.
10. `App\Services\Ai\SpeechInterpretation` creates a goal-aware interpreted transcript.
11. `App\Ai\Agents\SpeakingJudge` judges the interpreted transcript against the active goal.
12. `SpeechCheckController` stores the result in `speaking_attempts`.
13. `DialogueOrchestrator` advances the authored scene flow only when the result can continue.
14. The API returns the verdict, dialogue decision, transcript data, and feedback to the frontend.

## Main Components

### Browser Recorder

Frontend recording is handled in the Kalbek frontend, mainly around the scenario route and recorder utilities.

Responsibilities:

- request microphone permission;
- record learner speech;
- calculate optional audio readiness metrics;
- send the audio blob to `/api/v1/speak-check`;
- render feedback and progression.

The frontend should not decide whether an answer passes. It only records, sends, and displays the backend verdict.

### SpeechCheckController

Backend class: `App\Http\Controllers\Api\V1\SpeechCheckController`

Responsibilities:

- authenticate the learner;
- enforce subscription/trial access before spending AI tokens;
- validate that `scenario_id`, `scene_id`, and `goal_id` form a valid authored path;
- resolve target language and feedback language;
- load CEFR context;
- call the speech evaluator;
- store the speaking attempt;
- call the dialogue orchestrator.

Important behavior: the controller stores client-sent `intent` and `example` as metadata, but it evaluates using the database-owned goal fields. This prevents stale frontend payloads from steering the judge.

### Transcription

Laravel AI entry point: `Laravel\Ai\Transcription`

Configuration:

- `ai.default_for_transcription`
- `services.kalbek.transcription_model`
- `KALBEK_TRANSCRIPTION_MODEL`

The transcription step returns the raw STT transcript. This is useful for debugging, but it is not automatically treated as the learner's final displayed answer because STT can mishear Lithuanian beginner speech.

Example:

```text
Raw transcript: Quo tu vardu?
```

The raw transcript is stored in `speaking_attempts.transcript`.

### SpeechInterpretation

Backend class: `App\Services\Ai\SpeechInterpretation`

This layer sits between STT and the judge. Its purpose is not to globally rewrite user speech. It is a bounded guardrail for obvious STT misses when the current goal provides enough context.

Inputs:

- raw transcript from STT;
- `goals.example`;
- `goals.accepted_phrases`;
- feedback language.

Output:

- `transcript`: interpreted transcript used for judging;
- `confidence`: `high`, `medium`, or `low`;
- `note`: optional learner/support note;
- `matched_phrase`: accepted phrase matched, when applicable;
- `source`: currently `accepted_phrase` or `transcript`.

The interpreter compares the raw transcript only against `goals.accepted_phrases`, not against every phrase in the system. This avoids an unbounded hardcoded normalization problem.

Example:

```text
Goal accepted phrases:
- Koks jūsų vardas?
- Kuo jūs vardu?
- Kuo tu vardu?

Raw transcript:
Quo tu vardu?

Interpreted transcript:
Kuo tu vardu?
```

The frontend labels this as `Understood as`, not `You said`, because the text may include interpretation.

### Accepted Phrases

Database column: `goals.accepted_phrases`

This is a nullable JSON array of valid target-language phrases for one exact goal.

Use accepted phrases when the goal has a small, controlled answer range:

- greetings;
- goodbyes;
- fixed classroom phrases;
- yes/no answers;
- payment phrases;
- short menu-ordering phrases.

Avoid accepted phrases for open answers:

- names;
- countries;
- cities;
- professions where many valid answers are possible;
- personal preferences;
- free-form explanations.

Accepted phrases help with:

- recovering close STT misses;
- making strict mode meaningful;
- preventing unrelated suggestions;
- keeping the judge inside the active goal.

They do not replace semantic judging. For open goals, leave `accepted_phrases` empty and let the judge evaluate meaning.

### SpeakingJudge

Backend class: `App\Ai\Agents\SpeakingJudge`

The judge receives:

- situation/context;
- target language;
- feedback language;
- active goal intent;
- reference learner phrase;
- accepted phrases for the exact goal;
- content CEFR level;
- learner's estimated CEFR level;
- raw transcript;
- interpreted transcript;
- interpretation confidence;
- strict mode instruction.

Important judge rules:

- judge spoken meaning, not spelling, casing, or punctuation;
- never expose internal terms such as model answer, model list, prompt, schema, or rubric;
- accept valid target-language alternatives when the goal is open;
- reason from the interpreted transcript when confidence is high or medium;
- request retry for low-confidence interpretation instead of punishing grammar;
- do not recommend the exact same sentence the learner already said.

### DialogueOrchestrator

Backend contract: `App\Contracts\DialogueOrchestratorContract`

The orchestrator does not judge language. It decides the next authored conversation step after the judge result.

Inputs:

- scenario;
- current scene;
- current goal;
- raw transcript;
- `canContinue`.

Output:

- matched goal;
- next scene;
- reply scene;
- reply trigger goal;
- completion status.

Important: AI does not choose the next scene. Authored `goals.next_scene_id` and `npc_lines.trigger_goal_id` control progression.

## Strict Mode

Strict mode is user-configurable and defaults off.

Normal mode:

- accepts understandable target-language answers that satisfy the goal;
- allows minor grammar and phrasing slips;
- allows socially natural variants.

Strict mode:

- is less forgiving within the same CEFR level;
- requires key semantic pieces of the goal;
- uses `goals.accepted_phrases` as a hard boundary when those phrases are configured.

Example:

```text
Goal: Say good morning
Accepted phrases:
- Labas rytas.
- Labas rytas!

Learner says:
Labas.

Normal mode:
The judge may treat it as a greeting but can suggest the morning phrase.

Strict mode:
The answer should not continue because it does not express "good morning".
```

## Storage

Speaking attempts are stored in `speaking_attempts`.

Important columns:

- `user_id`: learner.
- `scenario_id`: scenario being played.
- `scene_id`: active scene.
- `goal_id`: active goal.
- `transcript`: raw STT transcript.
- `passed`: whether the authored flow advanced.
- `feedback`: learner-facing feedback.
- `corrected_text`: interpreted/normalized transcript.
- `grammar_score`, `vocabulary_score`, `cohesion_score`, `task_completion_score`, `pronunciation_score`: scoring dimensions.
- `overall_score`: aggregated score.
- `attempt_cefr_level`: estimated level for that attempt.
- `metadata`: full evaluation context and additional details.

Important `metadata` fields:

- `evaluated_intent`: database-owned `goals.intent` used by the judge.
- `example`: database-owned `goals.example`.
- `client_intent`, `client_example`: client-sent compatibility/debug values.
- `accepted_phrases`: active goal accepted phrases.
- `strict_speech_mode`: whether strict mode was enabled.
- `normalized_transcript`: interpreted transcript shown as `Understood as`.
- `normalization_confidence`: confidence after interpretation and judge normalization.
- `normalization_note`: optional note for uncertain interpretation.
- `suggested_response`: optional phrase the learner can try.
- `communication`: intent match, understood meaning, off-script flag, and improvement focus.
- `interpretation`: confidence, source, and matched phrase.
- `dialogue`: authored flow decision.

## Frontend Display Rules

The frontend should display the interpreted text as:

```text
Understood as: "..."
```

Do not label interpreted text as:

```text
You said: "..."
```

Reason: STT and interpretation can produce a cleaned or likely version of the learner's speech. `Understood as` is more honest and reduces distrust when STT makes minor Lithuanian mistakes.

Suggested responses must be displayed as suggestions only:

- `Try saying: ...`
- `More natural: ...`

Never display `suggested_response` as if it were the learner's own words.

## CMS Usage

In the CMS, each learner goal has:

- `Goal slug`: internal stable identifier.
- `Label`: short editor/learner label.
- `What the learner is trying to say`: meaning to evaluate.
- `Example learner phrase`: one good reference phrase.
- `Accepted phrases`: optional one-phrase-per-line list of valid variants.
- `Next scene`: authored progression after success.

Accepted phrases should be added only when editors want a controlled set of valid answers.

Example CMS entry:

```text
Goal: Say goodbye
Example learner phrase:
Viso gero.

Accepted phrases:
Viso gero.
Iki.
Iki pasimatymo.
```

For open answers, leave `Accepted phrases` empty:

```text
Goal: Say your name
Example learner phrase:
Mano vardas Deividas.

Accepted phrases:
[empty]
```

This tells the judge to accept real names rather than forcing every learner toward the example name.

## Token Cost Boundary

This flow spends AI tokens in live mode for:

- STT transcription;
- SpeakingJudge evaluation.

`SpeechInterpretation` itself does not call an AI provider. It is local PHP logic.

Fake/dev mode can bypass live provider calls for deterministic local testing.

## Current Limitations

- Interpretation is intentionally conservative and does not solve all pronunciation/STT errors.
- Accepted phrases must be authored carefully; poor phrase lists can make strict mode too rigid.
- Open-ended answers still depend heavily on STT quality and judge quality.
- Pronunciation scoring is not yet true acoustic scoring; pronunciation should remain `null` unless audio-level evidence is available.

## Recommended Improvements

- Add CMS validation hints before save for goals with overly broad or overly narrow accepted phrase lists.
- Store STT provider/model per attempt for easier QA comparison.
- Add provider-level transcription experiments for Lithuanian.
- Consider a future AI-assisted interpretation layer only after enough real attempts expose repeatable STT failure patterns.
- Add per-goal QA fixtures so content editors can test expected pass/fail examples without spending tokens.
