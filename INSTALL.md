# Installation - Open Demat

Ce document décrit l'installation de l'environnement serveur, puis le démarrage
initial d'une instance **Open Demat** :

- Apache 2
- PHP 8.5 (via Sury)
- PostgreSQL 17
- Composer
- Supervisor
- Utilisateur de déploiement
- Instance Open Demat

Pour l'installation d'un stockage S3 compatible Garage, voir
`INSTALL_GARAGE.md`.

Testé sur **Debian 12 (Bookworm)**.

---

## 1. Prérequis système

```bash
sudo apt update
sudo apt install -y \
  curl wget git unzip ca-certificates lsb-release gnupg supervisor
```

---

## 2. Installation de PHP 8.5 (Sury)

### Ajout du dépôt Sury

```bash
curl -fsSL https://packages.sury.org/php/apt.gpg | sudo gpg --dearmor -o /usr/share/keyrings/php.gpg

echo "deb [signed-by=/usr/share/keyrings/php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
| sudo tee /etc/apt/sources.list.d/php.list

sudo apt update
```

### Installation PHP + extensions

```bash
sudo apt install -y \
  php8.5 php8.5-cli php8.5-fpm php8.5-common \
  php8.5-pgsql php8.5-mysql php8.5-sqlite3 \
  php8.5-xml php8.5-curl php8.5-zip php8.5-mbstring \
  php8.5-intl php8.5-gd php8.5-bcmath
```

Activation de PHP-FPM :

```bash
sudo systemctl enable php8.5-fpm
sudo systemctl start php8.5-fpm
```

---

## 3. Installation de Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 4. Installation de PostgreSQL 17

### Ajout du dépôt PostgreSQL officiel

```bash
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
| sudo gpg --dearmor -o /usr/share/keyrings/postgresql.gpg

echo "deb [signed-by=/usr/share/keyrings/postgresql.gpg] \
http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
| sudo tee /etc/apt/sources.list.d/postgresql.list

sudo apt update
```

### Installation

```bash
sudo apt install -y postgresql-17 postgresql-client-17
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

### Création de la base et de l'utilisateur

```bash
sudo -u postgres psql
```

```sql
CREATE USER open_demat WITH PASSWORD 'change_me';
CREATE DATABASE open_demat OWNER open_demat;
\q
```

---

## 5. Installation d'Apache 2

```bash
sudo apt install -y apache2
sudo systemctl enable apache2
sudo systemctl start apache2
```

### Activation PHP-FPM dans Apache

```bash
sudo a2enmod proxy_fcgi setenvif rewrite
sudo a2enconf php8.5-fpm
sudo systemctl reload apache2
```

---

## 6. Utilisateur de déploiement

### Création de l'utilisateur

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
```

### Permissions du projet

```bash
sudo mkdir -p /var/www/open-demat
sudo chown -R deploy:www-data /var/www/open-demat
sudo chmod -R 2750 /var/www/open-demat
sudo chmod -R 2770 /var/www/open-demat/var
```

Le code applicatif appartient à `deploy:www-data`. Le bit `2` sur les
répertoires conserve le groupe `www-data` pour les fichiers créés ensuite.
Le répertoire `var/` doit rester inscriptible par PHP-FPM et les commandes
Symfony exécutées sous `www-data`.

Après le clone initial, appliquer ou réappliquer les droits suivants :

```bash
sudo chown -R deploy:www-data /var/www/open-demat
sudo find /var/www/open-demat -type d -exec chmod 2750 {} \;
sudo find /var/www/open-demat -type f -exec chmod 0640 {} \;
sudo chmod -R 2770 /var/www/open-demat/var
```

### Autoriser le clear cache en www-data

Le script `update.sh` lance `cache:clear` avec l'utilisateur `www-data` pour
éviter de créer un cache Symfony appartenant à `deploy`.

Éditer sudoers avec `visudo` :

```bash
sudo visudo -f /etc/sudoers.d/open-demat-deploy
```

Ajouter la règle suivante, en adaptant le chemin de PHP si nécessaire :

```sudoers
deploy ALL=(www-data) NOPASSWD: /usr/bin/php /var/www/open-demat/bin/console cache:clear *
```

Vérifier ensuite :

```bash
sudo -u deploy sudo -n -u www-data /usr/bin/php /var/www/open-demat/bin/console cache:clear --env=prod
```

