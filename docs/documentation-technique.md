# Documentation technique — Vite et Gourmand

Version documentaire : 19 novembre 2026. Ce document décrit le code présent dans le dépôt à cette date. Il s’adresse aux développeurs et aux personnes chargées de l’installation et de la maintenance. Les procédures de déploiement proposées ne constituent pas un déploiement déjà réalisé.

## 1. Présentation et périmètre

Vite et Gourmand est une application web de traiteur. Elle permet de consulter les menus, de constituer un panier, de transmettre une commande et de suivre sa réalisation. Des espaces dédiés permettent aux employés de gérer l’activité et aux administrateurs de gérer les comptes et les statistiques.

| Profil | Fonctionnalités principales |
| --- | --- |
| Visiteur | Accueil, catalogue filtrable, détail des menus, présentation de l’entreprise, inscription et connexion |
| Client | Panier, commande, coordonnées, suivi et actions autorisées sur ses commandes, dépôt d’un avis |
| Employé | Commandes, planning, suivi du matériel, menus, plats, horaires, modération des avis et compte personnel |
| Administrateur | Gestion des employés, menus, commandes, avis, sélection des contenus d’accueil et statistiques |

Le parcours nommé « paiement » enregistre une demande de commande. Il ne réalise pas de débit bancaire. La présence de tables de paiement ne signifie pas qu’un prestataire de paiement est intégré.

## 2. Technologies

| Couche | Technologie et rôle |
| --- | --- |
| Serveur | PHP et Symfony 7.4 : routage, contrôleurs, services, sécurité et commandes |
| Rendu | Twig : génération des pages HTML côté serveur |
| Accès aux données | Doctrine ORM pour les entités ; Doctrine DBAL pour les requêtes SQL et transactions métier |
| Base principale | PostgreSQL 16 : comptes, catalogue, commandes, historique et notifications |
| Statistiques | MongoDB, interrogé par un script Node.js avec le pilote `mongodb` |
| Interface | JavaScript, Stimulus, Symfony UX Turbo, CSS et Sass |
| Ressources frontales | Symfony AssetMapper et Importmap ; compilation Sass par npm |
| Courriels | Symfony Mailer, Messenger et file PostgreSQL ; boîte de réception Mailpit en développement |
| Environnement local | Docker Compose pour PostgreSQL et Mailpit ; MongoDB dans un conteneur distinct |
| Vérifications | Scripts PHP de contrôle fonctionnel, outils de lint Symfony et configuration PHPUnit |

Le nom du projet ne désigne pas l’outil de compilation Vite : les scripts npm présents compilent le Sass.

`composer.json` déclare PHP `>=8.2`, mais les dépendances verrouillées et les outils de développement peuvent imposer davantage. Le README retient PHP **8.4.1 ou supérieur** et Node.js **20.19.0 ou supérieur**. `composer check-platform-reqs` doit confirmer la compatibilité de l’installation réelle.

## 3. Architecture et organisation

L’application suit une architecture de rendu serveur avec contrôleurs, services métier et vues. Les routes sont principalement déclarées par attributs PHP. Le conteneur Symfony injecte les dépendances des services.

```text
Navigateur — HTML / CSS / Stimulus
    |
    v
public/index.php → Symfony → contrôleurs → vues Twig
                                |
                                v
                          services métier
                            |          |
                            v          v
                       PostgreSQL    AdminStatistics
                            |          |
                            v          v
                       notifications  Node.js → MongoDB
                            |
                            v
                         SMTP / Mailpit
```

| Répertoire | Contenu |
| --- | --- |
| `src/Controller/` | Traitement des requêtes et contrôles d’accès |
| `src/Catalog/` | Catalogue, publication, horaires et avis publics |
| `src/Order/` | Validation et enregistrement du panier en commandes |
| `src/Employee/` | Suivi des commandes, délais de retour et notifications |
| `src/Statistics/` | Préparation des données et appel du processus Node.js |
| `src/Security/` | Validation des comptes et contrôle des comptes actifs |
| `src/Entity/` | Entités Doctrine |
| `src/Doctrine/` | Génération d’UUID et adaptation des timestamps PostgreSQL |
| `src/Command/` | Commandes d’administration et de notification |
| `templates/` | Pages publiques, espaces privés et composants Twig |
| `assets/` | Contrôleurs Stimulus, styles et point d’entrée JavaScript |
| `public/` | Point d’entrée HTTP, images et ressources accessibles au navigateur |
| `config/` | Configuration des services et composants Symfony |
| `migrations/` | Évolution versionnée de la base |
| `database/postgresql/` | SQL et données utilisés par les migrations |
| `tests/` | Scripts de vérification et amorçage PHPUnit |
| `design/` | Maquettes et ressources graphiques |

