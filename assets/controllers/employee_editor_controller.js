import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'content', 'error', 'notice'];
    async open(event) {
        event.preventDefault();
        this.opener = event.currentTarget;
        this.employeeId = this.opener.closest('[data-employee-id]').dataset.employeeId;
        this.errorTarget.textContent = '';
        this.contentTarget.textContent = 'Chargement…';
        this.dialogTarget.showModal();
        this.request = new AbortController();
        try {
            const response = await fetch(this.opener.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: this.request.signal
            });
            if (!response.ok || response.redirected) throw new Error();
            this.contentTarget.innerHTML = await response.text();
            this.contentTarget.querySelector('input:not([type="hidden"])')?.focus();
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.contentTarget.textContent = '';
                this.errorTarget.textContent = 'Impossible de charger les informations. Fermez puis réessayez.';
            }
        }
    }
    async save(event) {
        event.preventDefault();
        if (this.saving) return;
        const form = event.target;
        if (!form.reportValidity()) return;
        this.saving = true;
        this.errorTarget.textContent = '';
        const buttons = [...form.querySelectorAll('button')];
        buttons.forEach(button => { button.disabled = true; });
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await response.text();
            if (response.status === 422) {
                this.contentTarget.innerHTML = html;
                this.contentTarget.querySelector('[role="alert"]')?.setAttribute('tabindex', '-1');
                this.contentTarget.querySelector('[role="alert"]')?.focus();
                return;
            }
            if (!response.ok || !response.redirected) throw new Error();
            const page = new DOMParser().parseFromString(html, 'text/html');
            const selector = `[data-employee-id="${this.employeeId}"]`;
            const replacement = page.querySelector(selector);
            const existing = this.element.querySelector(selector);
            if (!replacement || !existing) throw new Error();
            existing.replaceWith(replacement);
            this.opener = replacement.querySelector('[aria-haspopup="dialog"]');
            this.noticeTarget.textContent = 'Informations de l’employé mises à jour.';
            // Allow Stimulus to refresh its row targets before reapplying the filters.
            requestAnimationFrame(() => this.dispatch('updated'));
            this.dialogTarget.close();
        } catch {
            this.errorTarget.textContent = 'Enregistrement non confirmé. Réessayez ou rechargez la liste pour vérifier.';
        } finally {
            this.saving = false;
            buttons.forEach(button => { button.disabled = false; });
        }
    }
    close() { if (!this.saving) this.dialogTarget.close(); }
    cancel(event) { if (this.saving) event.preventDefault(); }
    closed() {
        this.request?.abort();
        this.opener?.focus();
    }
    disconnect() { this.request?.abort(); if (this.hasDialogTarget) this.dialogTarget.close(); }
}
