# Support Portal MVP

## Scope
- External customer-facing support portal for software support intake
- Host-aware portal branding and context resolution
- Setting-backed configuration with a seed fallback for immediate rollout
- Tickets stored as normal Leantime tickets inside a mapped support project

## Routes
- `/support`
- `/support/login`
- `/support/register`
- `/support/tickets`
- `/support/tickets/new`
- `/support/tickets/{id}`

## Host Strategy
- Immediate app path is `/support/*`
- Support host branding is resolved by request host
- For direct support-host root traffic, the edge should rewrite `/` to `/support`

## Branding
- Per-host branding is supported through `supportportal.hosts` or `supportportal.host.<host>` settings
- Deploy-safe env config is also supported through:
  - `LEAN_SUPPORT_PORTALS` as a JSON object keyed by host
  - `LEAN_SUPPORT_PORTAL_<HOST>` as a JSON object for a single host override
- Default tenant branding is not shown inside the support portal shell

### Generic Env Example
```env
LEAN_SUPPORT_PORTALS={"support.client-example.com":{"slug":"client-support","name":"Client Support","host":"support.client-example.com","clientId":123,"projectId":456,"productName":"Product Support","allowSelfSignup":true,"defaultTags":"support,software","brandName":"Client Brand","brandLogo":"","primaryColor":"#173B6D","secondaryColor":"#28A7A1"}}
```

## Data Model
- MVP uses existing users, projects, tickets, comments
- No new table in the first slice
- Portal config is setting-backed first, with a structured table still appropriate as a later hardening step

## Constraints
- External portal users only see tickets they created
- Internal staff continue using the full internal Leantime ticket workflow
- GitHub elevation is out of scope for this branch and should follow as a separate module/PR
