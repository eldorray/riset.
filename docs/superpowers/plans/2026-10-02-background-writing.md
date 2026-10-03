# Background Writing Implementation Plan

**Goal:** Continue AI drafting and manuscript generation independently of browser navigation.
**Architecture:** Database-backed writing runs and one queued job per section, using existing generation actions. Persist suggestions separately, poll status from the UI, and pass the billing actor explicitly in worker context.
**Tech Stack:** Laravel database queue, Eloquent, Svelte 5, Pest.

- [x] Add writing runs and billing linkage, explicit worker billing context, dedicated queue connection with timeout-safe retry interval.
- [x] Add authorized start/status/stop/review endpoints and step execution with snapshot conflict checks, idempotent claims, persisted results and failed-job refunds.
- [x] Replace browser generation loops with server starts/status polling; restore suggestions and progress after navigation. Keep manual-edit navigation guards.
- [x] Add project progress indicator and cancellation; restore manuscript previews when safe.
- [x] Verify access isolation, duplicate starts/jobs, persisted suggestions, conflicts, cancellation, failures and worker billing with Pest tests.
- [x] Run full tests, PHP/Svelte checks and build; migrate with backup, run local worker, verify browser navigation and document deployment.

No Git repository is present; changes remain in the current workspace. Execute inline using the approved design.

Verification: queue, access isolation, explicit worker billing, cancellation, duplicate delivery, pending suggestions, edit conflicts, stale reservation refunds, outline creation and complete article covered by WritingTest. Browser used a temporary account and isolated fake provider/queue to verify menu navigation, reload, accepting draft, full manuscript completion, and editing one abstract without changing the other. Temporary account/provider/queue removed. Dedicated local queue listener started; production process-manager command documented.

Review: fresh reviewer found stale front-matter saves and malformed failover configuration. Locked baseline merge and changed-parts frontend submission fixed the first; nested configuration removed for the second.
