# Architecture

## Module boundaries

`mod_kanbanlearning` is a Moodle activity module. The entry point is [view.php](../view.php), which validates course-module access and renders the reactive board page. Most reads and writes then use Moodle AJAX external functions declared in [db/services.php](../db/services.php).

The browser uses AMD modules in [amd/src](../amd/src). They request initial data, render the board, perform optimistic interactions where appropriate, and periodically request server updates. PHP remains authoritative for permission checks and persistence.

## Main components

| Location | Responsibility |
| --- | --- |
| [classes/boardmanager.php](../classes/boardmanager.php) | Board lifecycle, structure copies, cards, columns, ordering, templates, and cache updates. |
| [classes/external/get_kanbanlearning_content.php](../classes/external/get_kanbanlearning_content.php) | Initial and incremental board reads; resolves the board visible to the current user. |
| [classes/external/change_kanbanlearning_content.php](../classes/external/change_kanbanlearning_content.php) | Validated AJAX writes such as moving, completing, assigning, duplicating, and editing board content. |
| [lib.php](../lib.php) | Moodle callbacks for instance lifecycle, group-setting normalisation, file serving, and course reset. |
| [mod_form.php](../mod_form.php) | Activity configuration form and validation. |
| [classes/privacy/provider.php](../classes/privacy/provider.php) | Moodle Privacy API implementation for exported and deleted user data. |
| [backup/moodle2](../backup/moodle2) | Backup and restore structures. |

## Data model

The persistent model is deliberately hierarchical:

```
kanbanlearning activity
  -> board (shared, group, personal, or template)
       -> column
            -> card
                 -> assignee(s)
                 -> discussion comment(s)
       -> history item(s)
```

The main database tables are `kanbanlearning`, `kanbanlearning_board`, `kanbanlearning_column`, `kanbanlearning_card`, `kanbanlearning_assignee`, `kanbanlearning_comment`, and `kanbanlearning_history`. Board and column sequences store ordering. JSON options fields hold display and behavioural options such as colours and automatic completion/hiding settings.

These plugin-owned tables are defined in [db/install.xml](../db/install.xml). They are separate from `mod_kanban` tables; both components refer to Moodle's shared users, courses and groups. Moodle adds the site's configured database prefix to table names.

## Request flow

1. Moodle opens `view.php` and validates the user, course module, and `mod/kanbanlearning:view` capability.
2. The frontend calls `mod_kanbanlearning_get_kanbanlearning_content_init`.
3. `get_kanbanlearning_content` resolves an allowed board and sends the current board, columns, cards, capabilities, and metadata.
4. A user interaction invokes an external write function, for example `mod_kanbanlearning_move_card`.
5. The external class validates parameters, context, capability, and board/card access before delegating persistence to `boardmanager`.
6. `boardmanager` updates data, ordering, caches, timestamps, history, and notifications as applicable.
7. Clients receive formatted update data and the polling endpoint reconciles concurrent updates.

## Important design rules

* Frontend visibility is not an authorization boundary. Every write must remain protected by server-side access checks.
* Template operations copy **structure only**. Applying a template to a board that has cards requires explicit confirmation because the operation removes target cards.
* The module supports concurrent access by polling. It does not implement record locking, so conflicting edits can still race.
* Group and personal boards are created lazily when they are first needed, usually from the current template or the default three-column structure.