Les services métier utilisent aussi directement DBAL. Toutes les tables ne sont donc pas nécessairement représentées par des entités ORM. Ne pas utiliser `doctrine:schema:update --force` pour aligner automatiquement la base : des tables utiles pourraient être considérées comme non mappées.

## 4. Modèle de données

PostgreSQL est la source de référence des données opérationnelles. Les migrations successives, et non le seul schéma SQL initial, définissent l’état actuel.

| Domaine | Tables principales | Relations et contraintes |
| --- | --- | --- |
| Comptes | `users`, `roles`, `staff_positions` | Un rôle principal par compte ; adresse électronique unique sans distinction de casse grâce à `citext` |
| Catalogue | `menus`, `themes`, `diets`, `menu_images` | Un thème et un régime obligatoires par menu ; plusieurs images possibles |
| Composition | `dishes`, `dish_types`, `menu_dishes`, `allergens`, `dish_allergens` | Plats réutilisables et associations d’allergènes |
| Commandes | `orders`, `order_items`, `checkout_submissions` | Un menu par commande ; déduplication d’une soumission par utilisateur et jeton |
| Suivi | `order_statuses`, `order_status_history`, `order_contact_actions` | Historique des statuts et traçabilité des modifications après contact client |
| Avis | `reviews` | Un avis par commande, note de 1 à 5 et statut de modération |
| Équipe | `employee_daily_checks`, `employee_mail_outbox` | Contrôles quotidiens et notifications à envoyer |
| Informations | `opening_hours`, `contact_messages` | Horaires ; structure prévue pour les demandes de contact |
| Paiement et récupération | `payments`, `payment_events`, `password_reset_tokens` | Structures présentes ; parcours complets encore à intégrer |

La migration `Version20261119020000` rend `menus.theme_id` et `menus.diet_id` obligatoires. Elle remplace l’ancienne association `menu_diets` et les classifications JSON. Le JSON `catalogue` conserve d’autres métadonnées, notamment des options et informations de présentation.

Les commandes conservent des instantanés du titre du menu, du prix, du nombre de personnes et de l’adresse. Une modification ultérieure du catalogue ne doit pas réécrire ces valeurs historiques. Le stock représente ici un nombre de commandes disponibles : chaque menu commandé consomme une unité, indépendamment du nombre de convives.

## 5. Parcours et règles métier

### 5.1 Inscription et authentification

`RegistrationController` vérifie le jeton CSRF, les champs obligatoires, les longueurs, l’adresse électronique, le téléphone, le mot de passe et l’acceptation des conditions. Le mot de passe doit contenir au moins dix caractères, une majuscule, une minuscule, un chiffre et un caractère spécial, sans dépasser 72 octets.

Le compte reçoit le rôle client. Le mot de passe est haché avec le composant Symfony PasswordHasher. La création du compte et la mise en file de l’e-mail de bienvenue sont réalisées dans une transaction avec la configuration Doctrine Messenger du projet.

La connexion utilise l’adresse électronique et une session Symfony. `ActiveUserChecker` et `ActiveAccountSubscriber` contrôlent l’état actif des comptes. `User::getRoles()` ajoute `ROLE_USER` aux employés et administrateurs ; la hiérarchie de sécurité donne également `ROLE_EMPLOYEE` aux administrateurs.

### 5.2 Catalogue et panier

`MenuCatalog` prépare les données publiques. Les thèmes et régimes sont lus par jointure avec les tables de référence. Les paramètres publics de filtrage conservent des libellés.

Le panier est conservé en session. Une entrée identifie un menu et contient notamment le nombre de convives, la date et les options. Les contrôleurs Stimulus améliorent l’interaction, mais les vérifications déterminantes sont effectuées côté serveur.

`MenuPublisher` gère la publication et les images. Les fichiers acceptés sont JPEG, PNG et WebP, avec une limite de 5 Mo, une vérification du type d’image et un nom aléatoire. Ils sont placés dans `public/images/menus/uploads/`.

