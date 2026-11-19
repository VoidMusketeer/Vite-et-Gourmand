import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['cover', 'thumb', 'option', 'date', 'guests', 'total', 'authDialog'];
    static values = { price: Number, start: String, end: String };

    connect() {
        this.update();
    }

    requireAccount(event) {
        if (!this.hasAuthDialogTarget) return;
        event.preventDefault();
        this.authDialogTarget.showModal();
    }

    closeAccount() {
        this.authDialogTarget.close();
    }

    disconnect() {
        if (this.hasAuthDialogTarget) this.authDialogTarget.close();
    }

    show({ currentTarget }) {
        const photo = currentTarget.querySelector('img');
        this.coverTarget.src = photo.src;
        this.coverTarget.alt = photo.alt;
        this.thumbTargets.forEach(button => button.setAttribute('aria-pressed', String(button === currentTarget)));
    }

    update() {
        const day = this.dateTarget.value.slice(5);
        const outsideSeason = day && this.startValue && (day < this.startValue || day > this.endValue);
        this.dateTarget.setCustomValidity(outsideSeason ? 'Choisissez une date dans la période indiquée pour ce menu.' : '');
        const extra = this.optionTargets.reduce((sum, option) => sum + (option.checked ? Number(option.dataset.price) : 0), 0);
        this.totalTarget.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })
            .format((this.priceValue + extra) * Number(this.guestsTarget.value));
    }
}