---

## 7. Démarrage initial d'une instance

### Cloner le dépôt

```bash
cd /var/www
git clone https://github.com/open-demat/open-demat-core.git open-demat
cd open-demat
```

### Préparer les fichiers locaux

```bash
cp .env.example .env.local
cp composer.open_demat.json.template composer.open_demat.json
```

Le fichier `.env.local` contient la configuration locale de l'application. Le
fichier `composer.open_demat.json` contient les packages et bundles activés pour
cette instance.

### Renseigner les variables d'environnement essentielles

Éditer `.env.local` :

```env
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=change_me_please_123456

ADMIN_URL="http://localhost:8000"
ORGANIZATION_NAME="Open Demat"

DATABASE_URL="postgresql://open_demat:change_me@127.0.0.1:5432/open_demat?serverVersion=17&charset=utf8"

MAILER_DSN="null://null"
MAILER_FROM="noreply@open-demat.example.org"
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=open_demat_async
```

En production :

- mettre `APP_ENV=prod` et `APP_DEBUG=0` ;
- générer un vrai `APP_SECRET` ;
- utiliser des mots de passe forts ;
- ne jamais versionner `.env.local`.

### Déclarer les packages et bundles locaux

Exemple de `composer.open_demat.json` :

```json
{
  "require": {
    "open-demat/admin-bundle": "dev-main"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/open-demat/admin-bundle.git"
    }
  ]
}
```

Les bundles Symfony activés localement sont déclarés dans
`config/bundles.local.php`. Exemple :

```php
<?php

return [
    OpenDemat\AdminBundle\OpenDematAdminBundle::class => ['all' => true],
];
```

### Générer Composer et installer les dépendances

```bash
bash bin/composer-build
composer install
```

Le `composer.json` généré ne doit pas être modifié à la main. Modifier
`composer.core.json` pour le socle commun, et `composer.open_demat.json` pour
les packages propres à l'instance.

### Initialiser la base

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:create
```

Si le projet ou les bundles fournissent des migrations, utiliser plutôt :

```bash
php bin/console doctrine:migrations:migrate
```

### Lancer l'instance en local

```bash
php -S 127.0.0.1:8000 -t public
```

Ouvrir ensuite l'URL configurée dans `ADMIN_URL`, par exemple
`http://localhost:8000`.

---

## 8. Installer un package

Pour installer un package Composer :

```bash
composer require vendor/package
```

Pour installer un bundle Open Demat, déclarer aussi son activation Symfony dans
`config/bundles.local.php` si le bundle ne le fait pas automatiquement.

---

## 9. Mettre à jour les dépôts

Pour récupérer les dernières versions des différents dépôts :

```bash
./update.sh
```

Le script fait un `git pull --ff-only`, régénère `composer.json` avec
`bin/composer-build`, lance `composer update`, puis vide le cache Symfony avec
`www-data`.

---

## 10. Worker mail avec Supervisor

Les emails Open Demat sont envoyés via Symfony Messenger. Les messages mail sont
déposés dans la file Doctrine définie par `MESSENGER_TRANSPORT_DSN`, puis
traités par le transport Messenger `async` :

```bash
php bin/console messenger:consume async
```

En production, installer ce worker avec Supervisor :

```bash
sudo cp docs/supervisor-open-demat-mailer.conf /etc/supervisor/conf.d/open-demat-mailer.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status open-demat-mailer:*
```

Commandes utiles :

```bash
sudo supervisorctl restart open-demat-mailer:*
sudo supervisorctl tail -f open-demat-mailer:*
```

La configuration fournie suppose :

- projet dans `/var/www/open-demat` ;
- utilisateur système `deploy` ;
- environnement `prod` ;
- logs dans `/var/log/supervisor/open-demat-mailer.log`.

Adapter `docs/supervisor-open-demat-mailer.conf` si le chemin, l'utilisateur ou
l'environnement diffère.

---

## 11. Ports utilisés

| Service    | Port     |
| ---------- | -------- |
| Apache     | 80 / 443 |
| PHP-FPM    | socket   |
| PostgreSQL | 5432     |

---

## 12. Vérifications rapides

```bash
php -v
composer --version
psql --version
systemctl status supervisor
supervisorctl status
systemctl status apache2
systemctl status php8.5-fpm
systemctl status postgresql
php bin/console lint:container
php bin/console debug:router
```
