# Kanban for Learning activity for Moodle

Kanban for Learning is a Moodle activity module for project and learning-process management inside a course. It provides shared, group, personal, and template boards with columns, cards, assignments, due dates, notifications, history, and activity-completion rules.

This independently maintained plugin uses the Moodle component `mod_kanbanlearning`, declared in [version.php](version.php). Its code origin, authorship and licence are recorded in [NOTICE](NOTICE).

It is designed for installation alongside `mod_kanban`, using a separate plugin directory and plugin-owned tables. Coexistence still requires staging validation. It does not replace or automatically migrate activities from the original module.

## Development status and declared compatibility

The previous `mod_kanbanccead` identity passed static validation, a manual clean-installation and coexistence check in a Moodle 5.2 test environment, and the complete [Moodle Plugin CI matrix](https://github.com/jPerpetuo/moodle-mod_kanbanccead/actions/workflows/moodle-ci.yml). This `mod_kanbanlearning` rename still requires validation. The plugin remains beta and is not approved for production or Moodle Marketplace use. Automated CI coverage does not replace production homologation.

[version.php](version.php) retains the inherited `0.4.0-beta` label and declares Moodle 4.1 through 5.2. These declarations describe the supported range of this beta source revision; they are not evidence of a published release or production certification.

The source repository is [jPerpetuo/moodle-mod_kanbanlearning](https://github.com/jPerpetuo/moodle-mod_kanbanlearning). Use no predecessor repository as a substitute.

JavaScript is required. The activity uses Moodle reactive components and has no non-JavaScript fallback.

## Features

* Shared boards for all authorised activity users.
* One board per selected Moodle group, with server-side access control.
* Optional personal boards.
* Columns with titles, order, visual options, locking, completion behaviour, and hidden completed cards.
* Cards with descriptions, attachments, assignees, due dates, reminders, discussions, and completion state.
* Board history, notifications, and Moodle activity-completion rules.
* Template boards that copy structure only: columns, options, colours, order, and locks. Existing target cards require explicit confirmation before a template overwrites a board.
* Course import and restore that retain activity configuration and board structure without importing cards or other user data when Moodle user data is excluded.

See [Configuration and board flows](docs/flows.md) for the functional reference.

## Installation

The procedures below describe the intended installation layout. They have not yet been validated for this renamed variant. Use them only after a tested package or source revision is available.

### Install from a ZIP file

1. Obtain a tested release ZIP for `mod_kanbanlearning` containing a top-level `kanbanlearning/` directory.
2. In Moodle, go to **Site administration > Plugins > Install plugins**.
3. Upload the ZIP and complete the validation and installation process.
4. Go to **Site administration > Notifications** if Moodle asks to complete the upgrade.

### Install from source

Place this repository at:

```
{moodle-dirroot}/mod/kanbanlearning
```

Then complete the Moodle upgrade through **Site administration > Notifications** or:

```bash
php {moodle-dirroot}/admin/cli/upgrade.php
```

### Install from Git

From the Moodle `mod` directory, clone into `kanbanlearning`:

```bash
cd {moodle-dirroot}/mod
git clone https://github.com/jPerpetuo/moodle-mod_kanbanlearning.git kanbanlearning
```

Then complete the Moodle upgrade through **Site administration > Notifications** or:

```bash
php {moodle-dirroot}/admin/cli/upgrade.php
```

### Update a Git installation

Use this only for an existing `mod_kanbanlearning` Git installation with no local changes and a tested update on `origin/main`. Follow the backup requirements in [Release and operations](docs/release.md) first. Stop if the status is not clean.

```bash
cd {moodle-dirroot}/mod/kanbanlearning
git status --short
git pull --ff-only origin main
php {moodle-dirroot}/admin/cli/upgrade.php
```

Here, `{moodle-dirroot}` means the directory containing Moodle's `config.php` and `mod/`, which can be the `public/` directory in a split layout. Substitute the actual path before running commands.

## Existing installations of the original module

Keep the original `mod/kanban` directory and its data intact. Install this component separately in `mod/kanbanlearning` after validation.

The schema in [db/install.xml](db/install.xml) uses `kanbanlearning`-prefixed tables. Existing boards and cards in `mod_kanban` remain with that component. Both plugins still use Moodle's shared courses, users and groups.

No cross-component migration or backup conversion is provided. Do not rename existing database tables or treat a `mod_kanban` backup as a `mod_kanbanlearning` backup. See [Backup, restore, and import](docs/backup-restore.md).

For the complete release and rollback procedure, see [Release and operations](docs/release.md).

## Groups, permissions, and import

Group boards are limited to the groups selected in the activity settings. Users without an all-boards capability can access only boards for groups they belong to. A direct request for another group board is rejected and redirected to an accessible board.

Course import normally excludes user data. In that mode, the activity preserves board structure, including custom columns, colours, options, order, and locks, but does not import cards, attachments, assignees, discussions, or history. Groups are retained only when the Moodle import option includes them.

See [Permissions and groups](docs/permissions.md) and [Backup, restore, and import](docs/backup-restore.md).

## Documentation

The documentation index is available in [docs/README.md](docs/README.md).

* [Architecture](docs/architecture.md)
* [Configuration and board flows](docs/flows.md)
* [Permissions and groups](docs/permissions.md)
* [Backup, restore, and import](docs/backup-restore.md)
* [Development and testing](docs/development.md)
* [Release and operations](docs/release.md)

## Development and testing

The repository includes workflow definitions for static checks and Moodle integration tests. Their presence does not establish compatibility. Before submitting changes, run the local preflight and the relevant Moodle tests.

See [Development and testing](docs/development.md) for commands, test coverage, and the GitHub Actions matrix.

## Community

* [Contributing](CONTRIBUTING.md)
* [Governance](GOVERNANCE.md)
* [Support](SUPPORT.md)
* [Security policy](SECURITY.md)

## Licence and attribution

Original work is copyright 2023-2025 ISB Bayern and Stefan Hanauska.

This derivative is distributed under the GNU General Public License, version 3 or later. See [LICENSE](LICENSE) and [NOTICE](NOTICE).
