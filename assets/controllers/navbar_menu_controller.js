import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['reveal'];

    toggle(event) {
        event.preventDefault();
        this.slide(this.animation ? !this.opening : !this.element.open);
    }

    outside(event) {
        if (this.element.open && !this.element.contains(event.target) && !(this.animation && !this.opening)) {
            this.slide(false);
        }
    }

    close() {
        this.slide(false);
        this.element.querySelector('summary').focus();
    }

    slide(opening) {
        const panel = this.revealTarget;
        const start = this.element.open ? panel.getBoundingClientRect().height : 0;
        this.cancel();
        this.opening = opening;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !panel.animate) {
            this.element.open = opening;
            return;
        }

        // Keep details open until the closing animation has actually finished.
        this.element.open = true;
        const end = opening ? panel.scrollHeight : 0;
        this.animation = panel.animate(
            [{ height: start + 'px' }, { height: end + 'px' }],
            { duration: opening ? 360 : 240, easing: 'cubic-bezier(.22,.68,.2,1)', fill: 'both' }
        );
        this.animation.onfinish = () => {
            if (!opening) this.element.open = false;
            this.cancel();
        };
    }

    cancel() {
        if (this.animation) {
            this.animation.onfinish = null;
            this.animation.cancel();
            this.animation = null;
        }
    }

    reset() {
        this.cancel();
        this.element.open = false;
    }

    disconnect() {
        this.reset();
    }
}
