# Support Portal

## Objective
- Provide an external, client-branded support entry point on a dedicated support host.
- Keep one shared app instance, one shared database, and one shared ticket system.
- Reuse Leantime tickets/comments as the system of record.
- Keep the implementation as module-oriented as possible, with minimal core patches only where global auth/base-url behavior makes that unavoidable.

## Product Shape
- External support hosts should behave like a separate support application mode.
- A configured support host should present:
  - `/`
  - `/login`
  - `/register`
  - `/tickets`
  - `/tickets/new`
  - `/tickets/{id}`
- External users should only see their own tickets and a stripped support UI.
- Internal staff should continue to manage work in the normal internal Leantime project/ticket flow.

## Current Desired Architecture
- Single app instance
- Single database
- Single deployment
- Request-scoped support mode based on incoming host
- Per-host portal mapping for:
  - client/account context
  - support project
  - branding
  - default tags/product label

## Portal Mapping
- Immediate config source:
  - `LEAN_SUPPORT_PORTALS`
  - `LEAN_SUPPORT_PORTAL_<HOST>`
- Later hardening target:
  - dedicated support portal config table

### Generic Env Example
```env
LEAN_SUPPORT_PORTALS={"support.client-example.com":{"slug":"client-support","name":"Support Portal","host":"support.client-example.com","clientId":123,"projectId":456,"productName":"Product Support","allowSelfSignup":true,"defaultTags":"support,software","brandName":"Client Brand","brandLogo":"https://client.example/logo.png","primaryColor":"#173B6D","secondaryColor":"#28A7A1"}}
```

## What We Know
- The support portal module exists.
- External register/login/ticket list/create/detail/comment flows have been scaffolded.
- Host-aware branding/config resolution has been implemented.
- Production auth/base-url collisions still exist.
- Static files on the support host resolve correctly through the platform.
- The current failure is in application routing/auth/bootstrap behavior, not DNS reachability.

## What We Do Not Yet Know
- Whether production route loading is seeing support-host config early enough.
- Whether production env JSON is being parsed exactly as expected at runtime.
- Whether route/view/config caching is still preserving older main-host assumptions in some code paths.
- The exact first production stack trace line for the remaining support-host render failure.

## What We Tried
- `/support/*` path-based portal flow on the main app host
- host-aware redirect into `/support`
- support-host login redirect overrides
- support-host root redirect overrides
- support view/layout isolation
- request-scoped `BASE_URL` / `appUrl` override for configured support hosts
- host-locked support routes
- generic support-host controller/view wiring

## What Did Not Hold Up
- Redirect-first support host flow
- Relying on shared global auth/login route behavior
- Relying on shared theme/header layout behavior
- Depending on configured-host route registration alone for support mode activation

## What We Are Choosing Instead
- Domain-locked support mode
- Support host should be treated as a separate application mode, not a redirect target
- Support host auth/base-url handling should activate from host classification first
- Portal config should decide branding/project scope, not whether support mode exists at all

## Minimum Viable Production Behavior
- On a support host:
  - `/` renders support home
  - `/login` renders support login
  - `/register` renders support registration
  - `/tickets` requires support login on the same host
  - all CSS/JS/assets resolve on the same support host
  - no redirects to the main app host
- On the main host:
  - no support host behavior leaks into the main app

## Phased Roadmap

### Phase 1: Stabilize Support Host Mode
- Make support-mode classification independent from successful portal JSON parsing.
- Treat `support.*` hosts as support-host candidates at bootstrap/auth level.
- Ensure request-scoped base URL is pinned to the incoming support host.
- Ensure public/auth routes for support hosts stay on the support host.
- Avoid route registration that depends entirely on env-derived host enumeration.

### Phase 2: Stabilize Rendering
- Keep support layout self-contained.
- Keep support templates in a stable global namespace.
- Ensure support views do not depend on internal dashboard/theme composer state.
- Verify support login/home render with styles on the support host.

### Phase 3: Harden Portal Resolution
- Move from env/settings-first resolution to a dedicated support portal table.
- Support one host -> one or more support project/product mappings.
- Add admin management UI for support portals.

### Phase 4: Internal Support Center
- Allow authenticated internal/customer users to submit support issues without the stripped external shell.
- Scope visibility and actions according to role/project access.

### Phase 5: Engineering Elevation
- Add a project-manager action to elevate a validated support issue to GitHub.
- Preserve a two-way reference between ticket and GitHub issue.
- Keep client-specific details out of public GitHub text by default.

## Constraints
- No separate app instance per client support host
- No public repo leakage of private client/domain details
- Minimal core changes unless global auth/bootstrap behavior forces them
- Shared DB and shared ticket system remain required
