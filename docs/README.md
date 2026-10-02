# Documentation

This directory documents the source behaviour and maintenance model of Kanban for Learning. Read the [development status](../README.md#development-status-and-declared-compatibility) before installation; source descriptions do not establish successful runtime validation.

## Guides

| Document | Purpose |
| --- | --- |
| [Architecture](architecture.md) | Components, data model, request flow, and frontend/backend boundaries. |
| [Configuration and board flows](flows.md) | Activity modes, groups, templates, cards, columns, and completion behaviour. |
| [Permissions and groups](permissions.md) | Capabilities, board visibility, and role-design guidance. |
| [Backup, restore, and import](backup-restore.md) | What is retained or intentionally omitted when user data is excluded. |
| [Development and testing](development.md) | Local checks, automated tests, CI matrix, and code locations. |
| [Release and operations](release.md) | Upgrade, rollback, manual verification, and release discipline. |
| [Historical 0.4.0-beta notes](releases/0.4.0-beta.md) | Predecessor release notes, retained for provenance; not installation guidance for this component. |
| [Portuguese string reference](pt_br_strings_map.md) | Language keys and values from the renamed component. |
| [Screenshot capture guide](../screenshots/README.md) | Public screenshot scope and privacy requirements. |

## Scope and terminology

* **Activity instance**: a record in `{kanbanlearning}` linked to a Moodle course module.
* **Board**: a shared, group, personal, or template workspace for an activity.
* **Structure**: board settings and columns, including column titles, order, colours, and locks.
* **User data**: cards, attachments, descriptions, assignees, discussions, history, and user/group-specific board state.

These documents describe the current `mod_kanbanlearning` component. They are maintained alongside the code and should be updated whenever functionality, data handling, or supported Moodle versions change.
