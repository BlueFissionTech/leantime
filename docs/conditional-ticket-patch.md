# Conditional ticket patch — guarded storage and callable contract, draft v1

Partial implementation of #159. This supplies an immutable request, shared
transaction body, SQLite conformance adapter, current-project policy, review-only
MySQL schema/adapter, and default-off authenticated service methods.
**MySQL execution and HTTP routing are not qualified. No production schema is
applied and integration writes must remain disabled.** Existing write APIs are
unchanged. Notification delivery is not implemented; writes have a separate
fail-closed outbox qualification gate.

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

## MySQL proposal and default-off callable service

The review branch now includes staging baseline `b815fe95` (including dependency
integration `cc90a646`). No protected branch was changed. Current dependency
writes in `Ticketdependencies` use `zp_entity_relationship`; ticket status,
sorting, parent completion and schedule updates use `zp_tickets`. The proposed
triggers cover those physical tables rather than only this new entrypoint.
Legacy multi-step workflows themselves are not made atomic by this change.

`MysqlConditionalTicketSchema::statements()` returns review-only DDL; it never
executes it and is not registered with the application migrator. It proposes:

- InnoDB guard, append-only BIGINT AUTO_INCREMENT revision events, operation-key
  unique receipts and transactional outbox; initial revisions for existing tickets.
- BEFORE triggers for every insert/update/delete on tickets, entity relationships,
  users, projects and project membership, acquiring the same guard row lock.
- AFTER triggers appending revisions for old/new ticket identities and all old/new
  Ticket relationship endpoints, including dependencies and collaborators.
- Widening `zp_tickethistory.changeValue` from the install schema's VARCHAR(150)
  to MEDIUMTEXT so accepted description/criteria values are not truncated.

Schema changes require a reviewed maintenance/quiescence plan, backup/restore and
retention policy, all-writer inventory, runtime privileges excluding DDL/TRUNCATE,
and separate human approval. DDL is not transactional as a batch. No drop/reset
or automatic rollback migration is supplied. Preserve revision events and the
counter; restore/replication/epoch behavior needs review before activation.

`MysqlConditionalTicketStore` requires MySQL 8.x (not MariaDB), all expected
InnoDB tables, full-text history, exact reviewed trigger definitions and no
cascaded foreign-key writer paths. Its first transactional data read locks the
shared guard. Expected revision and actual ACL are then checked while competing
ticket/relation/ACL writers are serialized; the shared text/history/receipt/outbox
body executes before releasing that lock. Read-only receipt/version methods use
the lock without changing data. Deadlocks/timeouts/commit ambiguity fail unknown,
with one transaction attempt and no send retry. Unknown extra triggers reject.

