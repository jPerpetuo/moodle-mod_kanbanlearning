import {BaseComponent} from 'core/reactive';
import exporter from 'mod_kanbanlearning/exporter';
import Log from 'core/log';

/**
 * Parent component for all kanbanlearning boards of this cmid.
 */
export default class extends BaseComponent {
    /**
     * Function to initialize component, called by mustache template.
     * @param {*} target The id of the HTMLElement to attach to
     * @param {*} reactiveInstance The reactive instance for the component
     * @returns {BaseComponent} New component attached to the HTMLElement represented by target
     */
    static init(target, reactiveInstance) {
        return new this({
            element: document.getElementById(target),
            reactive: reactiveInstance,
        });
    }

    /**
     * Called after the component was created.
     */
    create() {
        this.cmid = this.element.dataset.cmid;
        this.id = this.element.dataset.id;
    }

    /**
     * Called once when state is ready, attaching event listeners and initializing drag and drop.
     * @param {*} state The initial state
     */
    async stateReady(state) {
        this.subcomponent = await this.renderComponent(
            this.getElement(),
            'mod_kanbanlearning/board',
            exporter.exportStateForTemplate(state),
        ).catch(error => {
            Log.debug(error);
        });
    }
}