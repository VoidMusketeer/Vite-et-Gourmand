# Organisation des branches Git

- `main` : version stable du projet.
- `develop` : branche de développement et d’intégration.
- `codex/nom-fonctionnalite` : branche dédiée à une fonctionnalité, toujours créée depuis `develop`.

Chaque fonctionnalité est testée sur sa branche avant fusion vers `develop`.

## Démarrer une fonctionnalité

Avec un répertoire de travail propre :

```sh
git switch develop
git switch -c codex/nom-fonctionnalite
```

Développer la fonctionnalité et enregistrer uniquement les fichiers concernés dans les commits.

## Tester et intégrer

Intégrer les dernières modifications de `develop` dans la branche de fonctionnalité :

```sh
git merge develop
php vendor/phpunit/phpunit/phpunit
```

Résoudre les éventuels conflits et vérifier manuellement les parcours concernés. Si les tests échouent, corriger puis les relancer avant toute fusion.

Lorsque les tests réussissent et que les modifications sont enregistrées :

```sh
git switch develop
git merge --no-ff codex/nom-fonctionnalite
```

Relancer les tests sur `develop` après intégration. La fusion de `develop` vers `main` intervient pour une version stable validée.

En collaboration, publier les branches sur le dépôt distant et ouvrir une pull request dont la cible est `develop`. Actualiser les branches locales depuis le distant avant de commencer les travaux et avant l’intégration.