### 5.3 Enregistrement d’une commande

`Checkout::submit()` réalise les opérations suivantes dans une transaction :

1. Vérifier le jeton de soumission et verrouiller le compte concerné.
2. Rechercher une soumission déjà traitée pour renvoyer ses références sans créer de doublon.
3. Valider le service, les coordonnées, l’adresse et l’acceptation des conditions.
4. Trier les menus puis verrouiller leurs lignes avec `FOR UPDATE`.
5. Vérifier publication, stock, minimum de convives, date et options autorisées.
6. Recalculer les prix côté serveur et créer une commande par menu du panier.
7. Enregistrer l’article, décrémenter le stock, ajouter le statut initial et mémoriser les références de soumission.

Le prix par personne correspond au prix minimum du menu divisé par son nombre minimum de convives, arrondi au centime, puis augmenté des options. Le sous-total est calculé en centimes entiers. Des conversions initiales utilisent toutefois des nombres flottants. La livraison coûte actuellement **15 € par soumission**, portés par la première commande créée ; le retrait n’ajoute pas de frais. Aucune réduction automatique de quantité n’est appliquée par ce service.

Le délai de préparation est converti en date minimale. La comparaison ne vérifie pas précisément le couple date et heure de prestation : cette limite est à prendre en compte si un délai strict en heures est attendu.

### 5.4 Suivi, annulation et matériel

Le cycle normal défini par `EmployeeWorkspace` est :

```text
pending → accepted → in_preparation → out_for_delivery → delivered
                                                           |
                     sans matériel : completed ←───────────┤
                     avec matériel : awaiting_equipment_return → completed
```

Chaque changement autorisé vérifie le statut courant sous verrou et ajoute une entrée d’historique dans la même transaction. Le client ne peut modifier ou annuler sa commande que dans les conditions prévues par son contrôleur, notamment le statut `pending` pour les actions autres qu’un commentaire.

Les modifications et annulations par l’équipe exigent, selon l’action, la déclaration d’un contact client par téléphone ou e-mail, sa date et un motif. Les valeurs avant/après sont conservées dans `order_contact_actions`.

Pour un prêt de matériel, le passage en attente de retour crée une notification. `EquipmentReturnDeadline` calcule dix jours ouvrés à compter du lendemain, en excluant les week-ends et les jours fériés nationaux métropolitains. La notification mentionne l’échéance et la clause de 600 € ; elle ne déclenche pas un encaissement automatique.

### 5.5 Avis et statistiques

Un client peut déposer un avis sur une commande terminée lui appartenant. La contrainte d’unicité empêche plusieurs avis pour la même commande. Les avis publics suivent la modération ; la sélection pour l’accueil est distincte.

`AdminStatistics` extrait les commandes utiles de PostgreSQL, filtre par menu et dates de prestation, puis transmet du JSON sur l’entrée standard de `bin/admin-statistics.mjs`. Le processus dispose d’un délai maximal de 30 secondes.

Le script Node.js écrit un instantané dans `admin_order_snapshots`, avec un identifiant propre à chaque consultation et une expiration après 24 heures via index TTL. MongoDB agrège le nombre de commandes et les revenus par menu. Les commandes annulées sont exclues. Le chiffre d’affaires correspond aux commandes terminées, après réduction et hors livraison ; il ne constitue pas une preuve d’encaissement bancaire. Les instantanés ne contiennent pas les coordonnées clients.

## 6. Principales routes

| Méthode | Route | Usage et accès |
| --- | --- | --- |
| GET | `/` | Accueil public |
| GET | `/menus`, `/menus/{id}` | Catalogue et détail public |
| GET, POST | `/inscription` | Création du compte |
| GET, POST | `/connexion` | Formulaire et traitement de connexion |
| POST | `/deconnexion` | Déconnexion avec protection CSRF |
| GET | `/panier` | Affichage du panier |
| POST | `/panier/{id}`, `/panier/actions` | Ajout et modification du panier |
| GET, POST | `/paiement` | Consultation et validation de commande, compte requis |
| GET | `/compte` | Orientation vers l’espace correspondant au rôle |
| POST | `/compte/avis` | Création d’un avis client |
| GET | `/employe`, `/employe/commandes`, `/employe/planning` | Espace employé |
| GET | `/administration`, `/administration/statistiques` | Espace administrateur |

