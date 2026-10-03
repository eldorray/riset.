# Title Brainstorming Implementation Plan

**Goal:** Implement the approved three-turn title discussion below the project form.
**Architecture:** An authenticated, rate-limited JSON endpoint reuses AiClient. A Svelte component owns transient conversation state and sends the chosen title to the existing form.
**Tech Stack:** Laravel, Pest, Svelte 5, Inertia useHttp.

- [x] Add tests in tests/Feature/BrainstormTest.php covering intermediate and final responses, invalid inputs and AI responses, failures, and authentication. Run `php artisan test --compact tests/Feature/BrainstormTest.php` and confirm the missing endpoint fails.
- [x] Add app/Http/Controllers/BrainstormController.php with validated input, enum document types, three-turn prompt, response validation, and AiException handling. Register POST /projects/brainstorm before project parameter routes with throttle:ai.
- [x] Add resources/js/components/TitleBrainstorm.svelte with sequential questions, feedback, retry, reset, progress status, and title selection. Wrap the existing project form and this component in one sidebar column in resources/js/pages/projects/Index.svelte.
- [x] Run the feature tests, `npm run types:check`, and `npm run build`. Fix failures attributable to the change. Commit is unavailable because this workspace has no .git directory.

Verification: 23 feature tests passed; Svelte check found zero errors or warnings; frontend build passed. Browser inspection reached the login page because the testing browser has no authenticated session; visual interaction checks remain unverified. AI integration tested using fake responses, not a live provider.
