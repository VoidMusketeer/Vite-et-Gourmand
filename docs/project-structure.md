# Vite et Gourmand: project structure

The application uses Symfony, Twig, Doctrine and PostgreSQL, with Stimulus for
browser interactions. It includes public pages, registration and login, customer
accounts, ordering, and employee and administrator workspaces.

See [database setup and table guide](database.md) for database installation and
relationships.

## Backend: src/

- `Controller/PublicSite/`: public navigation, menu pages and basket actions.
- `Controller/`: registration, checkout, customer accounts and reviews, employee
  operations and administration.
- `Entity/`: Doctrine models, including User, Menu, Order and Payment.
- `Catalog/`: menus, publication, opening hours and customer reviews.
- `Order/`: checkout logic.
- `Employee/`: workspace queries, equipment return deadlines and notifications.
- `Statistics/`: administrator statistics.
- `Security/`: account validation and active-account checks.
- `Doctrine/`: UUID generation and PostgreSQL timestamp handling.
- `Command/`: account setup and employee notification commands.

Security configuration is in `config/packages/security.yaml`. Database migrations
are in `migrations/`, with SQL schema and seed files in `database/postgresql/`.

## Views: templates/

- `base.html.twig`: shared page layout.
- `pages/`: public pages, login, registration, basket and checkout.
- `customer/`: customer account, profile and order views.
- `admin/`: administrator workspace.
- `employee/`: employee workspace.
- `components/`: shared navigation, footer, menu cards, filters, carousels and
  pagination.

## Frontend: assets/

- `styles/`: Sass sources and CSS stylesheets. `app.scss` is compiled to `app.css`
  with `npm run build:css`; `npm run watch:css` rebuilds it during development.
- `controllers/`: Stimulus controllers for forms, menus, navigation and workspaces.
- `app.js`: JavaScript entry point.
- `stimulus_bootstrap.js`: Stimulus initialization.

Public images are in `public/images/`. PHP smoke checks are in `tests/`.
