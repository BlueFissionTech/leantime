# Attachment modal refresh review

Related: #6; extracted from #12. In the ticket modal, both existing attachments
and newly uploaded attachments use the current ticket's delete route. Other
file-manager contexts retain their existing route. The ticket controller checks
the editor role and the file's module and ticket ID before calling deletion.
Storage and the existing delete transport are unchanged.

## User story and acceptance

As a task editor, I can delete a disposable attachment, see the refreshed file
list in the same task, and continue working without closing and reopening it.

## Validation

With existing development dependencies, run:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/AttachmentModalRefreshSourceTest.php
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/AttachmentDeleteFailureTest.php
```

Source checks verify modal-handler wiring and response intent, not successful
browser redirect/header handling. In particular, an HX-Trigger on a redirect
does not by itself establish that the browser receives a refresh event.

The controller test covers a separate failure path: if the file service returns
`false`, the editor sees the localized deletion error and the controller renders
the ticket modal again. Separate synthetic cases check that a read-only actor,
a different ticket, and another module with the same numeric ID never reach
deletion. These tests do not delete a file or prove browser behavior, storage
isolation, or deployed permissions.

- [ ] On an approved disposable staging task, create a uniquely named QA file.
- [ ] Delete only that QA-created file, then confirm list refresh, task identity,
  task usability, and that unrelated attachments remain untouched.
- [ ] Test desktop and mobile, permission denial, and a failed deletion.
- [ ] Run full tests, code style, static analysis, and current-head CI.
- [ ] Independent review, deployed-revision evidence, and exact-head production approval.

Do not close #6 or #12 until interactive acceptance is confirmed.
