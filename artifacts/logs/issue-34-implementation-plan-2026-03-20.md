# Issue 34 - GitHub Elevation

## First Slice
- Internal-only GitHub elevation from the internal support center
- Manager+ role requirement
- Manual sanitized GitHub title/summary form
- GitHub issue creation through env-configured repository/token
- Ticket linkage metadata stored in settings

## Config
- `LEAN_SUPPORT_GITHUB_REPO=owner/repo`
- `LEAN_SUPPORT_GITHUB_TOKEN=<token>`
- `LEAN_SUPPORT_GITHUB_LABELS=support,engineering`
- optional: `LEAN_SUPPORT_GITHUB_BASE_URL=https://api.github.com`

## Follow-up
- Move ticket linkage metadata to a dedicated table or relationship model
- Add issue-status sync back from GitHub
- Add richer repo mapping by support project/product


## Current temporary repo mapping
- Current implementation uses one global environment target: `LEAN_SUPPORT_GITHUB_REPO`
- This is acceptable for the first slice, but it is not the desired steady state
- Follow-up: move GitHub repo selection to support-project or product-family configuration so each support project can elevate into its own private product repository
