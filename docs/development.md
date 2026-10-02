# Development and Testing

## Supported versions

The declared support range is maintained in [version.php](../version.php). See the [development status](../README.md#development-status-and-declared-compatibility) before installing or making compatibility claims.

## Source layout

* `classes/`: domain logic, external APIs, forms, events, notifications, and privacy provider.
* `amd/src/`: ES module source; generated files belong in `amd/build/` when Moodle's build process is run.
* `templates/`: Mustache templates.
* `db/`: capabilities, services, database schema, upgrades, and caches.
* `backup/moodle2/`: Moodle backup and restore support.
* `tests/`: PHPUnit tests and test generators.

The source component is `mod_kanbanlearning`. Generated files in `amd/build/` are compiled from `amd/src/` with that component identity. Rebuild them whenever JavaScript source changes, and remove obsolete generated module names as part of the build review.

## Local preflight

Run a local static preflight before pushing. At minimum, run PHP syntax checks, `git diff --check`, and Moodle CodeSniffer. This is a fast gate; it does not replace integration tests.

## CI

GitHub Actions workflows are stored in [.github/workflows](../.github/workflows):

* `moodle-preflight.yml`: fast static validation for pull requests and manual runs.
* `moodle-ci.yml`: manually dispatched matrix validation for the declared Moodle range, 4.1 through 5.2, plus `main`, on MariaDB and PostgreSQL, including static checks, AMD build, PHPUnit, and Behat.

The complete matrix covers the Moodle range declared in `version.php`. This is evidence for the automated scenarios listed in the workflow, not a production certification or a substitute for manual homologation.

The matrix is intentionally broader than the development server. A change that works locally can still fail because of database portability, Moodle API changes, generated AMD output, or browser-level behaviour.

## Mustache lint and list fragments

The Mustache lint output includes an intentional HTML validation warning for `templates/column.mustache` and `templates/card.mustache`. Both templates render an `<li>` as their root element because they are list fragments: the board template inserts columns into `ul.mod_kanbanlearning_column_container`, and each column inserts cards into its inner `<ul>`.

The linter validates each template fragment as if it were placed directly inside `body`, so it reports the root `<li>` as invalid even though the runtime parent is a list. The frontend also relies on this structure when it identifies and reorders direct column and card children during drag and drop.

This warning is therefore treated as an intentional limitation of standalone template validation. Do not replace the root `<li>` with a `<div>` or add a wrapper only to silence the warning. Any future structural change must include AMD build validation and manual tests for column and card drag and drop, ordering, card creation, and card editing.

## Test selection

| Change type | Minimum validation |
| --- | --- |
| PHP logic or permissions | PHPUnit test that fails before the fix, plus preflight. |
| Backup/restore | PHPUnit coverage for user-data and no-user-data behaviour where affected. |
| JavaScript / UI | AMD build, relevant Behat scenario where available, and manual browser validation. |
| Database/query | MariaDB and PostgreSQL CI jobs. |
| Capability changes | PHPUnit/API test plus manual role validation in a Moodle test site. |

## Coding guidance

Use Moodle APIs for contexts, capabilities, parameters, database access, strings, files, and output. Treat external function declarations and their runtime authorization as separate safeguards. Keep PostgreSQL portability in mind: do not compare text/blob fields in condition arrays where Moodle's DML layer forbids it.
