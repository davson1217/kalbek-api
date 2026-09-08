# Kalbek: Path To Becoming Full-Scale

Kalbek is still an MVP, but it is no longer a throwaway prototype. It is best described as a production-capable MVP or private beta product: enough to test with real learners, validate the core speaking loop, and learn from usage, but not yet mature enough to scale without close monitoring, content QA, and operational hardening.

## Current MVP Shape

Kalbek currently proves the core product loop:

1. A learner chooses a scenario.
2. The app presents an NPC line.
3. The learner records a spoken response.
4. The backend transcribes and judges the response.
5. The app advances through authored scenario goals and replies.
6. The learner receives feedback and a final speaking summary.

The product also has meaningful infrastructure beyond a basic MVP:

- Laravel owns the backend and database.
- The CMS manages languages, scenarios, scenes, goals, NPC lines, translations, and characters.
- Auth includes email/password, Google OAuth, password reset, and welcome emails.
- Subscriptions are integrated through Stripe.
- Redis supports fast cache lookups for generated audio metadata.
- Generated audio is stored on configurable object storage such as S3/R2.
- The frontend supports mobile browsers, target language selection, app language, optional translations, and configurable success feedback.

## Why It Is Still An MVP

The core product assumptions are still being tested:

- Whether learners enjoy and trust the speaking loop.
- Whether the judge feedback feels accurate, helpful, and human.
- Whether authored content can scale without conversation incoherence.
- Whether latency around speech, transcription, judging, and audio playback is acceptable.
- Whether learners are willing to pay for this learning format.
- Whether teachers can comfortably use the CMS to create high-quality content.

Until those are validated with real users, Kalbek remains an MVP even though it already has production-oriented architecture.

## What Is Missing For Full Scale

### Content Depth

Kalbek needs substantially more reviewed content:

- More scenarios across CEFR levels.
- More NPC line variations per scene and goal.
- Teacher-reviewed Lithuanian phrasing.
- Clear progression from beginner to advanced speaking tasks.
- Domain-specific scenario packs such as travel, work, school, healthcare, immigration, social life, and interviews.

### Learning Progression

The product needs a stronger learning system:

- A structured curriculum.
- “Continue where I left off.”
- Placement or onboarding diagnostics.
- Skill paths tied to grammar, vocabulary, pronunciation, and coherence.
- Spaced repetition for weak areas.
- Long-term CEFR progression evidence.

### Speech Quality And Pronunciation

Current speech handling is useful but early-stage. Full scale needs:

- Better pronunciation scoring.
- More robust speech normalization.
- Audio quality guidance before recording.
- Confidence-aware transcription handling.
- Clear separation between “what was heard” and “what would be more natural.”

### Feedback UX

Feedback must become more learner-centered:

- Optional success feedback is a good start.
- Failure feedback should remain mandatory.
- Final summaries should become richer over time.
- Historical feedback should be available as progress evidence.
- Feedback should adapt to learner level and app language.

### CMS Maturity

The CMS needs production editorial workflows:

- Draft, review, publish, and archive flows.
- Teacher/editor roles and permissions.
- Scenario versioning.
- Content QA checks before publishing.
- Preview mode for learner experience.
- Import/export tooling.
- Translation management per supported app language.

### Flow QA

Scenario coherence must be protected automatically:

- Detect unreachable scenes.
- Detect goals without replies.
- Detect reply lines tied to the wrong goal.
- Detect hardcoded learner-specific assumptions.
- Detect missing translations.
- Detect inappropriate CEFR difficulty.
- Run content audits before publishing.

### Subscription Maturity

Stripe integration needs hardening for real billing operations:

- Webhook resilience and replay handling.
- Billing portal edge cases.
- Subscription status reconciliation.
- Trial abuse prevention.
- Plan management.
- Cancellation and renewal UX.
- Invoice and payment failure communication.

### Observability And Reliability

Production scale needs stronger operations:

- Structured logs.
- Error monitoring.
- Queue workers for slow or retryable work.
- Alerts for AI, storage, Redis, email, and Stripe failures.
- Storage cleanup policies.
- Rate limits and abuse protection.
- Health checks and deployment checks.

### Security And Compliance

Before wider launch, Kalbek needs:

- Privacy policy.
- Terms of service.
- Data retention rules.
- Account deletion.
- Export/delete user data flows.
- GDPR posture.
- Clear handling of recorded audio and transcripts.

### Multi-Language Expansion

The architecture has started moving toward multiple target languages, but full scale requires:

- Localized UI per app language.
- Localized feedback prompts.
- Target-language-specific voices.
- Per-language content QA.
- Per-language teacher workflows.
- Language-specific CEFR interpretation where needed.

### Mobile Product

Mobile web is currently supported. Native app-store shipping remains a future epic:

- Mobile-native interaction patterns.
- App-store packaging.
- Push notifications.
- Native audio permission handling.
- Offline or low-connectivity behavior.
- Mobile-specific onboarding and lesson navigation.

## Recommended Path

The next path should prioritize product validity before broad platform expansion:

1. Stabilize conversation flow and speech feedback quality.
2. Expand and review content for Lithuanian A1-A2.
3. Add analytics for learner behavior and drop-off.
4. Harden billing, storage, email, and monitoring.
5. Improve CMS editorial workflows.
6. Run private beta with real learners and teachers.
7. Use beta evidence to refine learning progression.
8. Revisit native mobile once the web product proves retention and willingness to pay.

## Product Stage Summary

Kalbek is not yet a full-scale learning platform. It is a production-capable MVP with the right architectural direction and enough feature coverage to support private beta testing.

The goal now is not to add every possible feature. The goal is to prove that learners trust the speaking experience, complete scenarios, improve over time, and are willing to subscribe.
