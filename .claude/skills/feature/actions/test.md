# Test Action

1. Read current-feature.md to understand what was implemented
2. Identify backend logic (controllers/services/jobs/commands in `api/`) and frontend utilities (composables/Pinia stores/`web/src/utils/`) added/modified for this feature
3. Check if tests already exist for these targets
4. For logic without tests that has testable behavior, write unit/feature tests:
   - Backend (`api/`): PHPUnit, placed under `api/tests/Feature` or `api/tests/Unit` (the existing Laravel test layout). Frontend (`web/`) has no test runner wired up — skip unless one is added.
   - Focus on controllers/services/jobs/commands (api) and composables/stores/utils (web), not Vue components
   - Test happy path and error cases
   - Do not write tests just to write them. Use your best judgement
5. Run `cd api && php artisan test` to verify all backend tests pass
6. Report test coverage for the new feature code