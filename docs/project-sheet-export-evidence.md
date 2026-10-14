# Project sheet export read evidence

The existing `projectSheetExport` ticket read retains `rows`, `count`,
`projectId`, and `updatedSince`, and adds an `evidence` object (schema version 1).
This is a bounded improvement for #10, not a new roster or capacity endpoint.

Acceptance criteria:

- Successful empty queries differ from unavailable source reads.
- Reaching the 10,000-row cap is conservatively incomplete, even when the
  subsequent modified-time filter returns few or no rows.
- An invalid project or cursor never becomes an unfiltered export.
- Existing row-only service consumers retain their return shape.

`queryComplete` applies only to the caller-visible, filtered project query.
It does not attest to project-wide coverage, a transactionally consistent snapshot,
deployed revision, identities, remaining work, consent, funding, or availability.
`observedAt` is UTC request-processing time, not the records' modification time.
`retrievedCount` is measured before the modified-time filter; it is null when
the read was not performed or failed. `count` continues to mean returned rows.

When `queryComplete` is false, `reason` is `limit_reached`, `source_unavailable`,
`invalid_project`, or `invalid_updated_since`. A cap hit is not proof that more
records exist. No pagination or continuation token is provided by this change.
Invalid cursors now return no rows rather than silently broadening the query.
Clients must inspect evidence before treating an empty or short result as complete.

Focused isolated test (no application bootstrap or database):

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ProjectSheetExportEvidenceTest.php
```

Review/QA gates: inspect additive response compatibility and query scope;
exercise authorized empty, nonempty, failed, and capped reads on a known staging
revision; verify project access boundaries. Unit tests do not establish deployed
behavior or approve a production release.
