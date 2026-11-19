import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    preview(event) {
        if (event.pointerType !== 'mouse' || this.element.open) return;
        this.pinned = false;
        this.element.open = true;
    }

    toggle(event) {
        event.preventDefault();
        this.pinned = !(this.element.open && this.pinned);
        this.element.open = this.pinned;
    }

    leave() {
        if (!this.pinned && !this.element.contains(document.activeElement)) this.close();
    }

    outside(event) {
        if (!this.element.contains(event.target)) this.close();
    }

    blur(event) {
        if (!this.element.contains(event.relatedTarget)) this.close();
    }

    escape() {
        this.close();
        this.element.querySelector('summary').focus();
    }

    close() {
        this.element.open = false;
        this.pinned = false;
    }
}
