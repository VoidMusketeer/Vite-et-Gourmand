import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    togglePassword(event) {
        const button = event.currentTarget;
        const input = document.getElementById(
            button.getAttribute("aria-controls"),
        );
        const visible = input.type === "password";
        input.type = visible ? "text" : "password";
        button.setAttribute("aria-pressed", String(visible));
        button.setAttribute(
            "aria-label",
            visible ? "Masquer le mot de passe" : "Afficher le mot de passe",
        );
        button.querySelector("i").className = visible
            ? "bi bi-eye-slash"
            : "bi bi-eye";
    }

}
