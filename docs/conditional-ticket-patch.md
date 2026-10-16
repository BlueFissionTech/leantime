# Conditional ticket patch — request and SQLite storage contract, draft v1

Partial implementation of #159. This change supplies an immutable request value
object and an executable SQLite transaction/receipt conformance adapter.
**There is no callable HTTP/JSON-RPC patch endpoint or production schema/adoption
in this change. It must not enable integration writes.** Existing write APIs are
unchanged. The disposable storage proof is described below.

## Implemented contract

`ConditionalTicketPatch::fromRequest($authenticatedActorId, $request)` accepts
exactly `ticketId`, `projectId`, `operationId`, `expectedVersion`, and `patch`.
IDs are positive JSON integers, not numeric strings or booleans. The actor comes
from trusted authentication, never a request field. Operation IDs are 1–128 ASCII
letters, digits, underscores or hyphens. Clients generate and retain an operation
ID before sending; do not generate a replacement after an uncertain response.

The proposed version namespace is `ticket-revision-v1:<positive decimal>` (up to
19 digits). This is a format check, not proof that a token was issued or current.
The disposable SQLite contract issues these tokens; production does not yet.
The legacy `modified` timestamp is not accepted.

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

## Implemented SQLite storage proof

`SqliteConditionalTicketStore` uses the existing Laravel database connection,
requires driver `sqlite`, an explicitly installed contract marker and all six
revision triggers, and refuses an existing outer transaction. It never installs
a schema. `tests/Fixtures/ConditionalTicketDatabase.php` creates a new disposable
database for each test; it is not registered with the application migrator.

- `execute(intent)` acquires SQLite's writer lock before reading access, ticket,
  revision or receipt. A SQL expected-revision predicate gates the text update.
  Same-connection history, revision, receipt and outbox rows commit together.
  No-op and stale-version outcomes also receive immutable receipts. Denials and
  storage failures do not receive success receipts.
- `readVersion(actor, project, ticket)` supplies a current token after the supplied
  access policy approves; `readReceipt(intent)` performs a read-only authorized
  lookup and returns the original committed result. Exact replay rechecks current
  write permission and returns that same result without another ticket mutation.
  A missing receipt returns `unknown`, not permission to retry or proof of failure.
- The required access closure receives the same connection and exact actor,
  project, ticket and `read`/`write` action, inside the transaction. Only literal
  `true` authorizes. The original storage tests use a synthetic SQL access table;
  the additional current-project policy tests use the application's table and role
  semantics described below. Authentication and production-engine isolation are
  still not integrated.
- Append-only AUTOINCREMENT revision events cover every ticket INSERT/UPDATE/DELETE
  and every Ticket endpoint of relation INSERT/UPDATE/DELETE, including previous
  and new endpoints. Retaining these events prevents token reuse after same-second
  or ABA edits and deletion/reinsertion. Triggers apply to raw SQL too, not just
  this adapter. Committed revision history must not be purged/reset at adoption.
- The allowlist remains text-only. Parent, collaborators, dependencies, workflow,
  dates and custom data are preserved, not edited by this adapter. Relation writes
  nevertheless invalidate earlier tokens. History includes explicit empty text.
- An outbox intent is persisted once per applied operation; no event handler,
  notification or external delivery is invoked. The payload contains changed field
  names and identities/version, not text values. Delivery is not implemented.
- Two independent connections to the same disposable file exercise overlapping
  writer locks, duplicate operations and stale competitors. These are SQLite
  session-overlap tests, not multi-process MySQL concurrency or crash/power-loss
  durability proof. Reopening the database verifies committed receipt recovery.
- Injected SQL failures in history, outbox and receipt insertion roll back the
  ticket/revision and all effects, including a synthetic relational trigger effect.
  There are no automatic write retries. Exceptions return failure/unknown outcome;
  commit ambiguity must be reconciled by authorized receipt lookup.

## Current project authorization adapter

