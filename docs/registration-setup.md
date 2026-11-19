# Inscription et e-mail de bienvenue

Après la configuration de la base et les migrations, initialiser les transports
Messenger avant d’accepter des inscriptions :

```sh
php bin/console messenger:setup-transports --no-interaction
```

Cette commande Symfony crée la table `messenger_messages` utilisée par les files
`async` et `failed`. Elle peut être relancée sur une installation existante.
Ne pas compter sur la création automatique lors de la première inscription :
l’écriture du compte et celle du mail sont effectuées dans une même transaction.

Pour envoyer les messages, configurer `MAILER_DSN` et `MAIL_FROM_ADDRESS`, puis
faire tourner le worker (à superviser sur un serveur de production) :

```sh
php bin/console messenger:consume async --time-limit=3600
```

Contrôle d’intégration sans envoi de mail réel :

```sh
php tests/registration_smoke.php
```

Le test vérifie notamment l’inscription avec le transport réel. Les comptes et
messages de test sont annulés en fin de transaction et ne sont jamais visibles
par le worker.
