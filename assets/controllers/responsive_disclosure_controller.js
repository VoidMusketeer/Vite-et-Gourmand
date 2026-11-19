import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { errors: Boolean };
    connect() {
        this.media = window.matchMedia('(max-width: 700px)');
        this.update = () => { this.element.open = !this.media.matches || this.errorsValue; };
        this.media.addEventListener('change', this.update);
        this.update();
    }
    disconnect() { this.media.removeEventListener('change', this.update); }
    toggle(event) { if (!this.media.matches) event.preventDefault(); }
}
