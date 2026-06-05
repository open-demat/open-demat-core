# Bien démarrer avec Open Demat

Ce guide décrit une installation complète d'Open Demat, depuis les composants
serveur jusqu'à l'ajout d'un bundle métier.

Pour les commandes détaillées d'installation serveur, voir aussi :

- `INSTALL.md` pour Apache, PHP, PostgreSQL, Composer et MinIO.
- `INSTALL_GARAGE.md` pour un service S3 avec Garage.
- `docs/core-services.md` pour les services fournis par le Core.

## 1. Installer les composants logiciels de base

Open Demat est une application Symfony modulaire. Une installation standard a
besoin des composants suivants :

- Linux Debian 12 ou équivalent.
- PHP 8.4 ou plus récent, avec les extensions usuelles Symfony :
  `ctype`, `iconv`, `pgsql`, `xml`, `curl`, `zip`, `mbstring`, `intl`, `gd`.
- Composer.
- `git`, `unzip`, `curl`, `jq`.
- PostgreSQL pour la base applicative.
- Un service compatible S3 pour les pieces jointes et documents.
- Un serveur web, par exemple Apache avec PHP-FPM, ou Nginx avec PHP-FPM.

Exemple minimal Debian :

```bash
sudo apt update
sudo apt install -y git curl unzip jq ca-certificates
```

Installe ensuite PHP, Composer, PostgreSQL et le serveur web en suivant
`INSTALL.md`.

## 2. Recuperer le projet

```bash
git clone https://github.com/open-demat/open-demat-core.git
cd open-demat-core
```

Prepare les fichiers locaux :

```bash
cp .env.example .env
cp composer.open_demat.json.template composer.open_demat.json
```

Le fichier `.env` contient la configuration locale de l'application. Le fichier
`composer.open_demat.json` contient les bundles métiers activés pour cette
installation.

## 3. Comprendre et creer la base de donnees

Open Demat utilise Doctrine ORM. Le Core fournit les tables communes :

- `user` pour les comptes et les roles applicatifs.
- `task` pour les taches transverses.
- `application` pour le hub applicatif.
- `messenger_messages` pour les messages asynchrones Symfony, selon la
  configuration Messenger.
- `doctrine_migration_versions` pour l'historique des migrations.

Chaque bundle métier peut ajouter ses propres entités et ses propres tables. Le
principe recommande est de separer clairement les tables métier, par exemple via
un schema PostgreSQL dedie au bundle lorsque le besoin existe.

Cree un utilisateur et une base PostgreSQL :

```bash
sudo -u postgres psql
```

```sql
CREATE USER open_demat WITH PASSWORD 'change_me';
CREATE DATABASE open_demat OWNER open_demat;
\q
```

Configure ensuite `DATABASE_URL` dans `.env` :

```env
DATABASE_URL="postgresql://open_demat:change_me@127.0.0.1:5432/open_demat?serverVersion=17&charset=utf8"
```

Après installation des dépendances, initialise le schéma :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:create
```

En production, préfère les migrations Doctrine lorsque le projet et les bundles
en fournissent :

```bash
php bin/console doctrine:migrations:migrate
```

## 4. Installer un service S3

Open Demat stocke les documents via une API compatible S3. Deux options courantes
sont documentées :

- MinIO, decrit dans `INSTALL.md`.
- Garage, decrit dans `INSTALL_GARAGE.md`.

Pour un démarrage local avec MinIO, crée un bucket, par exemple `documents`, puis
renseigne `.env` :

```env
MINIO_ENDPOINT="http://localhost:9000"
MINIO_REGION="us-east-1"
MINIO_BUCKET="documents"
MINIO_USE_PATH_STYLE=1
MINIO_ACCESS_KEY="change_me"
MINIO_SECRET_KEY="change_me"
```

Avec Garage, les noms historiques peuvent rester `MINIO_*` dans Open Demat : ils
représentent la configuration S3 utilisée par l'application.

## 5. Configurer l'authentification

Open Demat peut s'appuyer sur CAS ou sur Shibboleth. Les deux modes peuvent
coexister : si Shibboleth est activé et fournit l'identifiant attendu, il est
utilisé ; sinon l'application conserve le flux CAS.

### Option CAS

Configure les variables CAS dans `.env` :

```env
CAS_BASE_URL="https://cas.example.org"
CAS_LOGOUT_URL="https://cas.example.org/logout"
CAS_HOST="cas.example.org"
CAS_PORT="443"
CAS_PATH=""
CAS_LOGIN_TARGET="https://open-demat.example.org"
CAS_GATEWAY=0
```

`CAS_LOGIN_TARGET` doit pointer vers l'URL publique de l'application. Le Core
expose notamment `/cas/login`, `/cas/force`, `/logout` et `/llogout`.

### Option Shibboleth

Le serveur web ou le module Shibboleth doit protéger l'application et transmettre
les attributs dans les variables serveur. Exemple :

```env
SHIBBOLETH_ENABLED=1
SHIBBOLETH_IDENTIFIER_ATTRIBUTE="REMOTE_USER"
SHIBBOLETH_EMAIL_ATTRIBUTE="HTTP_MAIL"
SHIBBOLETH_FIRST_NAME_ATTRIBUTE="HTTP_GIVENNAME"
SHIBBOLETH_LAST_NAME_ATTRIBUTE="HTTP_SN"
SHIBBOLETH_DEFAULT_EMAIL_DOMAIN="example.org"
SHIBBOLETH_AUTO_CREATE_USER=1
```

Adapte les noms d'attributs aux headers réellement fournis par ton
infrastructure. Par exemple, certaines installations utilisent `HTTP_EPPN` comme
identifiant au lieu de `REMOTE_USER`.

## 6. Compléter le fichier `.env`

Les variables importantes sont :

```env
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=change_me_please_123456

