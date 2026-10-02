import Reactive from 'mod_kanbanlearning/reactive';
import KanbanlearningParent from 'mod_kanbanlearning/kanbanlearningparent';
import KanbanlearningMutations from 'mod_kanbanlearning/mutations';

const stateChangedEventName = 'mod_kanbanlearning:stateChanged';

/**
 * Create reactive instance for kanbanlearning, load initial state.
 * @param {string} domElementId Id of render container
 * @param {number} cmId Course module id of the kanbanlearning board
 * @param {number} boardId Id of the board to display
 * @returns {kanbanlearningComponent}
 */
export const init = (domElementId, cmId, boardId) => {
    const reactiveInstance = new Reactive({
        name: 'kanbanlearning_' + cmId,
        eventName: stateChangedEventName,
        eventDispatch: dispatchkanbanlearningEvent,
        target: document.getElementById(domElementId),
        mutations: new KanbanlearningMutations(),
    });
    reactiveInstance.loadBoard(cmId, boardId);
    return new KanbanlearningParent({
        element: document.getElementById(domElementId),
        reactive: reactiveInstance,
    });
};

/**
 * Internal state changed event.
 *
 * @method dispatchkanbanlearningEvent
 * @param {object} detail the full state
 * @param {object} target the custom event target (document if none provided)
 */
function dispatchkanbanlearningEvent(detail, target) {
    if (target === undefined) {
        target = document;
    }
    target.dispatchEvent(
        new CustomEvent(
            stateChangedEventName,
            {
                bubbles: true,
                detail: detail,
            }
        )
    );
}
