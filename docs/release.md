# Release and Operations

## Before a release

The final release checklist remains pending for the renamed variant. See [development status](../README.md#development-status-and-declared-compatibility); the historical release notes are not a release approval for `mod_kanbanlearning`.

1. Confirm [version.php](../version.php) has a new monotonically increasing build number, accurate release name, maturity, and support range.
2. Update user-facing release notes and this documentation when behaviour changes.
3. Run the local preflight.
4. Push the branch, manually dispatch the complete GitHub Actions matrix for that revision, and wait for every job to succeed.
5. Test the affected flows in a Moodle staging site with developer debugging enabled.

Rebuild AMD artifacts before packaging. Check that the ZIP contains `kanbanlearning/`, with component `mod_kanbanlearning`, and no obsolete generated modules. Confirm the dedicated repository and support links before publication.

## Staging verification

At a minimum, verify:

* clean installation alongside `mod_kanban`, with separate activity entries and plugin-owned tables;
* creating, editing and deleting Kanban for Learning content leaves existing `mod_kanban` content unchanged;
* backup and restore within `mod_kanbanlearning`, without claiming cross-component conversion;
* shared-board activity creation and normal card movement;
* group-board activity creation with selected groups and with no groups available;
* student board access, including a rejected direct URL to an unrelated group board;
* teacher/monitor all-board access where configured;
* completed-card behaviour and the completion column;
* template creation and explicit confirmation before overwriting a board with cards;
* course import with groups included and excluded;
* course import without user data preserves columns but creates no cards;
* basic privacy export/deletion behaviour when that code changes.

## Production upgrade

This procedure applies only to updates of an existing `mod_kanbanlearning` installation. For a site using `mod_kanban`, follow the [separate installation guidance](../README.md#existing-installations-of-the-original-module).

1. Record the running plugin build, Moodle release, PHP version, and database engine/version.
2. Back up the database, `moodledata`, and the current plugin directory.
3. Deploy the new plugin files while preserving Moodle ownership and permissions.
4. Run Moodle's upgrade process and review the database upgrade output.
5. Purge caches and perform the targeted staging checks against production configuration.
6. Monitor Moodle PHP, web-server, cron, and task logs after deployment.

## Rollback

Do not roll back plugin files alone after a database schema change. Restore a consistent application-code and database backup pair, or apply a forward corrective release. Test the rollback process in staging before relying on it operationally.

## Independent maintenance

This repository uses `mod_kanbanlearning`. Preserve the provenance and notices in [NOTICE](../NOTICE) in every distribution. A separate component name does not establish Marketplace acceptance; Marketplace submission remains pending.

Before publication, search active documentation for claims that this independently maintained plugin replaces `mod_kanban` in place. Such instructions belong only in clearly marked historical material. Confirm that installation commands target `mod/kanbanlearning` and links point to the dedicated repository.