Cette liste est une sélection. La liste complète se consulte avec `php bin/console debug:router`. Il s’agit principalement de routes HTML avec formulaires, sans API REST publique documentée.

## 7. Installation locale

### 7.1 Prérequis et configuration

Installer PHP avec `pdo_pgsql`, `ctype`, `iconv`, `dom`, `xml` et `xmlwriter`, Composer 2, Node.js, npm, Docker Compose et Symfony CLI. Exécuter les commandes depuis la racine du projet.

Créer un fichier `.env.dev.local` :

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=remplacer_par_un_secret_aleatoire
DEFAULT_URI=http://127.0.0.1:8000
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN="doctrine://default?auto_setup=0"
MAILER_DSN="smtp://127.0.0.1:1025"
MAIL_FROM_ADDRESS="contact@vite-et-gourmand.test"
MONGODB_URI="mongodb://127.0.0.1:27017"
MONGODB_DATABASE=vite_gourmand_statistics
NODE_BINARY=node
```

Ces identifiants PostgreSQL sont ceux du développement local. Les secrets réels ne doivent pas être versionnés. Générer `APP_SECRET` avec :

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### 7.2 Dépendances et services

```sh
composer install
composer check-platform-reqs
npm ci
npm run build:css
docker compose up -d database mailer
docker compose ps
```

Attendre que PostgreSQL soit disponible. Pour MongoDB, au premier lancement :

```sh
docker run -d --name vite-gourmand-mongodb -p 127.0.0.1:27017:27017 -v vite_gourmand_mongodb_data:/data/db mongo:8.0
```

Aux lancements suivants : `docker start vite-gourmand-mongodb`.

### 7.3 Base et démarrage

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports --no-interaction
php bin/console doctrine:migrations:status
symfony server:start --no-tls --port=8000
```

Les migrations initialisent le schéma et les données du projet. Conserver `database/` avec les migrations ; ne pas importer les scripts SQL séparément après cette installation. Le compte PostgreSQL doit pouvoir installer l’extension `citext`.

Dans un second terminal :

```sh
php bin/console messenger:consume async -vv
```

Application : <http://127.0.0.1:8000>. Courriels capturés par Mailpit : <http://127.0.0.1:8025>. Pour recompiler les styles pendant le développement : `npm run watch:css`.

La commande facultative `php bin/console app:accounts:easy-access` crée ou réinitialise des comptes de démonstration. Elle doit être réservée à une installation locale isolée.

## 8. Sécurité et notifications

Les contrôleurs privés emploient les attributs `IsGranted` et des vérifications explicites. La section `access_control` vide de `security.yaml` ne signifie donc pas que ces espaces sont publics. Les opérations client filtrent les données selon le propriétaire connecté.

Les protections observées comprennent le hachage des mots de passe, les jetons CSRF, les requêtes SQL paramétrées, l’échappement Twig, les contrôles serveur des formulaires et les transactions avec verrouillage. Les changements sensibles de profil demandent le mot de passe actuel.

Deux mécanismes de courriel coexistent :

- L’e-mail de bienvenue utilise Mailer et Messenger, avec les transports `async` et `failed` et trois nouvelles tentatives configurées.
- Les notifications métier sont conservées dans `employee_mail_outbox`, avec suivi des tentatives et de l’état d’envoi. Elles peuvent être relancées depuis les écrans concernés ou par `php bin/console app:employee-notifications`.

L’acceptation SMTP ne prouve pas la réception dans la boîte du destinataire. Un arrêt entre l’acceptation SMTP et la confirmation en base peut entraîner un nouvel envoi lors d’une relance.

## 9. Vérification et maintenance

Contrôles de configuration et de rendu :

```sh
php bin/console lint:container
php bin/console lint:yaml config
php bin/console lint:twig templates
php bin/console debug:router
npm run build:css
```

Contrôles fonctionnels fournis :

```sh
php tests/registration_smoke.php
php tests/customer_smoke.php
php tests/employee_smoke.php
php tests/catalog_relations_smoke.php
php tests/optimization_smoke.php
```

Ces scripts utilisent un noyau de développement et la base configurée, avec des écritures temporaires annulées par transaction. Préparer les migrations et les données attendues ; les exécuter sur une base locale dédiée, jamais en production. Le suffixe `_test` configuré pour PHPUnit n’isole pas automatiquement ces scripts autonomes.

