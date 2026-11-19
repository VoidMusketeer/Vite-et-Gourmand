import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['password', 'confirmation'];

    validate() {
        const password = this.passwordTarget.value;
        const valid = [...password].length >= 10 && new TextEncoder().encode(password).length <= 72
            && /\p{Lu}/u.test(password) && /\p{Ll}/u.test(password)
            && /[0-9]/.test(password) && /[^\p{L}\p{N}\s]/u.test(password);
        this.passwordTarget.setCustomValidity(valid ? '' : 'Utilisez 10 caractères minimum, une majuscule, une minuscule, un chiffre et un caractère spécial (72 octets maximum).');
        this.confirmationTarget.setCustomValidity(this.confirmationTarget.value === password ? '' : 'Les mots de passe ne correspondent pas.');
    }

    toggle(event) {
        const visible = this.passwordTarget.type === 'password';
        this.passwordTarget.type = visible ? 'text' : 'password';
        event.currentTarget.textContent = visible ? 'Masquer' : 'Afficher';
        event.currentTarget.setAttribute('aria-pressed', String(visible));
    }
}
