# Installation – Garage S3 Storage (Debian)

Ce document décrit l’installation et la configuration de **Garage S3** sur Debian.

## 1. Création de l’utilisateur système

```bash
sudo useradd -r -s /usr/sbin/nologin garage
```

---

## 2. Création des répertoires

```bash
sudo mkdir -p /var/lib/garage/meta
sudo mkdir -p /var/lib/garage/data
sudo mkdir -p /etc/garage
```

Permissions :

```bash
sudo chown -R garage:garage /var/lib/garage
sudo chown -R garage:garage /etc/garage
```

---

## 3. Installation du binaire

```bash
cd /tmp

wget https://garagehq.deuxfleurs.fr/_releases/v2.2.0/x86_64-unknown-linux-musl/garage

chmod +x garage

sudo mv garage /usr/local/bin/garage
```

Vérification :

```bash
garage --version
```

---

## 4. Configuration Garage

Créer le fichier :

```bash
sudo nano /etc/garage/garage.toml
```

Configuration minimale :

```toml
metadata_dir = "/var/lib/garage/meta"
data_dir = "/var/lib/garage/data"
db_engine = "sqlite"

replication_factor = 1

rpc_bind_addr = "[::]:3901"
rpc_public_addr = "127.0.0.1:3901"
rpc_secret = "CHANGE_ME"

[s3_api]
api_bind_addr = "[::]:3900"
s3_region = "garage"

[admin]
api_bind_addr = "127.0.0.1:3903"
admin_token = "CHANGE_ME"
```

Générer les secrets :

```bash
openssl rand -hex 32
openssl rand -base64 32
```

---

## 5. Service systemd

Créer :

```bash
sudo nano /etc/systemd/system/garage.service
```

Contenu :

```ini
[Unit]
Description=Garage S3 Storage
After=network-online.target
Wants=network-online.target

[Service]
User=garage
Group=garage
Environment=GARAGE_CONFIG_FILE=/etc/garage/garage.toml
ExecStart=/usr/local/bin/garage server
Restart=always
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
```

Activation :

```bash
sudo systemctl daemon-reload
sudo systemctl enable garage
sudo systemctl start garage
```

Vérification :

```bash
sudo systemctl status garage
```

---

## 6. Initialisation du cluster

Vérifier le node ID :

```bash
sudo -u garage garage -c /etc/garage/garage.toml status
```

Assigner le stockage :

```bash
sudo -u garage garage -c /etc/garage/garage.toml layout assign -z dc1 -c 50G NODE_ID
```

Appliquer le layout :

```bash
sudo -u garage garage -c /etc/garage/garage.toml layout apply --version 1
```

---

## 7. Création du bucket

```bash
sudo -u garage garage -c /etc/garage/garage.toml bucket create process-documents
```

---

## 8. Création d’une clé S3

```bash
sudo -u garage garage -c /etc/garage/garage.toml key create process-app-key
```

Donner les permissions :

```bash
sudo -u garage garage -c /etc/garage/garage.toml bucket allow \
  --read \
  --write \
  --owner \
  process-documents \
  --key process-app-key
```

Afficher les informations :

```bash
sudo -u garage garage -c /etc/garage/garage.toml key info process-app-key
```

Les valeurs importantes :

```
Key ID       → S3_ACCESS_KEY
Secret key   → S3_SECRET_KEY
```

---

# Migration MinIO → Garage

## 1. Configurer les alias `mc`

Garage :

```bash
mc alias set garage http://127.0.0.1:3900 ACCESS_KEY SECRET_KEY --path on
```

MinIO :

```bash
mc alias set minio http://127.0.0.1:9000 MINIO_ACCESS_KEY MINIO_SECRET_KEY
```

Vérification :

```bash
mc ls minio
mc ls garage
```

---

## 2. Copier le bucket

Exemple :

```
MinIO bucket : documents
Garage bucket : process-documents
```

Migration :

```bash
mc mirror --overwrite minio/documents garage/process-documents
```

---

## 3. Vérifier la migration

Lister les fichiers :

```bash
mc ls garage/process-documents
```

Comparer les tailles :

```bash
mc du minio/documents
mc du garage/process-documents
```

---

# Variables d’environnement Symfony

```env
S3_ENDPOINT=http://127.0.0.1:3900
S3_REGION=garage
S3_BUCKET=process-documents

S3_ACCESS_KEY=ACCESS_KEY
S3_SECRET_KEY=SECRET_KEY
```

---

# Ports utilisés

| Service          | Port |
| ---------------- | ---- |
| Garage S3 API    | 3900 |
| Garage RPC       | 3901 |
| Garage Admin API | 3903 |

---

# Vérifications

```bash
garage --version
systemctl status garage
```

Tester la connexion S3 :

```bash
mc ls garage
```