Les vérifications couvrent notamment l’inscription, les opérations client, les transitions de commande, les relations du catalogue et certaines optimisations. Le script d’optimisation vérifie la sélection SQL des statistiques sans appeler Node.js ou MongoDB : un contrôle d’intégration séparé reste nécessaire pour ce parcours.

Cette rédaction repose sur la lecture des sources ; elle ne rapporte pas une nouvelle exécution de ces tests.

| Symptôme | Vérifications utiles |
| --- | --- |
| Connexion PostgreSQL impossible | État du conteneur, port 5432, `DATABASE_URL`, extension PHP `pdo_pgsql` |
| Tables absentes | État des migrations et initialisation des transports Messenger |
| E-mail de bienvenue non reçu | Worker `async`, DSN SMTP, Mailpit, `messenger:failed:show` |
| Notification métier en attente | Configuration SMTP, état de l’outbox et commande de relance |
| Statistiques indisponibles | MongoDB démarré, `NODE_BINARY`, dépendances npm, droits de création d’index et d’agrégation |
| Styles non actualisés | Compilation Sass et ressources servies au navigateur |

En développement, les journaux sont écrits dans `var/log/dev.log`. En production, Monolog utilise la sortie d’erreur standard avec un format JSON. Les volumes Docker conservent les données après un arrêt ; leur suppression détruirait cette persistance.

## 10. Préparation du déploiement

Le dépôt fournit un environnement de développement. Pour une mise en production, prévoir un serveur PHP dont la racine HTTP est `public/`, HTTPS, un service SMTP opérationnel, PostgreSQL, Node.js et MongoDB. Configurer `APP_ENV=prod`, `APP_DEBUG=0`, les secrets et l’URL publique avant les commandes Symfony.

Séquence indicative à intégrer au processus de déploiement :

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build:css
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console messenger:setup-transports --no-interaction --env=prod
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod
```

Conserver Node.js et le module `mongodb` à l’exécution. Superviser le worker Messenger, organiser les relances de notifications et donner les droits d’écriture nécessaires à `var/` et au répertoire des images téléversées. Sauvegarder PostgreSQL et les images ; tester leur restauration avant une migration importante. MongoDB contient ici des instantanés temporaires recalculables depuis PostgreSQL.

## 11. Limites et travaux restant à réaliser

Les points suivants distinguent les écrans ou structures présents des fonctionnalités effectivement abouties :

- **Paiement bancaire :** absence de prestataire intégré, de débit et de traitement complet des événements de paiement.
- **Mot de passe oublié :** formulaire et validation présents, sans parcours complet de génération et consommation d’un lien de réinitialisation.
- **Contact :** l’interface produit un aperçu JavaScript ; la route publique inspectée ne traite pas un envoi ni un enregistrement du formulaire.
- **Informations légales :** mentions légales et confidentialité affichent des pages provisoires ; les CGV existantes portent sur le retour du matériel.
- **Allergènes :** des métadonnées historiques peuvent servir de repli lorsque la revue des plats est incomplète ; la publication n’impose pas systématiquement une revue achevée.
- **Protection contre les tentatives répétées :** aucun réglage de limitation des connexions n’est présent dans la configuration de sécurité inspectée.
- **Démonstration et déploiement :** exclure les comptes de démonstration et les éventuels fichiers de mots de passe sous `public/` des livrables de production.

Le [rapport de revue de sécurité existant](security-compliance-review.md) détaille les constats réalisés séparément, notamment sur les dépendances et les paramètres de déploiement. Ses résultats d’audit sont datés et doivent être revérifiés avant livraison.

## 12. Sources et documents complémentaires

- [Installation rapide](../README.md)
- [Structure du projet](project-structure.md)
- [Base de données et historique de ses évolutions](database.md)
- [Inscription et courriels](registration-setup.md)
- [Espace employé](employee-workspace.md)
- [Ressources de conception](../design/README.md)

Références principales du code : [Checkout](../src/Order/Checkout.php), [EmployeeWorkspace](../src/Employee/EmployeeWorkspace.php), [MenuPublisher](../src/Catalog/MenuPublisher.php), [AdminStatistics](../src/Statistics/AdminStatistics.php), [script MongoDB](../bin/admin-statistics.mjs) et [configuration de sécurité](../config/packages/security.yaml). Les notes historiques peuvent décrire un état antérieur ; le code et les migrations en vigueur restent la référence technique.
