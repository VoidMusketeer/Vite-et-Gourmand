# Security and compliance review

Date: 19 November 2026. Scope: current working tree, including existing uncommitted changes. Assumption: a French catering business serving EU consumers. This is a source/configuration review and dependency audit, not certification or a production penetration test.

## Assessment

Not ready for a production security/compliance sign-off. The following findings need resolution or deployment-specific verification. No application code or credentials were changed.

## Priority findings

1. **High — vulnerable dependency versions.** `composer audit --locked` reports 20 advisory entries across 13 Symfony packages: config, expression-language, filesystem, framework-bundle, http-client, http-foundation, http-kernel, mime, routing, security-http, serializer, validator and yaml. Examples include local-file reads, routing/access-control edge cases and denial of service. `npm audit` reports one high-severity advisory in source-map-js, GHSA-68fv-2mgg-jv7q, affecting versions below 1.2.2. This is a development dependency through the styling toolchain. Advisory presence does not establish exploitability in this application. Update affected packages within supported constraints, rerun audits and regression checks. Both audits initially failed DNS resolution but succeeded on retry with network access.

2. **High — publication does not require verified allergens.** `src/Catalog/MenuPublisher.php:39` accepts published status and subsequently creates new dishes without allergen associations or review. Editing also replaces nonoptional dish associations. `src/Catalog/MenuCatalog.php:58` falls back to legacy metadata when review is incomplete, which can be empty or stale after composition changes. `templates/pages/menu_detail.html.twig:46` displays that list without identifying incomplete review. Require a completed allergen review for every included dish/option before publication and after ingredient changes, and retain the ordered composition. Actual recipe accuracy still requires kitchen verification.

3. **High — incomplete privacy/legal information.** `src/Controller/PublicSite/NavigationController.php` serves placeholder pages for `/confidentialite` and `/mentions-legales`; `templates/pages/placeholder.html.twig` says they are under construction. Registration collects identity, contact and address data while requiring acceptance of the missing privacy policy. Supply the actual controller identity/contact, purposes and legal bases, recipients, retention periods, rights and complaint information, and business/hosting identification.

4. **High — allergy information collected as ordinary order notes.** `templates/pages/checkout.html.twig:29` expressly requests allergies; `src/Order/Checkout.php` saves these with the order. Identifiable allergy information may be health data. Establish an Article 6 basis and applicable Article 9 exception; an ordinary CGV checkbox is not specific explicit consent. Minimize collection, define restricted access and deletion/retention rules, and supply clear information at collection. No dedicated health-data safeguards or retention process were found in reviewed code; external organizational procedures were not available.

5. **Medium — predictable demonstration credentials can be provisioned in production.** `src/Command/EasyAccessAccountsCommand.php` resets active accounts, including an administrator, to deterministic passwords without an environment restriction. `src/Command/SeedCustomersCommand.php` also uses predictable demonstration passwords. Restrict these commands to isolated development/test environments and ensure demonstration accounts are disabled or removed before deployment. Current production account state was not queried.

6. **Medium — credential backups inside the public document root.** `public/passwords/employe/backup/*.php` and `public/passwords/user/comptesClient.php` contain password-labelled records. They have PHP 404/exit guards; the employee directory also has an Apache denial file. Thus direct credential disclosure is NOT established. Their safety depends on server execution/configuration and they should be stored outside public entirely. Administrator files are empty. Never include these backups in deployment artifacts; rotate affected credentials if past exposure is established.

7. **Medium — no application login throttling.** `config/packages/security.yaml` enables password login and CSRF but does not configure login throttling. No equivalent application limiter was found. Add account/IP-aware throttling and abuse controls for registration; verify any reverse-proxy controls. This is a brute-force exposure, not a demonstrated account takeover.

8. **Compliance gap — CGV cover equipment return only.** `templates/pages/equipment_terms.html.twig` omits general ordering, pricing/payment, performance/delivery, complaints and mediation provisions. Complete terms for the actual business and correctly explain applicable withdrawal rights or exceptions. Checkout currently describes a request with payment agreed after confirmation; establish when a binding paid contract forms before choosing mandatory payment-button wording. Do not assume all catering sales have a 14-day withdrawal right.

## Positive controls observed

- Administrative and employee controllers use role authorization attributes; customer order operations scope queries to the authenticated owner.
- State-changing handlers inspected validate CSRF tokens.
- Password hashing and current-password verification for sensitive profile changes are present.
- Reviewed database operations use bound parameters; Twig escapes normal output.
- Menu image uploads limit size and detected image type and generate filenames.
- Checkout recomputes prices server-side and uses transactions, row locks and submission deduplication.

These observations are source evidence, not proof of complete vulnerability coverage. The empty access_control section alone does not bypass controller authorization attributes.

## Outstanding verification

Production HTTPS/TLS, security headers, cookie flags behind the real proxy, public-file handling, debug/profiler exposure, database/network permissions, backups and restoration, secret management, monitoring, data-rights procedures, processor contracts and retention remain unverified. No deployed URL was provided. No live exploit attempts or database-changing smoke tests were run. Existing smoke tests were inspected only. Food hygiene, cold-chain, traceability and staff procedures cannot be certified from website code. Cookie consent requirements depend on actual trackers; absence of a banner alone is not a finding.

## Official references

- CNIL, transparency and Article 13: https://www.cnil.fr/fr/reglement-europeen-protection-donnees/chapitre3
- CNIL, legal bases and special categories under Article 9: https://www.cnil.fr/fr/reglement-europeen-protection-donnees/chapitre2
- French public service, e-commerce requirements: https://entreprendre.service-public.gouv.fr/vosdroits/F23455
- French public service, CGV: https://entreprendre.service-public.gouv.fr/vosdroits/F33527
- DGCCRF, food information and allergens: https://www.economie.gouv.fr/dgccrf/les-fiches-pratiques/etiquetage-des-denrees-alimentaires-les-regles-connaitre

## Recommended sequence

Update vulnerable dependencies; prevent unreviewed allergen publication; remove demonstration credentials from production and relocate credential backups; add throttling; complete legal/privacy and allergy-data handling; then validate the real deployment and run isolated security regression tests.

