# OpenDemat Core — Services fournis

Le **Core Open Demat** fournit un ensemble de services transverses mutualisés, utilisables par tous les bundles métiers (CSST etc.).

Il ne contient aucune logique métier. Il expose uniquement des briques techniques et fonctionnelles communes.

---

## 1. Point d’entrée & hub applicatif

### Rôle

Le Core sert de point d’entrée unique de l’application et redirige systématiquement vers le bundle Admin, qui contient le hub fonctionnel.

### Routes

```
/        → redirection vers {ADMIN_URL}/accueil
/cas     → entrée sécurisée CAS
```

### Fonctionnement

Le contrôleur `IndexController` redirige vers l’URL d’admin configurée dans l’environnement.

```php
return $this->redirect($adminUrl . '/accueil');
```

Le Core ne possède aucune page fonctionnelle propre.

---

## 2. Authentification CAS

### Rôle

Le Core centralise l’authentification CAS pour l’ensemble des bundles.

### Routes exposées

| Route        | Description                |
| ------------ | -------------------------- |
| `/cas/login` | Redirection vers le CAS    |
| `/cas/force` | Forçage de reconnexion CAS |
| `/cas`       | Point d’entrée sécurisé    |
| `/logout`    | Logout Symfony             |
| `/llogout`   | Logout CAS                 |

### Fonctionnement

Le flux CAS est entièrement géré dans le Core.
Les bundles métiers ne gèrent jamais l’authentification.

---

## 3. Gestion des utilisateurs

### Rôle

Fournir une entité utilisateur unique et partagée par tous les bundles.

### Entité

`OpenDemat\Core\Entity\User`

### Champs principaux

* `username`
* `email`
* `roles` (JSON, rôles explicites uniquement)
* `composante[]`
* `dep_composante[]`

### Points importants

* Les rôles sont stockés de manière explicite en base
* Aucun héritage de rôles en BDD
* L’héritage éventuel est géré uniquement par Symfony à l’exécution

```php
public function getRoles(): array {
    $roles = $this->roles;
    $roles[] = 'ROLE_USER';
    return array_unique($roles);
}
```

---

## 4. Service de mailing

### Rôle

Permettre l’envoi d’emails asynchrones, templatisés et ciblés, sans logique métier dans les bundles.

### Service

`OpenDemat\Core\Mailer\Service\MailerService`

---

### Types de cibles supportées

| Cible               | Description                                      |
| ------------------- | ------------------------------------------------ |
| `User`              | Envoi à l’email du user                          |
| `ROLE_*`            | Tous les utilisateurs ayant ce rôle (non hérité) |
| `email@domaine.tld` | Envoi direct                                     |
| `id:123`            | User par identifiant (optionnel)                 |

---

### Exemples d’utilisation

#### Envoi à un utilisateur

```php
$mailer->sendToTarget(
    $user,
    'Sujet',
    'emails/notification.html.twig',
    ['foo' => 'bar']
);
```

#### Envoi à un rôle

```php
$mailer->sendToRole(
    'ROLE_CSST_SHS',
    'Nouvelle observation',
    'emails/observation.html.twig',
    $context
);
```

#### Envoi générique

```php
$mailer->sendToTarget(
    'ROLE_ADMIN',
    'Alerte',
    'emails/alert.html.twig'
);
```

---

### Architecture technique

* Envoi via Symfony Messenger
* Message : `TemplatedMailMessage`
* Handler : `SendMailMessageHandler`
* Rendu via Twig
* Résolution des rôles via requête SQL (`roles @> [...]`)

---

## 5. Service de tâches

### Rôle

Fournir un système de tâches transverse et générique, indépendant des workflows métiers.

Une tâche représente une action attendue d’un utilisateur ou d’un rôle sur un objet métier.

---

### Entité

`OpenDemat\Core\Entity\Task`

### Champs clés

| Champ         | Rôle                 |
| ------------- | -------------------- |
| `caseType`    | Type métier          |
| `caseId`      | Identifiant métier   |
| `processName` | Nom du module        |
| `taskName`    | Nom fonctionnel      |
| `summary`     | Texte affichable     |
| `actionRoute` | Route Symfony cible  |
| `user`        | Assignation directe  |
| `roleName`    | Assignation par rôle |
| `completedAt` | Statut               |

---

### Règles d’assignation

* Assignation possible à un utilisateur ou à un rôle
* Rôles explicites uniquement (non hérités)
* Une tâche non complétée est visible dans l’inbox

---

### Routes exposées

| Route                | Description        |
| -------------------- | ------------------ |
| `/tasks/inbox`       | Liste des tâches   |
| `/tasks/inbox/count` | Compteur de tâches |

---

### Exemple d’intégration dans un bundle métier

```php
$task = (new Task())
    ->setCaseType('csst')
    ->setCaseId($observation->getId())
    ->setProcessName('csst')
    ->setTaskName('moderation')
    ->setSummary('Observation à modérer')
    ->setActionRoute('csst_observation_show')
    ->setRoleName('ROLE_CSST_SHS');

$em->persist($task);
```

---

### Complétion automatique

```php
$taskRepository->completeOpenForCaseAndTaskNames(
    'csst',
    $caseId,
    ['moderation']
);
```

---

## 6. Tables communes et fondations

Le Core fournit également :

### Tables partagées

* `user`
* `task`
* `application`

### Fondations techniques

* Doctrine ORM
* Symfony Security
* Symfony Messenger
* Twig
* EntityManager commun

---

## 7. Principes de conception

* Aucune logique métier dans le Core
* Services transverses réutilisables
* Couplage minimal avec les bundles
* Point d’entrée unique et stable

---

## 8. Extensions prévues

* Gestion documentaire (S3 / MinIO)

