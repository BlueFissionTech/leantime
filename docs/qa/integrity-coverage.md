# Read-only database integrity coverage review

Related: #5; extracted from #12. This branch adds read-only orphan diagnostics
and strict coverage reporting. It does not change import/export execution or
replace the separate import-safety work in #21.

## Acceptance

- Missing prerequisite tables or columns produce named skipped checks with
  unknown counts, never an implicit zero-error result.
- `--fail-on-errors` fails for missing tables, detected orphans, or incomplete
  coverage. Advisory mode still reports incomplete coverage explicitly.
- JSON includes `coverageComplete` and `incompleteChecks`; text identifies
  unknown counts. No repair or database writes are performed.

## Validation

With existing development dependencies, run:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/DataIntegrityTools/IntegrityCoverageTest.php
```

The focused tests use fixtures and do not connect to a database. Integration
validation is opt-in against an isolated disposable MySQL database only.
The command reads `LEAN_DB_HOST`, `LEAN_DB_PORT`, `LEAN_DB_USER`,
`LEAN_DB_PASSWORD`, and `LEAN_DB_DATABASE`, or `LEAN_OLD_DB_*` with
`--source=old`. Do not use production credentials for validation.

- [ ] Verify command discovery and clean, orphaned, and missing-column schemas.
- [ ] Confirm query failures fail closed and database state remains unchanged.
- [ ] Run full tests, code style, static analysis, and current-head CI.
- [ ] Independent review and explicit approval before deployment or live reads.

This diagnostic subset does not satisfy or close the full import/export issue.
