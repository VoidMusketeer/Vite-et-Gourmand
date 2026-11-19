import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'role', 'row', 'count', 'empty'];
    connect() { this.filter(); }
    filter() {
        const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr');
        const words = normalize(this.searchTarget.value).trim().split(/\s+/).filter(Boolean);
        const role = this.roleTarget.value;
        let visible = 0;
        for (const row of this.rowTargets) {
            const name = normalize(row.dataset.name);
            const matches = words.every(word => name.includes(word)) && (!role || row.dataset.role === role);
            row.hidden = !matches;
            if (matches) visible++;
        }
        this.countTarget.textContent = `${visible} employé${visible > 1 ? 's' : ''} sur ${this.rowTargets.length}`;
        this.emptyTarget.hidden = visible !== 0 || this.rowTargets.length === 0;
    }
}
