import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "panel",
        "step",
        "delivery",
        "serviceLabel",
        "fee",
        "amount",
        "fulfillment",
    ];
    static values = { total: Number };

    connect() {
        this.index = 0;
        this.show(false);
        this.service();
    }
    next(event) {
        event.preventDefault();
        if (!event.target.reportValidity()) return;
        this.index = Math.min(2, this.index + 1);
        this.show();
    }
    back() {
        this.index = Math.max(0, this.index - 1);
        this.show();
    }
    submit(event) {
        const serviceForm = this.panelTargets[0].querySelector("form");
        if (!serviceForm.checkValidity()) {
            event.preventDefault();
            this.index = 0;
            this.show();
            serviceForm.reportValidity();
            return;
        }
        event.target
            .querySelectorAll("[data-service-copy]")
            .forEach((input) => input.remove());
        for (const [name, value] of new FormData(serviceForm)) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = value;
            input.dataset.serviceCopy = "";
            event.target.append(input);
        }
    }
    show(focus = true) {
        this.panelTargets.forEach((panel, index) => {
            panel.hidden = index !== this.index;
        });
        this.stepTargets.forEach((step, index) => {
            if (index === this.index) step.setAttribute("aria-current", "step");
            else step.removeAttribute("aria-current");
        });
        if (focus) this.panelTargets[this.index].querySelector("h1").focus();
    }
    service() {
        const delivery =
            this.element.querySelector('[name="service"]:checked').value ===
            "delivery";
        this.deliveryTargets.forEach((label) => {
            label.hidden = !delivery;
            label.querySelector("input").required = delivery;
            label.querySelector("input").disabled = !delivery;
        });
        this.serviceLabelTarget.textContent = delivery
            ? "Livraison"
            : "Retrait en boutique";
        if (this.hasFulfillmentTarget)
            this.fulfillmentTarget.textContent = delivery
                ? "livraison"
                : "retrait en boutique";
        const money = (value) =>
            new Intl.NumberFormat("fr-FR", {
                style: "currency",
                currency: "EUR",
            }).format(value);
        this.feeTarget.textContent = money(delivery ? 15 : 0);
        this.amountTarget.textContent = money(
            this.totalValue + (delivery ? 15 : 0),
        );
    }
    reset() {
        this.element.querySelectorAll("form").forEach((form) => form.reset());
        this.index = 0;
        this.show(false);
        this.service();
    }
}
