import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track', 'card', 'status'];

    connect() {
        if (!this.cardTargets.length) return;
        this.activeIndex = Math.min(1, this.cardTargets.length - 1);
        this.element.classList.add('is-ready');
        this.update();
        this.observer = new ResizeObserver(() => this.measure());
        this.cardTargets.forEach(card => this.observer.observe(card));
        this.measure();
    }

    disconnect() {
        this.dragCancel();
        this.observer?.disconnect();
        this.trackTarget.querySelectorAll('[data-carousel-ghost]').forEach(ghost => ghost.remove());
        this.cardTargets.forEach(card => card.getAnimations().forEach(animation => animation.cancel()));
        this.element.classList.remove('is-ready');
        this.trackTarget.style.removeProperty('height');
    }

    measure() {
        const height = Math.max(...this.cardTargets.map(card => card.scrollHeight));
        const scale = window.matchMedia('(max-width: 767.98px)').matches ? 1.05 : 1.14;
        this.trackTarget.style.height = Math.ceil(height * scale + 24) + 'px';
    }

    update(direction = 0) {
        this.trackTarget.querySelectorAll('[data-carousel-ghost]').forEach(ghost => ghost.remove());
        const count = this.cardTargets.length;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.cardTargets.forEach(card => card.getAnimations()
            .filter(animation => animation.id === 'menu-wrap')
            .forEach(animation => animation.finish()));
        this.cardTargets.forEach((card, index) => {
            let offset = index - this.activeIndex;
            if (offset > count / 2) offset -= count;
            if (offset < -count / 2) offset += count;
            const previousOffset = Number(card.style.getPropertyValue('--offset'));
            const wraps = direction && Math.abs(offset - previousOffset) > 1;
            const before = wraps ? getComputedStyle(card) : null;
            const oldTransform = before?.transform;
            const oldOpacity = before?.opacity;
            const visible = Math.abs(offset) <= 1;
            card.style.setProperty('--offset', offset);
            card.classList.toggle('is-active', index === this.activeIndex);
            card.classList.toggle('is-outside', !visible);
            card.setAttribute('aria-current', String(index === this.activeIndex));
            card.setAttribute('aria-hidden', String(!visible));
            card.tabIndex = visible ? 0 : -1;
            if (wraps && !reducedMotion) {
                const position = value => 'translate(calc(-50% + ' + (value * 113) + '%), -50%)';
                // Fade out a visual copy while the real card enters simultaneously.
                if (Math.abs(previousOffset) <= 1) {
                    const ghost = card.cloneNode(true);
                    ghost.removeAttribute('data-menu-carousel-target');
                    ghost.removeAttribute('data-action');
                    ghost.removeAttribute('href');
                    ghost.removeAttribute('id');
                    ghost.querySelectorAll('[id]').forEach(node => node.removeAttribute('id'));
                    ghost.dataset.carouselGhost = '';
                    ghost.inert = true;
                    ghost.tabIndex = -1;
                    ghost.setAttribute('aria-hidden', 'true');
                    ghost.classList.remove('is-active', 'is-outside');
                    this.trackTarget.append(ghost);
                    const departure = ghost.animate([
                        { transform: oldTransform, opacity: oldOpacity },
                        { transform: position(previousOffset - direction), opacity: 0 },
                    ], { duration: 400, easing: 'ease', fill: 'forwards' });
                    departure.onfinish = () => ghost.remove();
                }
                const animation = card.animate([
                    { transform: position(offset + direction), opacity: 0, visibility: 'visible' },
                    { transform: position(offset), opacity: visible ? .7 : 0, visibility: 'visible' },
                ], { duration: 400, easing: 'ease' });
                animation.id = 'menu-wrap';
            }
        });
        if (this.hasStatusTarget) this.statusTarget.textContent = (this.activeIndex + 1) + ' / ' + count;
    }

    focusMenu(index, direction = Math.sign(index - this.activeIndex)) {
        if (!this.cardTargets.length) return;
        this.activeIndex = (index + this.cardTargets.length) % this.cardTargets.length;
        this.update(direction);

    }

    previous() { this.focusMenu(this.activeIndex - 1); }
    next() { this.focusMenu(this.activeIndex + 1); }

    dragStart(event) {
        if (!event.isPrimary || event.button !== 0 || this.cardTargets.length < 2) return;
        this.suppressClick = false;
        this.drag = { id: event.pointerId, x: event.clientX, y: event.clientY, dx: 0, active: false, started: performance.now() };
    }

    dragMove(event) {
        const drag = this.drag;
        if (!drag || drag.id !== event.pointerId) return;
        const dx = event.clientX - drag.x;
        const dy = event.clientY - drag.y;
        if (!drag.active) {
            if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 8) {
                this.drag = null;
                return;
            }
            if (Math.abs(dx) < 8) return;
            this.trackTarget.querySelectorAll('[data-carousel-ghost]').forEach(ghost => ghost.remove());
            this.cardTargets.forEach(card => card.getAnimations().forEach(animation => animation.finish()));
            drag.active = true;
            this.element.setPointerCapture(event.pointerId);
            this.element.classList.add('is-dragging');
        }
        drag.dx = dx;
        // Limit a single gesture to one card, avoiding empty space at the edges.
        const stride = this.cardTargets[0].offsetWidth * 1.13;
        this.trackTarget.style.translate = Math.max(-stride, Math.min(stride, dx)) + 'px 0';
        event.preventDefault();
    }

    dragEnd(event) {
        const drag = this.drag;
        if (!drag || drag.id !== event.pointerId) return;
        const threshold = Math.min(50, this.cardTargets[0].offsetWidth * .15);
        const flick = Math.abs(drag.dx) > 16 && Math.abs(drag.dx) / Math.max(1, performance.now() - drag.started) > .35;
        this.suppressClick = drag.active;
        this.dragCancel();
        if (drag.active && (Math.abs(drag.dx) >= threshold || flick)) {
            this.focusMenu(this.activeIndex + (drag.dx < 0 ? 1 : -1));
        }
    }

    dragCancel(event) {
        // Touch starts with implicit capture on the link. Losing that capture
        // during transfer to the carousel must not cancel our new capture.
        if (event?.type === 'lostpointercapture' && this.element.hasPointerCapture(event.pointerId)) return;
        if (event?.pointerId !== undefined && this.drag && event.pointerId !== this.drag.id) return;
        const drag = this.drag;
        this.drag = null;
        if (drag?.active) this.suppressClick = true;
        this.element.classList.remove('is-dragging');
        this.trackTarget.style.removeProperty('translate');
        if (drag && this.element.hasPointerCapture(drag.id)) this.element.releasePointerCapture(drag.id);
    }

    preventNativeDrag(event) { event.preventDefault(); }

    guardClick(event) {
        if (this.suppressClick && event.detail !== 0) {
            event.preventDefault();
            event.stopImmediatePropagation();
            this.suppressClick = false;
        }
    }

    select(event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const index = this.cardTargets.indexOf(event.currentTarget);
        if (index !== this.activeIndex) {
            event.preventDefault();
            const offset = Number(event.currentTarget.style.getPropertyValue('--offset'));
            this.focusMenu(index, Math.sign(offset));
        }
    }

    key(event) {
        if (event.key === ' ') {
            event.preventDefault();
            event.currentTarget.click();
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            this.focusMenu(this.activeIndex + (event.key === 'ArrowLeft' ? -1 : 1));
            this.cardTargets[this.activeIndex].focus({ preventScroll: true });
        }
    }
}