`CurrentProjectTicketAccess` is an invokable policy for the store. Pass
`Closure::fromCallable(new CurrentProjectTicketAccess())` as its access closure.
It reads `zp_user`, `zp_projects`, `zp_relationuserproject` and the exact ticket
destination through the transaction's supplied connection, not the application
container or cached session permissions. The default role provider calls
`Roles::getRoles()` on every check, preserving the `available_roles` filter.

The policy follows `Projects::isUserAssignedToProject` for global admin/owner,
organization-visible projects, matching clients and explicit project membership.
It follows `Auth::getRoleToCheck` for project-role overrides: manager/admin/owner
retain the global role; other members use an explicit project role or inherit.
Reads require at least readonly, writes at least editor in the current filtered
role map. Membership alone does not confer write permission. A manager without
project access is not treated as an admin.

Intentional fail-closed restrictions requiring review: only active status `a`
qualifies (including API users); missing users/projects/tickets, unknown roles,
ambiguous duplicate memberships/role names, unsupported actions, and absent
transactions deny. Client matching requires a positive client ID, not two unset
IDs. Storage catches schema/filter failures without writing a success receipt.
No new session, API key, actor impersonation or endpoint is introduced; trusted
actor identity must still come from the eventual authentication integration.

Tests use disposable SQLite tables with real policy column names and inject the
role map to avoid application boot or environment credentials. They cover role
overrides, revoked write/read access before replay/readback, exact destination,
missing schema and filtered-role changes. They do **not** prove deployed ACL,
plugin-filter behavior or MySQL isolation. SQLite's existing pre-read writer lock
serializes access changes with writes; a future engine adapter must supply its
own equivalent authorization/concurrency guarantees. Do not reuse the policy
with an arbitrary read-committed transaction and assume that guarantee.

## Remaining production adoption gates

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

Exact source gap: the PR base `f6a9434` has repository `updateTicket`, `patchTicket`,
`updateTicketStatus` and bulk sorting writes predicated by ID, history recorded
separately, collaborator writes in `zp_entity_relationship`, and service events
outside a transaction. `Projects::isUserAssignedToProject` delegates to a policy
that can resolve users through the application container and includes global-role,
client and project membership rules; it is not this fixture's simple access table.
The inspected integration branch `cc90a646` additionally has dependency scheduling,
auto-reschedule relation flags and parent completion, absent from this PR's base.
Its dependency repository also uses `zp_entity_relationship` Ticket endpoints.

Smallest next owner fixes: adopt the reviewed revision/receipt/outbox schema and
writer coverage for the intended production engine, qualify the current role/project
policy under that engine's concurrency and application filters, reconcile the dependency-bearing integration
branch, then expose the text-only API with real-engine rollback/permission/concurrent
proof and a reviewed outbox consumer. Do not add a conditional wrapper around
legacy multi-step writes, claim the fixture covers uninspected extensions, or
expose workflow/status patches before their larger transactional scope is proved.

## Validation

With existing dependencies, from the checkout root:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ConditionalTicketPatchTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Repositories/SqliteConditionalTicketStoreTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/CurrentProjectTicketAccessTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Support/ConditionalTicketPatch.php tests/Unit/app/Domain/Tickets/Support/ConditionalTicketPatchTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php tests/Fixtures/ConditionalTicketDatabase.php tests/Unit/app/Domain/Tickets/Repositories/SqliteConditionalTicketStoreTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Support/CurrentProjectTicketAccess.php tests/Unit/app/Domain/Tickets/Support/CurrentProjectTicketAccessTest.php
```

Tests use existing PDO SQLite and Laravel dependencies without application boot
or environment credentials. They now prove the bounded SQLite storage behavior,
not the production API/authorization/deployed engine. No application migrations,
runtime registrations, dependencies or services are added. Keep #159 open and the
review draft until remaining adoption gates are satisfied. Deployment and live
reconciliation remain unapproved.
