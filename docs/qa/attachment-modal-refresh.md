# Attachment modal refresh review

Related: #6; extracted from #12. This branch changes the attachment delete link
and successful-delete response only, with source regression coverage. It does
not change storage, file authorization, or the existing delete transport.

## User story and acceptance

As a task editor, I can delete a disposable attachment, see the refreshed file
list in the same task, and continue working without closing and reopening it.

## Validation

With existing development dependencies, run:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Unit/app/Domain/Tickets/AttachmentModalRefreshSourceTest.php
```

Source checks verify modal-handler wiring and response intent, not successful
browser redirect/header handling. In particular, an HX-Trigger on a redirect
does not by itself establish that the browser receives a refresh event.

- [ ] On an approved disposable staging task, create a uniquely named QA file.
- [ ] Delete only that QA-created file, then confirm list refresh, task identity,
  task usability, and that unrelated attachments remain untouched.
- [ ] Test desktop and mobile, permission denial, and a failed deletion.
- [ ] Run full tests, code style, static analysis, and current-head CI.
- [ ] Independent review, deployed-revision evidence, and exact-head production approval.

Do not close #6 or #12 until interactive acceptance is confirmed.
