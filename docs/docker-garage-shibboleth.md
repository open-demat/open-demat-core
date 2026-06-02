# Docker Compose : Garage S3 et Shibboleth

Cette stack sert à tester Open Demat avec PostgreSQL, Garage S3 et un frontal
Shibboleth local.

## Garage S3

```bash
docker compose up -d db garage
docker compose --profile setup run --rm --build garage-init
```

Reporte ensuite dans `.env` les clés affichées par `garage-init` :

```env
DATABASE_URL="postgresql://postgres:password@127.0.0.1:5433/process_db_test?charset=utf8"
MINIO_ENDPOINT="http://127.0.0.1:3910"
MINIO_REGION="garage"
MINIO_BUCKET="documents"
MINIO_USE_PATH_STYLE=1
MINIO_ACCESS_KEY="KEY_ID_A_COPIER"
MINIO_SECRET_KEY="SECRET_KEY_A_COPIER"
```

Les ports hôte de Garage sont décalés pour éviter les conflits avec un Garage
ou un MinIO déjà installé sur la machine :

- API S3 : `http://127.0.0.1:3910`
- RPC : `3911`
- Web S3 : `3912`
- API admin : `3913`

Web UI optionnelle :

```bash
docker compose --profile tools up -d garage-webui
```

Elle est exposée sur `http://localhost:3909`.

## Shibboleth de développement

Le proxy de développement injecte directement les headers attendus par
`OpenDemat\Core\Security\ShibbolethAuthenticator`.

Lance Symfony sur le port `8001`.

```bash
php -S 0.0.0.0:8001 -t public
```

Avec le proxy Shibboleth dev en `network_mode: host`, un serveur lié à
`127.0.0.1:8001` fonctionne aussi :

```bash
symfony server:start --port=8001
```

Configure `.env` :

```env
SHIBBOLETH_ENABLED=1
SHIBBOLETH_IDENTIFIER_ATTRIBUTE="HTTP_EPPN"
SHIBBOLETH_EMAIL_ATTRIBUTE="HTTP_MAIL"
SHIBBOLETH_FIRST_NAME_ATTRIBUTE="HTTP_GIVENNAME"
SHIBBOLETH_LAST_NAME_ATTRIBUTE="HTTP_SN"
SHIBBOLETH_DEFAULT_EMAIL_DOMAIN="example.org"
SHIBBOLETH_AUTO_CREATE_USER=1
SHIBBOLETH_LOGIN_URL="http://localhost:8081/accueil"
```

Puis démarre le proxy :

```bash
docker compose --profile shibboleth-dev up -d shibboleth-dev-proxy
```

Si `http://localhost:8081/accueil` affiche `Service Unavailable`, vérifie que
Symfony répond bien sur la machine distante :

```bash
curl -I http://127.0.0.1:8001/accueil
```

Puis recrée le proxy :

```bash
sudo docker compose --profile shibboleth-dev up -d --force-recreate shibboleth-dev-proxy
```

L'application est exposée sur `http://localhost:8081` avec les headers :

- `EPPN: jane.doe@example.org`
- `MAIL: jane.doe@example.org`
- `GIVENNAME: Jane`
- `SN: Doe`

## Shibboleth SP réel

Le service `shibboleth-sp` embarque Apache et `mod_shib` :

```bash
docker compose --profile shibboleth up -d --build shibboleth-sp
```

Avant un vrai login fédéré, remplace
`docker/shibboleth-sp/idp-metadata.xml` par les métadonnées de ton IdP, puis
adapte l'`entityID` IdP dans `docker/shibboleth-sp/shibboleth2.xml`.

Les métadonnées du SP sont publiées sur :

```text
http://localhost:8080/Shibboleth.sso/Metadata
```
