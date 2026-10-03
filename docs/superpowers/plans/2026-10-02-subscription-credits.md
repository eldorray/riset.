# Subscription Credits Implementation Plan

**Goal:** Ship the approved manual subscription and metered-credit system for users and admins.
**Architecture:** Persist plan snapshots, purchase requests, dated credit grants and transaction history. Serialize balance changes on the user row. Meter each AI call centrally, reserving a conservative maximum before HTTP and settling provider usage only after feature validation succeeds. Persisted article notes are a separate successful operation.
**Tech Stack:** Laravel migrations/query builder, Svelte 5, Inertia, Pest.

- [x] Add tables and user access flags; seed three plans and retain existing users without paid access.
- [x] Implement subscription service: request/approve/reject with idempotence, month-end-safe queued renewals, top-ups, date edits, audit entries and unlimited.
- [x] Implement credit reservations/refunds and AI middleware. Add token usage handling in AiClient and discard failed retries. Checkpoint successful automatic article reading.
- [x] Add user subscription page and admin billing page, links, forms, status and history.
- [x] Write billing regression tests for access, lifecycle, snapshots, isolation, usage, refund and reservations; run relevant legacy tests with explicit unlimited test fixtures.
- [x] Run PHP/Svelte types, full tests and frontend build. Apply migration after verification; no implicit free/unlimited grant to production users.

**Verification:** 169 tests passed; PHPStan, Svelte type checks and production build passed. Browser verified purchase request, admin activation, permanent unlimited and user history with temporary accounts; those accounts were removed. Migration applied after SQLite backup. Provider token accounting verified with mocked usage, not a live paid API call.
