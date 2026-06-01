# Documentation – Fonctionnement du Core Symfony Open Demat

Ce logiciel constitue **le cœur de la plateforme Symfony** destinée à la gestion des processus internes de l'Open Demat.

Il repose sur une architecture :

* **modulaire**
* **multi-bundles**
* **multi-kernels**
* avec un **Core partagé**
* et un **système Composer dynamique Core / Overlay**

---


# Open Source Usage

This repository is published under the GNU Affero General Public License v3.0 or later, with a specific exception for bundles and plugins. The public distribution is installable without private process bundles: copy `composer.open_demat.json.template` to `composer.open_demat.json`, then run `bash bin/composer-build` before `composer install`.

Deployment-specific bundles, private repositories, and environment secrets must live in `composer.open_demat.json` or CI variables outside the public repository.

## Local quick start

```bash
cp .env.example .env
cp composer.open_demat.json.template composer.open_demat.json
bash bin/composer-build
composer install
composer test
```

Pour une installation pas a pas avec base de donnees, stockage S3,
authentification CAS/Shibboleth et ajout de bundles, voir
`docs/getting-started.md`.

## Licence

Ce projet est distribué sous licence GNU Affero General Public License v3.0
ou ultérieure, avec une exception spécifique pour les bundles/plugins.

Le cœur du logiciel reste libre : si vous modifiez le cœur et que vous le
redistribuez ou le rendez accessible à des utilisateurs via un réseau, vous devez
rendre disponible le code source correspondant de cette version modifiée.

Les bundles, plugins ou modules développés via les API publiques d’extension
documentées peuvent rester privés ou être distribués sous une autre licence,
conformément à l’exception décrite dans `LICENSE-EXCEPTION.md`.

Voir :

- `LICENSE`
- `LICENSE-EXCEPTION.md`
- `NOTICE.md`

## Exception pour les bundles

Le cœur du projet est distribué sous licence AGPLv3.

Par exception, les bundles, plugins ou modules Symfony développés pour des besoins
internes peuvent rester privés ou être distribués sous une autre licence, à condition
qu’ils interagissent avec le cœur uniquement via les API publiques d’extension
documentées.

Cette exception ne couvre pas les modifications du cœur, ni le code copié depuis
le cœur vers un bundle. Toute modification du cœur reste soumise à l’AGPLv3.

## Support commercial

Un support professionnel est disponible pour :

- installation et configuration ;
- maintenance corrective et évolutive ;
- hébergement managé ;
- intégration avec LDAP, CAS, SAML, API métiers ;
- développement de bundles spécifiques ;
- formation administrateurs et utilisateurs ;
- accompagnement au déploiement.

Contact : contact@example.com

# Architecture générale

La plateforme peut être déployée :

* soit en **kernel Symfony unique** regroupant plusieurs processus,
* soit en **plusieurs kernels distincts** partageant un **Core commun versionné**.

Chaque processus est encapsulé dans un **bundle autonome**.

---

# Rôle du Core

Le Core fournit les éléments transversaux :

* authentification CAS
* gestion des utilisateurs
* gestion des rôles globaux
* hub applicatif
* configuration commune
* services partagés
* structure Doctrine commune
* sécurité transverse

Le Core **ne contient pas la logique métier des processus**.

---

# Organisation des bases de données

Chaque processus :

* possède son **schéma PostgreSQL dédié**
* déclare explicitement ses tables :

```php
#[ORM\Table(name: 'tableName', schema: 'schemaName')]
```

Chaque kernel dispose :

* d’un **utilisateur PostgreSQL dédié**
* avec des droits limités à ses propres schémas

---

# Versioning

* Chaque bundle suit **Semantic Versioning**
* Les commits suivent les **Conventional Commits**
* Les releases sont générées automatiquement via CI

---

# Système Composer dynamique Core / Overlay

## Objectif

Permettre :

* un Core commun stable
* des bundles activables dynamiquement
* une CI capable d’activer uniquement certains bundles
* un système propre sans polluer le Core avec des dépendances spécifiques

---

## Fichiers utilisés

Le projet ne repose plus sur un unique `composer.json`.

Il utilise :

```
composer.core.json      → dépendances communes
composer.open_demat.json      → bundles activés + repositories
composer.json           → fichier généré (core + overlay)
```

---

## Fonctionnement

### 1️⃣ `composer.core.json`

Contient uniquement :

* les dépendances Symfony
* les dépendances transversales
* aucune référence aux bundles métiers

---

### 2️⃣ `composer.open_demat.json`

Contient uniquement :

* les bundles activés
* leurs repositories Composer/VCS

Exemple :

```json
{
  "require": {
    "open-demat/admin-bundle": "dev-main",
    "open-demat/app01-bundle": "dev-main"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/your-org/your-bundle.git" }
  ]
}
```

---

### 3️⃣ `composer.json` (généré)

Ce fichier est généré automatiquement par :

```bash
bash bin/composer-build
```

Il correspond à :

```
composer.core.json
+ composer.open_demat.json
```

Il ne doit jamais être modifié manuellement.

---

# Synchronisation automatique des dépendances

Un mécanisme de synchronisation est en place :

* Si une dépendance est ajoutée via `composer require`
  → elle est automatiquement synchronisée dans `composer.core.json` (si non Open Demat)

* Si une dépendance est supprimée via `composer remove`
  → elle est supprimée du Core

Les bundles `open-demat/*` ne sont jamais promus dans le Core.

---

# Scripts disponibles

## Génération du composer.json

```bash
bash bin/composer-build
```

## Mise à jour complète

```bash
./update.sh
```

Ce script :

1. Génère `composer.json`
2. Lance `composer update`

---

# CI

En CI ou dans un déploiement spécifique :

* `composer.open_demat.json` est généré dynamiquement
* seul un sous-ensemble de bundles peut être activé
* `bin/composer-build` est exécuté avant install

Cela permet :

* des kernels allégés en test
* des environnements spécifiques par branche
* un contrôle fin des bundles activés

---

# Intégration d’un nouveau bundle

## Étape 1 — Ajouter dans composer.open_demat.json

```json
"require": {
  "open-demat/app02-bundle": "dev-main"
}
```

---

## Étape 2 — Activer dans bundles.php

```php
OpenDemat\App02Bundle\App02Bundle::class => ['all' => true],
```

---

## Étape 3 — Ajouter access_control

Dans `config/packages/security.yaml` :

```yaml
security:
  access_control:
    - { path: ^/app02, roles: ROLE_USER }
```

---

# Ce qui est auto-configuré par les bundles

Les bundles injectent automatiquement :

* leurs migrations Doctrine
* leurs rôles
* leur configuration
* leur entrée Hub
* leurs routes
* leurs services

---

# Ce qui reste manuel

✔ activation dans `bundles.php`
✔ ajout dans `composer.open_demat.json`
✔ définition des `access_control`

---

# Avantages de l’architecture

* Core minimal et stable
* Bundles totalement autonomes
* Activation dynamique des processus
* CI flexible
* Isolation PostgreSQL par schéma
* Multi-kernel possible
* Pas de duplication de configuration

---

# Conclusion

Cette architecture permet :

* une séparation claire Core / Processus
* une évolutivité forte
* une gestion centralisée des dépendances
* une CI maîtrisée
* un système modulaire robuste adapté aux besoins institutionnels de l’Open Demat
