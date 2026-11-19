import { Controller } from "@hotwired/stimulus";

function node(tag, text, attributes = {}) {
    const element = document.createElement(tag);
    element.textContent = text;
    for (const [key, value] of Object.entries(attributes))
        element.setAttribute(key, value);
    return element;
}

function entry(...children) {
    const row = node("div", "", { class: "entry" });
    row.append(...children);
    return row;
}

const catalog = [
    ["Menu Printemps", 26, 6, "printemps/menu-paques"],
    ["Jardin Gourmand", 25, 4, "jardin/menu-jardin"],
    ["Table de fête", 39, 8, "fete/menu-fete"],
    ["Menu Mariage", 45, 20, "marriage/menu-mariage"],
    ["Buffet Convivial", 18, 10, "buffet-association/menu-association"],
    ["Menu Halloween", 22, 6, "halloween/menu-halloween"],
    ["Cocktail d’Entreprise", 32, 15, "cocktail-entreprise/menu-cocktail"],
    ["Buffet du Tournoi", 15, 10, "buffet-tournoi/menu-tournoi"],
    ["Menu 100% Végétal", 27, 4, "vegetal/menu-vegan"],
    ["Saveurs d’Orient", 29, 6, "oriental/menu-oriental"],
    ["Menu de Noël", 42, 6, "noel/menu-noel"],
];
const actions = {
    "Nouvelle demande": "Examiner la demande",
    "Information manquante": "Voir les informations manquantes",
    "À confirmer": "Confirmer la commande",
    Livrée: "Voir la commande",
    Confirmée: "Voir la commande",
};

export default class extends Controller {
    find(selector) {
        return this.element.querySelector(selector);
    }

    connect() {
        this.menus = catalog.map(([name, price, min, path]) => ({
            name,
            price,
            min,
            image: "/images/menus/" + path + ".jpg",
            state: "published",
        }));
        this.requests = [
            ["1048", "L. Bernard · 14 pers. · 25 juil.", "Nouvelle demande"],
            ["1049", "C. Martin · 8 pers. · 25 juil.", "Information manquante"],
            ["1050", "Entreprise Atlas · 30 pers. · 28 juil.", "À confirmer"],
            ["1046", "L. Bernard · 6 pers. · 12 juin", "Livrée"],
        ];
        this.staff = [
            {
                name: "Sofia Lemaire",
                role: "Livraison · Lun – Ven, 8 h – 16 h",
                tasks: [
                    [
                        "Livraison Jardin Gourmand",
                        "VG-1049",
                        "25 juillet · 18 h 30",
                    ],
                    [
                        "Livraison Cocktail d’entreprise",
                        "VG-1050",
                        "28 juillet · 11 h",
                    ],
                ],
            },
            {
                name: "Hugo Martins",
                role: "Cuisine · Mar – Sam, 7 h – 15 h",
                tasks: [
                    ["Préparer les entrées", "VG-1048", "25 juillet · 8 h"],
                    ["Dresser les plats", "VG-1048", "25 juillet · 10 h"],
                    [
                        "Préparer Jardin Gourmand",
                        "VG-1049",
                        "25 juillet · 12 h",
                    ],
                    ["Préparer le cocktail", "VG-1050", "28 juillet · 9 h"],
                ],
            },
        ];
        this.urls = [];
        this.orders();
        this.team();
        this.catalog();
        this.navigate();
    }

    disconnect() {
        this.urls.forEach((url) => URL.revokeObjectURL(url));
    }

    navigate() {
        const fragment = location.hash.slice(1);
        const editing = /^modifier-(\d+)$/.exec(fragment);
        const index = editing ? Number(editing[1]) : -1;
        const pages = [...this.element.querySelectorAll("[data-page]")];
        const page = this.menus[index]
            ? "nouveau"
            : pages.some((p) => p.dataset.page === fragment)
              ? fragment
              : "tableau";
        pages.forEach((p) => (p.hidden = p.dataset.page !== page));
        for (const link of this.element.querySelectorAll("[data-tab]")) {
            link.removeAttribute("aria-current");
            if (link.dataset.tab === (page === "nouveau" ? "menus" : page))
                link.setAttribute("aria-current", "page");
        }
        this.editing = index;
        if (page === "nouveau") {
            const form = this.find('[data-form="menu"]');
            form.reset();
            const menu = this.menus[index];
            this.find("[data-editor-title]").textContent = menu
                ? "Modifier le menu"
                : "Nouveau menu";
            if (menu)
                for (const input of form.elements) {
                    if (input.name && input.type !== "file")
                        input.value = menu[input.name] ?? "";
                }
        }
    }

    orders() {
        const query = this.find("[data-query]")
            .value.trim()
            .toLocaleLowerCase("fr");
        const filter = this.find("[data-filter]").value;
        const pending = this.requests.filter(
            (row) => !["Confirmée", "Livrée"].includes(row[2]),
        );
        this.find("[data-pending]").textContent =
            pending.length + " demande" + (pending.length === 1 ? "" : "s");
        for (const list of this.element.querySelectorAll("[data-orders]")) {
            const rows =
                list.dataset.orders === "pending"
                    ? pending
                    : this.requests.filter(
                          ([id, info, state]) =>
                              ("#VG-" + id + " " + info)
                                  .toLocaleLowerCase("fr")
                                  .includes(query) &&
                              (!filter || filter === state),
                      );
            list.replaceChildren(
                ...rows.map(([id, info, state]) =>
                    entry(
                        node("b", "#VG-" + id),
                        node("span", info),
                        node("mark", state),
                        node("button", actions[state], { "data-order": id }),
                    ),
                ),
            );
            if (!rows.length)
                list.append(entry(node("span", "Aucune commande à afficher.")));
        }
    }