This is deliberately a conservative global lock, not a performance claim. Legacy
writers can acquire other row locks first and deadlock; failure must remain safe.
Real-engine tests must establish that the actual deployed engine/schema/plugins
obey the contract. MySQL trigger restrictions preclude reusing the SQLite update's
revision-table subquery, so the MySQL path uses the transaction-held guard instead.
See [MySQL trigger restrictions](https://dev.mysql.com/doc/refman/8.0/en/faqs-triggers.html)
and [InnoDB auto-increment behavior](https://dev.mysql.com/doc/refman/8.0/en/innodb-auto-increment-handling.html).

Source-callable `Leantime\Domain\Tickets\Services\ConditionalTickets` methods:

- `readVersion(array $request): array`: exactly positive integer projectId/ticketId.
- `readSnapshot(array $request): array`: same exact identity request; returns only
  headline, description and acceptanceCriteria with identity and issued revision
  from one current-authorized transaction. Preserve stored null versus empty values.
- `readReceipt(array $request): array`: full original patch intent for authorized
  uncertain-send readback, with actor derived from authentication.
- `patch(array $request): array`: full text-only intent; original correlated result
  on exact replay. Missing receipts remain unknown, never retry authorization.

The portable RPC prefix is `leantime.rpc.tickets.conditional_tickets`, followed by
`.readVersion`, `.readSnapshot`, `.readReceipt` or `.patch`. The earlier proposed
`conditionaltickets` segment does not retain the PSR-4 class capitalization on
case-sensitive filesystems. The actual JSON-RPC controller's POST body parsing,
discovery, named argument preparation, response correlation and service dispatch
are exercised with the real API guard and a synthetic API-key provider. These
fixtures bypass controller construction events and the HTTP kernel, and do not
qualify deployed routing, real key storage, sessions, proxy/base-path configuration
or successful MySQL execution. The service accepts only the selected `ApiGuard`, not a web
session, and uses the existing connection plus `CurrentProjectTicketAccess`.
Caller-supplied actor fields are rejected. No schema is installed by these methods.

Example JSON-RPC POST body (API authentication uses `x-api-key`; never publish keys):

```json
{"jsonrpc":"2.0","id":"review-42","method":"leantime.rpc.tickets.conditional_tickets.readSnapshot","params":{"request":{"projectId":7,"ticketId":12}}}
```

Example qualified-store result envelope (synthetic data, not a deployed receipt):

```json
{"jsonrpc":"2.0","id":"review-42","result":{"state":"current","projectId":7,"ticketId":12,"version":"ticket-revision-v1:4","text":{"headline":"Keep","description":"Old","acceptanceCriteria":""}}}
```

For `patch` and `readReceipt`, `params.request` instead contains the same complete
original intent: `projectId`, `ticketId`, `operationId`, `expectedVersion`, and
`patch`. Obtain reviewed content and `expectedVersion` from **one readSnapshot**;
do not join a separate getTicket result with readVersion or substitute a hash.
An overlapping legacy writer cannot pair old text with a newer token in the
SQLite fixture. A later edit changes the issued token and rejects the stale patch.
MySQL uses the existing shared writer guard for this read; engine proof is pending.
HTTP 200 alone is not success: inspect the correlated JSON-RPC result/error and
state. Disabled, missing/invalid API authentication, session-only authentication
and unsupported-storage outcomes are explicitly covered for all four methods.

Three independent gates remain OFF: server config
`tickets.conditional_patch.enabled` must be literal boolean true (default false),
the schema guard `enabled` must be 1 (default 0), and mutations additionally require
`outboxReady`=1 (default 0). **Do not set these for production.** The last flag is
reserved for a reviewed, tested consumer preserving notification/event semantics;
no such consumer is supplied yet. Direct legacy notification methods are not called
before commit or on replay. Durable outbox rows alone are not delivered notifications.

### Notification consumer implementation boundary

Source inspection rules out safely wrapping `Projects::notifyProjectUsers` in a
retrying outbox loop. That method applies plugin filters, author exclusion,
per-project relevance and event-type preferences, mention bypass, email queuing,
messenger sends, mention processing and in-app events. `Queue::queueMessageToUsers`
derives its message hash from the current timestamp and catches insertion errors;
it neither accepts an operation/destination idempotency key nor returns a durable
enqueue receipt. Messenger sends occur before the remaining mention/in-app work.
A crash after any send cannot safely restart this composite method.

The transaction now emits `ticket.text_patched.v2`, capturing operation key/ID,
actor/project/ticket identity, previous and resulting issued revisions, UTC event
time, changed field names, and complete allowlisted `before`/`after` text images.
Stored null and explicit empty values remain distinct. This immutable event is
inserted with ticket/history/receipt effects: rollback removes it, and patch replay
does not replace it with current ticket content. No-op/conflict produces no event.
It deliberately excludes unrelated ticket fields, user addresses and credentials.
It is not the complete legacy Notification entity. Older v1 events cannot be
upgraded by reading current ticket content; the new fixture rejects them.

`ConditionalNotificationPlan::prepare(array $intent, ?array $policy = null)` is a
pure boundary, **not an installed production policy adapter**. Missing policy is
blocked. A trusted adapter must supply `contract=ticket-notification-policy-v1`,
`intentDigest` (SHA-256 of the immutable event JSON), nonempty `revision`, explicit
boolean-true coverage for all `COVERAGE` stages, and `deliveries`. Stages include
membership, relevance, event preferences, author exclusion, mention bypass, plugin
filters, messenger and in-app behavior. These assertions are obligations for the
adapter, not proof that those real policies have been qualified. Never populate
them mechanically or accept them from an untrusted API caller.

Each final delivery contains exactly `channel` (`email`, `messenger`, `in_app`),
stable opaque `destination`, `subject` and `body`. The adapter owns policy decisions,
recipient resolution and rendering; this boundary does not invent replacements
for the legacy policies or call their side-effectful facade. It retains the final
policy output, canonicalizes destination keys, deduplicates identical entries and
rejects conflicting content for the same operation/channel/destination. No actual
legacy policy/render adapter is provided; the production gate remains off.

`Tests\\Fixtures\\ConditionalNotificationQueue` is an executable SQLite-only,
default-disabled queue contract, not a registered worker. After observing a
matching committed applied patch receipt it persists one plan, immutable local
queue entries and durable per-operation/channel/destination receipts atomically.
Exact replay recovers that plan without recomputing policy. Changed event content
cannot reuse it. Its `test_notification_*` tables are disposable fixture schema;
no production notification tables/migration are added.

The fixture transition contract is `queued -> dispatching -> sent|uncertain`.
`claim(key, currentPolicyCheck)` checks current eligibility and persists a unique
attempt before returning payload; denial cancels it. The check must cover current
access and policy/preferences, not merely rely on the original plan. A crash after
claim leaves dispatching, which cannot be claimed again. `settle(key, attempt,
providerReceipt|null)` maps unknown outcome to uncertain; a later independently
verified provider readback may resolve the same attempt to sent. There is **no
requeue/retry transition**, and conflicting receipt/attempt tokens reject. Fixture
receipt strings are synthetic; no transport or trustworthy provider-proof verifier
is supplied. A local enqueue receipt is not an external delivery receipt.

Remaining implementation: a qualified read-only adapter for the actual project,
member, preferences, mentions, plugin and rendering semantics; a production
same-connection queue/receipt adapter; and transport-specific idempotency/readback
and crash recovery. Full legacy Notification entity/URLs/localization, plugin
execution ordering, mention processing, in-app events and messenger delivery are
**excluded from this fixture's proof**, not dropped from acceptance criteria.
Do not enable `outboxReady`. Review text-content retention/access policy before
adopting the new immutable outbox payload, which now contains ticket text.

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

Exact source gap: the original PR base `f6a9434` has repository `updateTicket`, `patchTicket`,
`updateTicketStatus` and bulk sorting writes predicated by ID, history recorded
separately, collaborator writes in `zp_entity_relationship`, and service events
outside a transaction. `Projects::isUserAssignedToProject` delegates to a policy
that can resolve users through the application container and includes global-role,
client and project membership rules; it is not this fixture's simple access table.
The now-incorporated integration branch `cc90a646` additionally has dependency scheduling,
auto-reschedule relation flags and parent completion.
Its dependency repository also uses `zp_entity_relationship` Ticket endpoints.

Smallest next owner fixes: qualify the proposed schema/adapter and current policy
on an approved MySQL fixture, qualify HTTP authentication/discovery, and implement
the reviewed idempotent outbox consumer and its delivery receipts. Only then seek
independent review and explicit schema/deployment/activation approvals. Do not add a conditional wrapper around
legacy multi-step writes, claim the fixture covers uninspected extensions, or
expose workflow/status patches before their larger transactional scope is proved.

## Validation

With existing dependencies, from the checkout root:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ConditionalTicketPatchTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Repositories/SqliteConditionalTicketStoreTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/CurrentProjectTicketAccessTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ConditionalTicketEndpointTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ConditionalTicketRpcTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/ConditionalNotificationQueueTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/Support/MysqlConditionalTicketSchemaTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Integration/ConditionalTicketMysqlTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Support/ConditionalTicketPatch.php tests/Unit/app/Domain/Tickets/Support/ConditionalTicketPatchTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php tests/Fixtures/ConditionalTicketDatabase.php tests/Unit/app/Domain/Tickets/Repositories/SqliteConditionalTicketStoreTest.php
php vendor/bin/pint --test --config .pint/pint.json app/Domain/Tickets/Support/CurrentProjectTicketAccess.php tests/Unit/app/Domain/Tickets/Support/CurrentProjectTicketAccessTest.php
```

The focused tests use existing PDO SQLite and Laravel dependencies without application
boot or environment credentials. They prove bounded SQLite behavior, authenticated
entrypoint logic and generated schema coverage, not MySQL SQL execution, production
API authentication/discovery or deployed engine behavior. The opt-in MySQL fixture
skips unless `LEANTIME_CONDITIONAL_MYSQL_ALLOW_SCHEMA=1` is explicitly authorized.
It requires an existing empty database named `leantime_conditional_test_<suffix>`,
`LEANTIME_CONDITIONAL_MYSQL_DATABASE`, a matching `LEANTIME_CONDITIONAL_MYSQL_DSN`,
and securely supplied `LEANTIME_CONDITIONAL_MYSQL_USER` / `_PASSWORD` values. Never
point it at staging/production or a database with existing tables. It creates and
removes only its exact test tables, tests default-off/schema gates, long-text history,
replay/readback, ABA/relation invalidation, overlapping connections, rollback and
revocation. Its test-only outbox flag is not consumer qualification. It does not
install dependencies, create a database/account, or start services.

No application migration was executed, services started or dependencies changed.
Keep #159 open and the
review draft until remaining adoption gates are satisfied. Deployment and live
reconciliation remain unapproved.
