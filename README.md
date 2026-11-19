# Vite et Gourmand

Application de traiteur développée avec Symfony 7.4 et Twig.

## Design et maquettes

Les ressources de conception sont regroupées dans le dossier **[design/](design/README.md)** :

- [Maquettes des écrans](design/maquettes/)
- [Charte graphique](design/charte-graphique.pdf)
- [Images de référence](design/images-references.pdf)
- [Lien vers le projet Figma](design/figma-link.txt)

## Prérequis

- PHP 8.4.1 ou supérieur, avec `pdo_pgsql`, `ctype`, `iconv`, `dom`, `xml` et `xmlwriter`.
- Composer 2, Node.js 20.19.0 ou supérieur et npm.
- Docker avec Docker Compose, démarré.
- Symfony CLI.

Cloner ou extraire le projet, puis exécuter les commandes à sa racine.

## 1. Configurer l’environnement

Créer ou adapter `.env.dev.local` :

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=remplacer_par_une_valeur_aleatoire
DEFAULT_URI=http://127.0.0.1:8000
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN="doctrine://default?auto_setup=0"
MAILER_DSN="smtp://127.0.0.1:1025"
MAIL_FROM_ADDRESS="contact@vite-et-gourmand.test"
MONGODB_URI="mongodb://127.0.0.1:27017"
MONGODB_DATABASE=vite_gourmand_statistics
NODE_BINARY=node
```

Générer une valeur et la copier dans `APP_SECRET` :

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

## 2. Installer les dépendances

```sh
composer install
composer check-platform-reqs
npm ci
npm run build:css
```

## 3. Démarrer les services

```sh
docker compose up -d database mailer
docker compose ps
```

Attendre que PostgreSQL soit `healthy`. Pour les statistiques administrateur, créer aussi le conteneur MongoDB lors du premier lancement :

```sh
docker run -d --name vite-gourmand-mongodb -p 127.0.0.1:27017:27017 -v vite_gourmand_mongodb_data:/data/db mongo:8.0
```

Aux lancements suivants, utiliser `docker start vite-gourmand-mongodb`.

## 4. Initialiser la base

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports --no-interaction
```

Les migrations chargent les données du projet. Conserver le dossier `database/` et ne pas importer les fichiers SQL séparément.

## 5. Lancer l’application

```sh
symfony server:start --no-tls --port=8000
```

Ouvrir [http://127.0.0.1:8000](http://127.0.0.1:8000).

Dans un second terminal, lancer le traitement des e-mails :

```sh
php bin/console messenger:consume async -vv
```

Consulter les e-mails locaux dans [Mailpit](http://127.0.0.1:8025).

## Comptes de démonstration (facultatif)

Cette commande crée ou réinitialise les trois comptes suivants. À utiliser uniquement en local.

```sh
php bin/console app:accounts:easy-access
```

| Profil | Identifiant | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@gmail.com` | `AdminAcount12345!` |
| Employé | `employe@gmail.com` | `EmployeAcount12345!` |
| Client | `client@gmail.com` | `ClientAcount12345!` |

Se connecter sur [/connexion](http://127.0.0.1:8000/connexion) ou créer un compte sur [/inscription](http://127.0.0.1:8000/inscription).

## Arrêter l’application

Appuyer sur `Ctrl+C` dans les terminaux du serveur et du worker, puis exécuter :

```sh
docker compose stop
docker stop vite-gourmand-mongodb
```

Les données sont conservées. Pour redémarrer, reprendre les étapes 3 et 5.

Plus de détails : [base de données](docs/database.md) et [inscription et e-mails](docs/registration-setup.md).
