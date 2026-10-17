# Conditional ticket contract validation

Existing dependencies only; do not install or upgrade packages to run these tests.
Run the focused commands in [the draft contract](docs/conditional-ticket-patch.md).
The request, SQLite storage, current-project policy, endpoint and MySQL schema
unit suites do not boot the application or connect to an existing database.

## Optional MySQL integration fixture

`tests/Integration/ConditionalTicketMysqlTest.php` skips by default. It changes and
removes its test schema, so running it requires explicit approval for an **existing,
empty, disposable MySQL 8 database** named `leantime_conditional_test_<suffix>`.
Never target staging, production, or an existing populated database.

Supply these environment variables through the approved secret/test runner, never
commit values or put credentials in reports:

- `LEANTIME_CONDITIONAL_MYSQL_ALLOW_SCHEMA=1`: explicit test-schema opt-in.
- `LEANTIME_CONDITIONAL_MYSQL_DATABASE`: exact approved disposable database name.
- `LEANTIME_CONDITIONAL_MYSQL_DSN`: PDO MySQL DSN selecting that same database.
- `LEANTIME_CONDITIONAL_MYSQL_USER`: test-only account.
- `LEANTIME_CONDITIONAL_MYSQL_PASSWORD`: test-only secret.

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Integration/ConditionalTicketMysqlTest.php
```

The fixture checks database identity and emptiness before creating tables. It does
not create a database/account, install dependencies, or start services. It removes
only the exact tables it successfully created. A killed test may leave test tables;
inspect ownership before cleanup rather than bypassing its empty-database check.
Its synthetic `outboxReady` marker is not approval of real notification delivery.
Do not treat a skipped test or generated SQL coverage as MySQL runtime proof.
