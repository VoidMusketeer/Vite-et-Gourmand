# Employee workspace

Authenticated employees enter at `/employe` (also reached through `/compte`).

- `/employe`: today's team orders, portions, position responsibilities, and personal daily checks for logistics employees.
- `/employe/commandes`: database order search, service date and status filters.
- `/employe/commandes/{id}`: order snapshot, customer instructions, delivery address, catalogue dishes/allergens and status history.
- `/employe/planning`: daily team deliveries with seven-day navigation and the signed-in employee's recorded working hours.
- `/employe/compte`: editable personal details and password; read-only position, responsibilities and working hours.

Data comes from PostgreSQL through the existing Doctrine connection. The database contained ten employee accounts and no orders at implementation time. No demonstration orders, assignments, departure times or preparation deadlines are generated. The existing `demo_open_task_count` is not used. Individual assignments and structured dietary adaptations are not present in the schema; the interface labels orders as shared team data and displays customer notes without inventing adaptations. Allergen details describe the current catalogue, not a historical order snapshot.

All authenticated employees may accept orders and advance them through preparation, delivery and completion, as required by the employee specification. Equipment loans determine whether completion requires the return stage. Every transition locks and rechecks the current status, restricts the action to the employee's department, and inserts an attributed history entry in the same transaction. All mutations use POST and CSRF tokens. Email changes require the current password; password changes require the current password and matching confirmation.

Migration `Version20261117200000` adds `employee_daily_checks`, keyed by employee, Paris service date and check code. These records persist across sessions. The employee layout overrides only the base header/footer blocks, leaving the public and administrator layouts unchanged.

## Local validation

```powershell
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lint:twig templates/employee
php bin/console lint:container
php tests/employee_smoke.php
php -S 127.0.0.1:8085 -t public
```

The smoke script uses an isolated dev kernel cache and the configured local database. It needs an existing logistics employee, customer and menu. All temporary database changes are enclosed in a transaction and rolled back. It checks order queries, detail rendering, department restrictions, stale transitions and audit history. Do not use it against production.


## Employee specification implementation

Employees now manage `/employe/menus`, `/employe/plats`, `/employe/horaires` and `/employe/avis`.
Menu removal archives the menu so existing orders keep their references. Dish removal deactivates the dish and unpublishes affected menus until the dish is replaced. Changes to opening hours immediately feed the public footer. Reviews approved by employees enter the same homepage feed as administrator-approved reviews; rejected reviews leave that feed.

Order search accepts reference, menu, full client name or email, with date and status filters. Before dispatch, employees can change delivery date/time/address, instructions and equipment-loan details, or cancel. These actions require a declared completed phone/email contact, contact timestamp and reason. The server checks all three and records the actor plus before/after changes in `order_contact_actions`. A telephone/mail link alone does not authorize a mutation. Normal workflow status updates do not require a new customer contact.

An order without equipment can finish after delivery. A loaned order must pass through equipment-return waiting, then completion records restitution. The return deadline is ten working days starting the next day, excluding weekends and national metropolitan French public holidays. Entering the waiting state atomically creates a notification in `employee_mail_outbox`; its text includes the deadline, 600-euro fee and instructions to reply to organize return. The equipment clause is visible at `/cgv`.

### Mail setup still required

The configured default `MAIL_FROM_ADDRESS` is `ViteEtGourmand@gmail.com`, with display name `Vite & Gourmand`, also used as the reply-to address. An operational, authenticated `MAILER_DSN` is still required in the deployment environment; the current null transport does not send messages. An unconfigured or failed transport leaves notifications visibly pending, never marked as sent. The employee can retry from the order page; operational retries can use `php bin/console app:employee-notifications` (schedule this command in the deployment environment). This command may also handle employee-account notifications added by the administration workflow.

A successful mail transport acceptance is tracked, not proof of inbox delivery. The integration suite uses an in-memory transport and sends no actual email.

The existing catalogue has imported menu-level allergen labels without dish-level associations. Those labels are retained until all dishes in a menu have been explicitly reviewed; no dish/allergen associations are invented. The hours previously displayed in the footer were imported into `opening_hours` without replacing existing rows.

Run `php tests/employee_smoke.php` for rollback-only coverage of workflow branches, contact enforcement, cancellation audit, working-day deadlines, notification queuing/sending, catalogue mutations, opening hours, review visibility and CSRF rejection.
