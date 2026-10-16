# Conditional ticket patch — request contract, draft v1

Partial implementation of #159. This change supplies an immutable request value
object and deterministic receipt-binding fingerprint. **There is no callable
HTTP/JSON-RPC patch endpoint, durable receipt store, or atomic update in this
change. It must not enable integration writes.** Existing write APIs are unchanged.

## Implemented contract

`ConditionalTicketPatch::fromRequest($authenticatedActorId, $request)` accepts
exactly `ticketId`, `projectId`, `operationId`, `expectedVersion`, and `patch`.
IDs are positive JSON integers, not numeric strings or booleans. The actor comes
from trusted authentication, never a request field. Operation IDs are 1–128 ASCII
letters, digits, underscores or hyphens. Clients generate and retain an operation
ID before sending; do not generate a replacement after an uncertain response.

The proposed version namespace is `ticket-revision-v1:<positive decimal>` (up to
19 digits). This is a format check, not proof that a token was issued or current.
No version issuer exists yet. The legacy `modified` timestamp is not accepted.

The initial allowlist is deliberately text-only: `headline` (nonblank, at most
255 UTF-8 bytes), `description` and `acceptanceCriteria` (at most 65,535 UTF-8
bytes). Explicit empty description/acceptance criteria clears the field; null,
coercion, unknown fields and empty patches reject. Text is preserved byte-for-byte
without trimming or Unicode normalization. Rendering sanitization remains the
application's responsibility. Status, dates, effort, parent, project movement,
collaborators and dependency writes are not yet supported by this draft.

`fingerprint()` SHA-256 binds contract version, authenticated actor, exact
destination, operation ID, expected version and sorted patch keys. Reordering
keys does not change it; changing a bound value does. This is an integrity binding,
not authentication, authorization, storage, or exactly-once execution.
`operationKey()` identifies actor + operation ID independently of destination;
the future durable unique constraint must reject changed destination/content for
that key, not silently treat it as a new operation.

`overlayForPreview()` accepts an already authorized, normalized row with integer
`id` and `projectId`. It checks destination identity and overlays only supplied
fields, preserving everything else. It performs no version check or write and
must never be used as read-then-update concurrency control.

## Required storage and adoption work (not implemented)

1. Review migration/version issuance and receipt retention policy before applying
   any schema change. Every relevant writer must advance a non-reused revision,
   including legacy full/partial updates, status/sort updates, bulk paths,
   dependency rescheduling, parent completion, collaborator and direct SQL paths.
   Same-second edits and change-away/change-back must invalidate a stale token.
2. Authenticate and recheck actor/project permission, destination, workflow and
   expected revision within the authoritative transaction. Fail closed on an
   unsupported writer/engine. A client pre-read or row hash is insufficient.
3. Atomically persist ticket + history + applicable relational effects + receipt,
   with unique operation key and fingerprint equality on replay. Roll back all
   on failure. External notifications require a transactional outbox/post-commit
   delivery design; do not dispatch before commit or repeat on replay.
4. Provide authorized read-only operation lookup (permission checked again) for
   uncertain sends. A missing receipt alone is not proof the send never committed.
   Exact replay returns the original committed result, not a fresh mutation.
   Concurrent duplicate and changed-payload requests must serialize safely.
5. Define structured `applied`, `no_op`, `conflict`, `denied`, and `failure`
   responses with issued versions and correlation. Never convert boolean false,
   transport failure or application errors into success. No auto-close authority.
6. Prove cross-project denial, revoked permission on replay, all competing writers,
   real concurrent DB sessions, same-timestamp/ABA changes, duplicate IDs, lost
   responses, transaction rollback and notification behavior on an approved
   synthetic storage/API setup before independent review and staging adoption.

Source evidence: `Tickets::updateTicket` supplies defaults for omissions and
coordinates dependency/notification/parent effects outside the repository write;
repository `updateTicket`, `patchTicket`, `updateTicketStatus` predicate by ID and
record history separately. Adding a conditional wrapper around those methods is
not sufficient. The initial text-only subset does not waive these requirements.

## Validation

With existing dependencies, from the checkout root:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ConditionalTicketPatchTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Support/ConditionalTicketPatch.php tests/Unit/app/Domain/Tickets/Support/ConditionalTicketPatchTest.php
```

These are pure request/preview tests, not API, SQL concurrency, authorization or
durability proof. No migrations, runtime registrations, dependencies or services
are added. Keep #159 open and the review draft until the missing adoption gates
are separately satisfied. Deployment and live reconciliation remain unapproved.