ADMIN_URL="http://localhost:8000"
ORGANIZATION_NAME="Open Demat"
ORGANIZATION_LOGO=""

DATABASE_URL="postgresql://open_demat:change_me@127.0.0.1:5432/open_demat?serverVersion=17&charset=utf8"

MAILER_DSN="smtp://localhost:1025"
MAILER_FROM="noreply@open-demat.example.org"
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=open_demat_async
```

En production :

- Mets `APP_ENV=prod` et `APP_DEBUG=0`.
- Génère un vrai `APP_SECRET`.
- Utilise des mots de passe forts.
- Ne versionne jamais `.env.local` ni les secrets.

## 7. Installer les dépendances

Open Demat utilise un système Composer en deux couches :

- `composer.core.json` contient les dépendances communes du Core.
- `composer.open_demat.json` contient les bundles activés localement.
- `composer.json` est généré à partir des deux fichiers précédents.

Génère le `composer.json`, puis installe :

```bash
bash bin/composer-build
composer install
```

Le fichier `composer.json` généré ne doit pas être modifié à la main. Toute
dépendance commune doit être ajoutée dans le Core via Composer, et tout bundle
métier doit être déclaré dans `composer.open_demat.json`.

## 8. Ajouter un bundle métier

Un bundle Open Demat est un package Composer de type `open-demat-bundle`.

Dans le `composer.json` du bundle :

```json
{
  "name": "open-demat/mon-bundle",
  "type": "open-demat-bundle",
  "autoload": {
    "psr-4": {
      "OpenDemat\\MonBundle\\": "src/"
    }
  }
}
```

Dans l'application Open Demat, ajoute le bundle dans `composer.open_demat.json` :

```json
{
  "require": {
    "open-demat/admin-bundle": "dev-main",
    "open-demat/mon-bundle": "dev-main"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/open-demat/mon-bundle.git"
    }
  ]
}
```

Puis lance :

```bash
./update.sh
```

Le script `update.sh` fait quatre choses :

1. récupère les derniers changements Git avec `git pull --ff-only` ;
2. régénère `composer.json` avec `bin/composer-build` ;
3. lance `composer update` ;
4. vide le cache Symfony avec l'utilisateur `www-data`.

Si tu ne veux pas faire de `git pull`, lance seulement :

```bash
bash bin/composer-build
composer update open-demat/mon-bundle
```

Les bundles installés avec le type `open-demat-bundle` sont placés dans
`app_open_demat/{nom-du-bundle}/` grâce à la configuration Composer du Core.

## 9. Initialiser ou mettre à jour la base après ajout de bundle

Quand un bundle ajoute des entités Doctrine, il faut mettre à jour le schéma.
En développement :

```bash
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force
```

En production, utilise les migrations :

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Vérifie aussi les routes et services du bundle :

```bash
php bin/console debug:router
php bin/console lint:container
```

## 10. Démarrer localement

Avec le serveur Symfony ou le serveur PHP intégré :

```bash
symfony server:start
```

ou :

```bash
php -S 127.0.0.1:8000 -t public
```

Ouvre ensuite l'URL configuree dans `ADMIN_URL`, par exemple
`http://localhost:8000`.

## 11. Checklist de vérification

```bash
php -v
composer validate
bash bin/composer-build
composer install
php bin/console lint:container
php bin/console debug:router
php bin/console doctrine:schema:validate
```

Pour les tests automatises :

```bash
composer test
```
