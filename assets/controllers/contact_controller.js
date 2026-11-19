import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "details",
        "eventButton",
        "questionButton",
        "title",
        "messageLabel",
        "summary",
    ];

    event() {
        this.setQuestion(false);
    }
    question() {
        this.setQuestion(true);
    }

    setQuestion(active) {
        this.detailsTarget.hidden = active;
        this.detailsTarget.disabled = active;
        this.questionButtonTarget.setAttribute("aria-pressed", String(active));
        this.eventButtonTarget.setAttribute("aria-pressed", String(!active));
        this.titleTarget.textContent = active
            ? "Votre question"
            : "Votre projet, vos envies";
        this.messageLabelTarget.textContent = active
            ? "Votre question *"
            : "Parlez-nous de votre projet *";
        this.clear();
    }

    clear() {
        this.summaryTarget.textContent = "";
    }

    preview(event) {
        event.preventDefault();
        const lines = ["Aperçu de votre demande"];
        for (const field of event.currentTarget.elements) {
            if (
                !field.name ||
                field.matches(":disabled") ||
                !field.value.trim()
            )
                continue;
            const label = field.labels[0].cloneNode(true);
            label.querySelector("input, select, textarea").remove();
            lines.push(label.textContent.trim() + " : " + field.value);
        }
        this.summaryTarget.textContent = lines.join("\n");
        this.summaryTarget.scrollIntoView({ block: "nearest" });
    }
}