    team() {
        this.find("[data-team-size]").textContent =
            this.staff.length + " employés";
        this.find("[data-team]").replaceChildren(
            ...this.staff.map((person, i) =>
                entry(
                    node("b", person.name),
                    node("span", person.role),
                    node("button", person.tasks.length + " tâches", {
                        "data-tasks": i,
                    }),
                ),
            ),
        );
    }

    catalog() {
        const list = this.find("[data-catalog]");
        const drafts = this.find("[data-drafts]");
        list.replaceChildren();
        drafts.replaceChildren();
        const products = this.find("[data-products]");
        products.replaceChildren(node("option", "Tous les menus"));
        for (const [index, menu] of this.menus.entries()) {
            const info = node("span", "");
            info.append(
                node("b", menu.name),
                node("br", ""),
                menu.price + " €/pers. · dès " + menu.min + " personnes",
            );
            const row = entry(
                info,
                node("a", "Modifier", {
                    class: "button",
                    href: "#modifier-" + index,
                }),
            );
            if (menu.image)
                row.prepend(node("img", "", { alt: "", src: menu.image }));
            if (menu.state === "draft") {
                if (!drafts.childElementCount)
                    drafts.append(node("h2", "Brouillons"));
                drafts.append(row);
            } else {
                list.append(row);
                products.append(node("option", menu.name));
            }
        }
        this.find("[data-catalog-title]").textContent =
            "Menus en ligne (" + list.childElementCount + ")";
    }

    detail(title, children) {
        this.find("[data-detail-title]").textContent = title;
        this.find("[data-detail-body]").replaceChildren(...children);
        this.find("[data-detail]").showModal();
    }

    click(event) {
        const button = event.target.closest("button");
        if (!button) return;
        const d = button.dataset;
        if ("close" in d) button.closest("dialog").close();
        if (d.open === "discount") this.find("[data-discount]").showModal();
        if ("tasks" in d) {
            const person = this.staff[d.tasks];
            this.detail(
                "Tâches de " + person.name,
                person.tasks.length
                    ? person.tasks.map(([task, id, date]) => {
                          const item = node("p", "");
                          item.append(
                              node("b", task),
                              node("br", ""),
                              "Commande #" + id,
                              node("br", ""),
                              "Échéance · " + date,
                          );
                          return item;
                      })
                    : [node("p", "Aucune tâche prévue.")],
            );
        }
        if (d.order) {
            const row = this.requests.find(([id]) => id === d.order);
            if (row[2] === "À confirmer") {
                row[2] = "Confirmée";
                this.orders();
                this.find("[data-status]").textContent =
                    "Commande #VG-" + row[0] + " confirmée (démo).";
            } else {
                const details = [node("p", row[1]), node("p", row[2])];
                if (row[2] === "Information manquante")
                    details.push(
                        node(
                            "p",
                            "Adresse de livraison à préciser avec le client.",
                        ),
                    );
                if (row[2] === "Nouvelle demande")
                    details.push(
                        node("button", "Passer à la confirmation", {
                            "data-examine": row[0],
                        }),
                    );
                this.detail("Commande #VG-" + row[0], details);
            }
        }
        if (d.examine) {
            this.requests.find(([id]) => id === d.examine)[2] = "À confirmer";
            this.find("[data-detail]").close();
            this.orders();
        }
        if (d.review) {
            const card = button.closest("article");
            card.querySelector("mark").textContent = d.review;
            for (const action of card.querySelectorAll("[data-review]"))
                action.hidden = action.dataset.review === d.review;
        }
    }

    submit(event) {
        const form = event.target;
        if (!form.dataset.form) return;
        event.preventDefault();
        const values = Object.fromEntries(new FormData(form));
        const draft = event.submitter?.value === "draft";
        if (values.start && values.end && values.end < values.start) {
            this.find("[data-status]").textContent =
                "La date de fin doit suivre la date de début.";
            form.elements.end.setCustomValidity(
                "Choisissez une date de fin égale ou postérieure au début.",
            );
            form.elements.end.reportValidity();
            form.elements.end.setCustomValidity("");
            return;
        }
        let message;
        if (form.dataset.form === "menu") {
            const image = values.image;
            values.image = this.menus[this.editing]?.image || "";
            if (image.size) {
                values.image = URL.createObjectURL(image);
                this.urls.push(values.image);
            }
            values.name = values.name.trim() || "Menu sans titre";
            values.state = draft ? "draft" : "published";
            if (this.editing >= 0) this.menus[this.editing] = values;
            else this.menus.push(values);
            this.catalog();
            location.hash = "menus";
            message = draft ? "Brouillon enregistré" : "Menu publié";
        } else if (form.dataset.form === "employee") {
            this.staff.push({
                name: values.name,
                role: values.role,
                tasks: [],
            });
            this.team();
            message = "Employé ajouté";
        } else {
            const unit = values.type === "Pourcentage" ? " %" : " €";
            this.find("[data-discounts]").append(
                entry(
                    node("b", values.code || values.product),
                    node(
                        "span",
                        values.product +
                            " · −" +
                            values.value +
                            unit +
                            " · du " +
                            values.start +
                            " au " +
                            values.end,
                    ),
                    node("mark", "Configurée"),
                ),
            );
            this.find("[data-discount]").close();
            message = "Réduction ajoutée";
        }
        form.reset();
        this.find("[data-status]").textContent = message + " (démo).";
    }
}
