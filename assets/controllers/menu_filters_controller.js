import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['price', 'panel'];

    connect() {
        this.mobileQuery = window.matchMedia('(max-width: 56rem)');
        this.updatePanel = () => {
            this.panelTarget.open = !this.mobileQuery.matches;
        };
        this.updatePanel();
        this.mobileQuery.addEventListener('change', this.updatePanel);
    }

    disconnect() {
        this.mobileQuery.removeEventListener('change', this.updatePanel);
    }

    apply() {
        this.element.requestSubmit();
    }

    showPrice(event) {
        this.priceTarget.value = event.target.value;
    }
}
