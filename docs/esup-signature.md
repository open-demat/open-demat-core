# ESUP Signature — Documentation administrateur Core

**Audience** : administrateur de la plateforme BPM Open Demat Core  
**Emplacement du code** : `src/EsupSignature/`

---

## 1. Rôle dans le Core

ESUP Signature est un **service de plateforme optionnel** du Core BPM Open Demat. Il fournit aux bundles métier (FEBRH, PDP, COURRIERS…) la boîte à outils pour interagir avec un serveur ESUP Signature sans gérer eux-mêmes la communication HTTP.

Le service est activé si et seulement si `ESUP_SIGNATURE_URL` est défini dans l'environnement.

---

## 2. Configuration

```yaml
# config/packages/esup_signature.yaml  (non existant — paramètres déclarés dans services.yaml)
```

Les paramètres sont déclarés dans `config/services.yaml` :

```yaml
parameters:
  esup_signature.url:        '%env(default::ESUP_SIGNATURE_URL)%'
  esup_signature.api_key:    '%env(default::ESUP_SIGNATURE_API_KEY)%'
  esup_signature.timeout:    30
  esup_signature.schema_dir: '%kernel.project_dir%/var/esup-schema'
```

Variables d'environnement dans `.env.local` :

```env
ESUP_SIGNATURE_URL=https://signatures.u-pec.fr
ESUP_SIGNATURE_API_KEY=<cle-api-admin>
ESUP_SIGNATURE_TIMEOUT=30          # optionnel, défaut 30s
```

Si `ESUP_SIGNATURE_URL` est absent ou vide, les services ES fonctionnent mais retournent une erreur à la première requête HTTP. Aucun bundle ne doit être impacté si aucune demande n'est émise.

---

## 3. Services disponibles

| Service | Rôle |
|---|---|
| `EsupSignatureApiClient` | Client HTTP bas niveau (GET, POST, POST multipart, DELETE) |
| `EsupSignatureServerInfo` | Version du serveur (avec fallback `/actuator/info`), adapter multi-version |
| `SignRequestService` | Demandes de signature simples (`/ws/signrequests/`) |
| `WorkflowService` | Circuits de signature multi-étapes (`/ws/workflows/`) |
| `FormService` | Formulaires ESUP (`/ws/forms/`) |
| `BpmSignalTargetUrlBuilder` | Construction du `targetUrl` HMAC pour les callbacks |
| `BpmSignalSecretRegistry` | Registre des secrets HMAC par `caseType` |
| `EsupSchemaCatalog` | Stockage et diff des schémas OpenAPI (outil dev) |

Les bundles consommateurs injectent directement ces services — aucune dépendance à un bundle séparé.

---

## 4. Gestion multi-version (adapter)

Le serveur ES est détecté automatiquement à la première requête :

1. Lecture du champ `info.version` dans `/ws/api-docs`
2. Si vide (cas de la v1.36.30+) : fallback sur `/actuator/info` → `build.version`

Selon la version détectée, `EsupSignatureServerInfo::getAdapter()` retourne l'adapter approprié :

| Version | Adapter | Différences principales |
|---|---|---|
| < 1.36.0 | `EsupApiAdapterV131` | Comportement de référence, pass-through |
| >= 1.36.0 | `EsupApiAdapterV136` | `pdfImageStamp/certSign/nexuSign` → `signature`, features ConvertToPDFA, JwtAuth… |

L'adapter normalise les paramètres avant chaque appel HTTP. Les bundles consommateurs construisent leurs DTOs sans connaître la version du serveur.

---

## 5. Module SIGNAL (webhook entrant)

ESUP Signature rappelle le BPM via le `targetUrl` à chaque changement de statut d'une demande.

**Route** : `GET|POST /esup-signature/signal` → `BpmSignalController`  
**Accès** : `PUBLIC_ACCESS` (configuré dans `config/packages/security.yaml`)

### Flux

```
Serveur ES → GET /esup-signature/signal?caseType=FEBRH&caseId=xxx&token=hmac
                │
                ├── Validation HMAC (sha256, secret par caseType)
                ├── ACK 200 immédiat
                └── Dispatch ProcessBpmSignalMessage → async Messenger
                        │
                        └── BpmSignalHandler
                                ├── Mise à jour SignRequest en base
                                └── Dispatch EsupSignatureCompletedEvent
```

### Sécurité HMAC

Chaque bundle consommateur implémente `BpmSignalProviderInterface` :

```php
class FeRHSignalProvider implements BpmSignalProviderInterface
{
    public function getCaseType(): string    { return 'FEBRH'; }
    public function getSignalSecret(): string { return $this->secret; }
}
```

Le `BpmSignalSecretRegistry` agrège tous les providers tagués `open_demat.bpm_signal_provider`.

Le `targetUrl` est construit par `BpmSignalTargetUrlBuilder::build($caseId)` :

```
/esup-signature/signal?caseType=FEBRH&caseId=xxx&token=hmac(FEBRH.xxx, secret)
```

---

## 6. Entité SignRequest

**Schéma PostgreSQL** : `esup_signature`  
**Table** : `esup_signature.sign_request`  
**Mapping Doctrine** : `OpenDematCoreEsupSignature` (dans `config/packages/doctrine.yaml`)

| Colonne | Type | Description |
|---|---|---|
| `id` | int | Clé primaire auto |
| `esup_id` | varchar(255) | ID retourné par le serveur ES |
| `esup_type` | varchar(32) | `signrequest`, `workflow` ou `form` |
| `case_type` | varchar(64) | Identifiant du bundle consommateur (ex: `FEBRH`) |
| `case_id` | varchar(255) | Identifiant du dossier dans le bundle |
| `create_by_eppn` | varchar(255) | EPPN de l'initiateur |
| `status` | varchar(32) | Enum `SignRequestStatus` |
| `server_id` | varchar(64) | Identifiant du serveur ES source (nullable) |
| `signed_file_key` | varchar(512) | Clé S3 du PDF signé (nullable) |
| `callback_payload` | json | Dernier payload callback reçu (nullable) |
| `created_at` | timestamp | Date de création |
| `updated_at` | timestamp | Date de dernière mise à jour |

### Migrations

Les migrations Core sont dans `migrations/` (namespace `DoctrineMigrations`, table de suivi `core_migration_versions`).

```bash
php bin/console doctrine:migrations:migrate --namespace="DoctrineMigrations"
```

---

## 7. Commandes utiles

Ces commandes sont fournies par `esup-signature-bundle` (ancien bundle, désormais absent). Des commandes équivalentes seront à créer dans `src/EsupSignature/Command/` si nécessaire.

En attendant, utiliser le bundle ESDEMO pour tester la connectivité :

```bash
# Vérifier la connectivité et la version du serveur ES
# (via l'interface web ESDEMO ou directement)
curl -H "X-Api-Key: <api-key>" https://signatures.u-pec.fr/ws/api-docs | jq '.info.version'
curl https://signatures.u-pec.fr/actuator/info | jq '.build.version'
```

---

## 8. Variables d'environnement de référence

```env
# Serveur ES principal (production)
ESUP_SIGNATURE_URL=https://signatures.u-pec.fr
ESUP_SIGNATURE_API_KEY=<cle-api-admin>

# Optionnel
ESUP_SIGNATURE_TIMEOUT=30
```

Pour ajouter un serveur ES de test ou multi-serveur, voir la documentation du bundle ESDEMO.
