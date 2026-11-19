# Vite et Gourmand: project structure

This is a folder scaffold. Pages, authentication, payment processing and forms still need implementation.

The PostgreSQL database schema and initial Doctrine migration are now available.
See [database setup and table guide](database.md) for installation, relationships
and the remaining application integration work.

## Backend: src/

- `Controller/PublicSite/`: public pages, accessible to visitors.
- `Controller/Account/`: registered customers' account pages.
- `Controller/Admin/`: administration pages.
- `Controller/Employee/`: employee workspace.
- `Controller/Menu/`: menu listing and detail actions.
- `Controller/Payment/`: checkout, return and payment notification actions.
- `Entity/`: database models; future examples: User, Menu, Order, Payment.
- `Repository/`: database queries for those models.
- `Form/Registration/`, `Form/Account/`, `Form/Menu/`, `Form/Payment/`: PHP form classes.
- `Service/Payment/`: payment provider integration and payment logic.
- `Service/Menu/`: menu-related business logic.
- `Security/`: authenticators and voters (object-level permission checks).
- `Validator/`: custom validation constraints and their validators. Standard constraints such as NotBlank and Email belong on the relevant entity or form data object.

Use one User entity with roles such as ROLE_USER, ROLE_EMPLOYEE and ROLE_ADMIN. An anonymous visitor does not need a User record. Folder names do not enforce access: configure access rules in config/packages/security.yaml and/or controller authorization attributes. No roles or access rules have been implemented by this scaffold.

## Views: templates/

- `base.html.twig`: shared page layout (already present).
- `pages/`: public pages such as home, about and contact.
- `account/`, `admin/`, `employee/`: views for the corresponding user areas.
- `security/`: login and registration pages.
- `menu/`: menu list and detail templates. Individual menus should be database records, not separate folders.
- `payment/`: checkout and payment result pages.
- `components/`: reusable Twig partials; suggested names: _navbar.html.twig, _header.html.twig, _footer.html.twig, _search_bar.html.twig.
- `form/`: shared form partials and Twig form themes; PHP form definitions belong in src/Form/.

Include a component with `{% include 'components/_navbar.html.twig' %}` after creating that template.

## Frontend: assets/

- `styles/`: CSS; app.css remains the existing stylesheet entry point.
- `styles/scss/`: reserved for Sass source files. A Sass compiler must be installed and configured before SCSS can be used; this scaffold does not configure one.
- `controllers/`: existing Stimulus folder for interactive behavior such as menu filtering, navigation toggles and search.
- `app.js`: existing JavaScript entry point.

Payment forms should use the selected payment provider's hosted fields or checkout. Provider selection and integration remain to be implemented.

Empty folders contain .gitkeep so Git can preserve the scaffold. Remove those placeholders when adding actual files.
