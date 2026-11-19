import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['email', 'result'];

    togglePassword(event) {
        const button = event.currentTarget;
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        button.querySelector('i').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    toggleRegistration(event) {
        const button = event.currentTarget;
        const information = document.getElementById(button.getAttribute('aria-controls'));
        information.hidden = !information.hidden;
        button.setAttribute('aria-expanded', String(!information.hidden));
    }

    preview(event) {
        event.preventDefault();
        const email = this.emailTarget.value.trim().toLowerCase();
        if (email.startsWith('admin')) {
            event.currentTarget.reset();
            window.location.assign(this.element.dataset.adminDemoUrl);
            return;
        }
        if (!email.startsWith('employe')) {
            event.currentTarget.reset();
            window.location.assign(this.element.dataset.customerDemoUrl);
            return;
        }
        const role = 'employé';
        this.resultTarget.textContent = 'Espace ' + role + ' — Démonstration';
        event.currentTarget.reset();
        this.resultTarget.scrollIntoView({ block: 'nearest' });
    }
}
