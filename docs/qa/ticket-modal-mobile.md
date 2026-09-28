# Ticket modal mobile layout review

Related: #3; extracted from #12. This branch changes only task modal layout,
its scoped mobile rules, and regression coverage. No comment, import, integrity,
roadmap, or generated asset changes are included.

## User story and acceptance

As a mobile user, I can reach all task fields and controls without content
being pushed off-screen. Metadata rows and task columns stack on small screens;
desktop sizing remains unchanged.

## Validation

With existing development dependencies, run:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/TicketModalMobileSourceTest.php
```

These are source-contract checks, not browser or deployment proof.

- [ ] Build the CSS through the normal asset pipeline and verify deployed assets.
- [ ] At 320, 375, 768, and 1280 pixels, open a task and inspect title, metadata,
  description, chosen selectors, save buttons, close control, and both orientations.
- [ ] Confirm no essential content is clipped and normal desktop sizing is retained.
- [ ] Run the full test suite, code style, static analysis, and current-head CI.
- [ ] Independent review and explicit production approval for the exact head.

Do not close #3 or #12 based solely on these source checks.
