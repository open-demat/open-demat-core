
# Installation – Process Symfony Stack

Ce document décrit l’installation complète de l’environnement serveur pour le projet **Process Symfony** :

- Apache 2
- PHP 8.5 (via Sury)
- PostgreSQL 17
- Composer
- MinIO
- Utilisateur de déploiement

Testé sur **Debian 12 (Bookworm)**.

---

## 1. Prérequis système

```bash
sudo apt update
sudo apt install -y \
  curl wget git unzip ca-certificates lsb-release gnupg
````

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

### Création de la base et de l’utilisateur

```bash
sudo -u postgres psql
```

```sql
CREATE USER core_user WITH PASSWORD 'password';
CREATE DATABASE process_db OWNER core_user;
\q
```

---

## 5. Installation d’Apache 2

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

### Création de l’utilisateur

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
```

### Permissions du projet

```bash
sudo chown -R deploy:www-data /var/www/open-demat-core
sudo chmod -R 2750 /var/www/open-demat-core
```

---

## 7. Installation de MinIO

### Création de l’utilisateur système

```bash
sudo useradd -r -s /sbin/nologin minio
```

### Arborescence

```bash
sudo mkdir -p /srv/minio/data
sudo mkdir -p /etc/minio
sudo chown -R minio:minio /srv/minio
sudo chown -R minio:minio /etc/minio
```

### Installation du binaire

```bash
sudo curl -Lo minio https://dl.min.io/server/minio/release/linux-amd64/minio
sudo install minio /usr/local/bin/minio
```

### Configuration MinIO

```bash
sudo nano /etc/minio/minio.env
```

```env
MINIO_ROOT_USER=root
MINIO_ROOT_PASSWORD=CHANGE_ME_STRONG_PASSWORD

MINIO_REGION=us-east-1
MINIO_SERVER_URL=http://localhost:9000
```

### Service systemd

```bash
sudo nano /etc/systemd/system/minio.service
```

```ini
[Unit]
Description=MinIO
After=network-online.target
Wants=network-online.target

[Service]
User=minio
Group=minio
EnvironmentFile=/etc/minio/minio.env
ExecStart=/usr/local/bin/minio server /srv/minio/data --address :9000 --console-address :9001
Restart=always
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
```

### Activation

```bash
sudo systemctl daemon-reload
sudo systemctl enable minio
sudo systemctl start minio
```

---

## 8. Client MinIO (mc)

```bash
sudo curl -Lo mc https://dl.min.io/client/mc/release/linux-amd64/mc
sudo install mc /usr/local/bin/mc
```

### Configuration de l’alias

```bash
mc alias set local http://localhost:9000 root 'CHANGE_ME_STRONG_PASSWORD'
```

---

## 9. Ports utilisés

| Service    | Port     |
| ---------- | -------- |
| Apache     | 80 / 443 |
| PHP-FPM    | socket   |
| PostgreSQL | 5432     |
| MinIO API  | 9000     |
| MinIO UI   | 9001     |

---

## 10. Vérifications rapides

```bash
php -v
composer --version
psql --version
systemctl status apache2
systemctl status php8.5-fpm
systemctl status postgresql
systemctl status minio
```

