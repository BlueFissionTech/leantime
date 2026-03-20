# Support Rich Text Hotfix

Date: 2026-03-20
Branch: issue/33-support-richtext

## Scope
- External support portal on support-host domains
- Rich text support for new ticket descriptions
- Rich text and image-capable support comments
- Preserve current support-host routing and branding behavior

## Changes
- Added Tiptap CSS/JS bundles to `app/Views/Templates/layouts/supportportal.blade.php`
- Added support-specific editor styling and support project bootstrap values for editor uploads
- Converted new ticket description to `tiptapComplex`
- Converted ticket comment form to `tiptapSimple`
- Render ticket descriptions/comments with `escapeMinimal()` instead of stripping tags
- Added per-page editor initialization scripts through a Blade stack in the support layout

## Validation
- `git diff --check`
- Manual code review against existing Tiptap usage in ticket/comment templates

## Deployment Notes
- PR to `master` is the production hotfix path
- Separate PR to `staging` is required because local `staging` does not contain the external support portal baseline
