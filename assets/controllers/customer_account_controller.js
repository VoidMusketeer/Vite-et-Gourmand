import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["order", "count", "empty", "feedback"];
    static values = { demo: Boolean };

    normalize(value) {
        return value
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase();
    }

    search(event) {
        const query = this.normalize(event.target.value.trim());
        let count = 0;
        this.orderTargets.forEach((order) => {
            order.hidden = !this.normalize(order.dataset.search).includes(
                query,
            );
            if (!order.hidden) count++;
        });
        this.countTarget.textContent = count;
        this.emptyTarget.hidden = count > 0;
    }

    details(event) {
        const id = event.currentTarget.getAttribute("aria-controls");
        const panel = document.getElementById(id);
        panel.hidden = !panel.hidden;
        this.element
            .querySelectorAll(`[aria-controls="${id}"]`)
            .forEach((button) =>
                button.setAttribute("aria-expanded", String(!panel.hidden)),
            );
    }

    open(event) {
        this.opener = event.currentTarget;
        const dialog = document.getElementById(this.opener.dataset.dialog);
        dialog.showModal();
    }

    close(event) {
        const dialog = event.currentTarget.closest("dialog");
        dialog.close();
        dialog.querySelector("form")?.reset();
        this.opener?.focus();
    }

    backdrop(event) {
        if (event.target !== event.currentTarget) return;
        const rect = event.currentTarget.getBoundingClientRect();
        if (
            event.clientX < rect.left ||
            event.clientX > rect.right ||
            event.clientY < rect.top ||
            event.clientY > rect.bottom
        )
            this.close(event);
    }

    submit(event) {
        const form = event.currentTarget;
        const password = form.elements.namedItem("password");
        const confirmation = form.elements.namedItem("confirmation");
        if (password && confirmation && password.value !== confirmation.value) {
            event.preventDefault();
            confirmation.setCustomValidity(
                "Les mots de passe doivent être identiques.",
            );
            confirmation.reportValidity();
            confirmation.addEventListener(
                "input",
                () => confirmation.setCustomValidity(""),
                { once: true },
            );
            return;
        }
        if (!this.demoValue) return;
        event.preventDefault();
        this.close(event);
        this.feedbackTarget.textContent =
            "Démonstration : votre saisie est valide. Aucune donnée n’a été enregistrée ni envoyée.";
        this.feedbackTarget.hidden = false;
        this.feedbackTarget.scrollIntoView({
            block: "center",
            behavior: "smooth",
        });
    }

    disconnect() {
        this.element
            .querySelectorAll("dialog[open]")
            .forEach((dialog) => dialog.close());
    }
}
