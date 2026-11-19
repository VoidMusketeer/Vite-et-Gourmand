import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card'];

    connect() {
        this.index = 0;
        this.mobile = window.matchMedia('(max-width: 767.98px)');
        this.onLayout = () => {
            this.cardTargets.forEach(card => {
                card.classList.remove('is-expanded');
                card.querySelectorAll('.testimonial-expand-label').forEach(button => button.setAttribute('aria-expanded', 'false'));
            });
            this.schedule();
        };
        this.mobile.addEventListener('change', this.onLayout);
        this.count = this.hasCardTarget ? this.cardTarget.children.length : 0;
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.onLayout();
    }

    disconnect() {
        this.mobile.removeEventListener('change', this.onLayout);
        clearTimeout(this.timer);
        clearTimeout(this.transitionTimer);
        this.element.classList.remove('is-changing');
    }

    expand(event) {
        if (this.changing) return;
        const button = event.currentTarget;
        const card = button.closest('.testimonial-card');
        const expanded = card.classList.toggle('is-expanded');
        button.setAttribute('aria-expanded', String(expanded));
        this.schedule();
    }

    next() {
        if (this.count < 2 || this.changing) return;
        clearTimeout(this.timer);
        this.changing = true;
        this.element.classList.add('is-changing');
        this.transitionTimer = setTimeout(() => {
            this.index = (this.index + 1) % this.count;
            this.cardTargets.forEach((card, slot) => {
                card.classList.remove('is-expanded');
                card.querySelectorAll('.testimonial-expand-label').forEach(button => button.setAttribute('aria-expanded', 'false'));
                [...card.children].forEach((review, index) => {
                    const active = index === (this.index + slot) % this.count;
                    review.classList.toggle('is-active', active);
                    review.setAttribute('aria-hidden', String(!active));
                });
            });
            this.element.classList.remove('is-changing');
            this.changing = false;
            this.schedule();
        }, this.reducedMotion ? 0 : 300);
    }

    schedule() {
        clearTimeout(this.timer);
        if (!this.reducedMotion && !document.hidden && this.count > 1 && !this.changing && !this.cardTargets.some(card => card.classList.contains('is-expanded'))) {
            this.timer = setTimeout(() => this.next(), 8000);
        }
    }
}
