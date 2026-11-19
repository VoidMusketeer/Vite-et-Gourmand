import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    focus() {
        if (this.element.open) this.inputTarget.focus();
    }

    outside(event) {
        if (this.element.open && !this.element.contains(event.target)) {
            this.element.open = false;
        }
    }

    close() {
        this.element.open = false;
        this.element.querySelector('summary').focus();
    }
}
