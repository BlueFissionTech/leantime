# Support Portal Assessment and Roadmap

## Summary
The original support portal objective is still correct, but the first implementation path leaned too hard on redirecting a support host into a support subpath inside the normal application flow. That collides with Leantime's global auth, base URL, shared error pages, and cached route/view behavior. The feature should now be treated as a host-activated support application mode that reuses the same app instance and database.

## What We Know
- DNS/platform routing to the shared app instance is working.
- Static files requested directly from the support host resolve correctly.
- The support portal module is present and the customer support controllers/templates are in place.
- The external support UI should be a reduced, branded shell over normal Leantime tickets/comments.
- The main host must continue to function as the normal app.
- One shared deployment and one shared database are required.
- Support-host failures are application-level, not DNS-level.

## What We Do Not Know
- Whether production route loading is consistently seeing support-host mappings at boot time.
- Whether the production runtime is reading support portal JSON env exactly as expected.
- Whether route/view/config cache interactions are preserving main-host assumptions.
- The exact first production exception that still causes the support-host error page.

## What We Want
- A single application that behaves differently based on incoming host.
- Support hosts become external support entry points with:
  - support home
  - support login/register
  - customer ticket list/detail/create
  - client branding
  - scoped ticket visibility
- The main host remains the full internal application.
- No redirects to the main host from a support host.

## What We Are Getting Instead Today
- Support-host requests still fall back to main-host auth in some paths.
- Some support pages still fall into the global error layout.
- Asset URLs on failed support pages still pin to the main host.
- Support mode activation is still too dependent on successful config/env resolution in early lifecycle steps.

## Things We Tried
- Redirect support hosts into `/support`
- Override auth login route for support requests
- Override support-host root redirect behavior
- Add host-aware support portal routing
- Add host-aware support views/layouts
- Add request-scoped base URL override for configured support hosts
- Move support templates into a stable global namespace
- Pivot to domain-locked support routes

## Things We Did Not Yet Do
- Make support-mode activation independent from env-config success
- Make support host classification a first-class bootstrap concern for all `support.*` hosts
- Gather the first production stack-trace line from the remaining support-host failure
- Replace env/settings mapping with a dedicated database-backed support portal table
- Add automated support-host Playwright coverage against a deployed environment

## What Works in Other Examples and Packages
- Multi-tenant packages such as Stancl work because they classify tenancy very early from the host and then make request-scoped config decisions before application routing/auth flows proceed.
- The relevant lesson is not to install a full tenancy package immediately; it is to adopt the same ordering:
  - classify host early
  - activate tenant/support mode early
  - then route/auth/render within that mode
- Packages that succeed here do not make auth/base-url behavior contingent on a later redirect workaround.

## Core Decision
We should stop treating support hosts as a redirect source into the normal app. We should treat them as a host-activated support mode that reuses the same database and ticket model.

## Recommended Implementation Direction

### 1. Support Host Classification First
- Any `support.*` host should be treated as a support-host candidate.
- Portal config should refine scope and branding, but should not be required just to enter support mode.

### 2. Request-Scoped Base URL
- For support hosts, request-scoped base URL and app URL must pin to the incoming host.
- This must happen in bootstrap, not later in controllers.

### 3. Support Auth Surface
- Support-host public/auth paths must stay local to the support host.
- Support-host protected pages must redirect only to support-host login.

### 4. Rendering Isolation
- Support views/layout should stay isolated from dashboard/theme assumptions.
- Shared global error pages should not be the primary rendering path for support mode.

### 5. Routing Strategy
- Prefer host-driven support mode plus stable support routes over host-config-enumerated route registration alone.
- Avoid solutions that require one app instance per support host.

## Immediate Next Technical Slice
- Broaden support-host activation from "configured support host" to "support-host candidate".
- Remove route/auth/base-url dependence on successful early JSON config resolution.
- Keep portal resolution responsible for branding/project scope only.
- Re-test support host home/login/tickets behavior after that narrower change.

## Acceptance Criteria for This Slice
- Support host root does not redirect to the main app host.
- Support host login stays on the support host.
- Support host ticket redirects stay on the support host.
- Support host pages render with styles from the support host.
- Main host behavior remains unchanged.

## After This Slice
- Add database-backed support portal mapping.
- Add internal support center.
- Add GitHub elevation flow for engineering issues.
