# Portage BPM UPEC Core vers Open Demat — 7 octobre 2026

Source comparée : BPM UPEC Core `7506b32` (1.21.5), branche `dev-maxime`, ainsi que son arbre de travail. Destination : Open Demat `7bbecf4`, branche `dev`. Les modifications locales de portage déjà présentes ont été conservées et complétées.

## Évolutions intégrées

- Messagerie interne : copie HTML des notifications, pagination, compteur de messages non lus, lecture et marquage avec CSRF. Chaque requête vérifie le propriétaire ; le HTML des e-mails est isolé par une iframe et une politique CSP restrictive.
- Sous-profils par application et mode silencieux des notifications. `MailerService::sendBundleNotification()` respecte les préférences tout en conservant la notification interne. `sendTemplated()` et `sendToTarget()` continuent à envoyer les messages transactionnels.
- Widget de discussion : affichage compact et envoi autonome dans un formulaire parent, champ CSRF configurable et rafraîchissement du fil.
- Stockage S3/Garage, maintenance, référence Doctrine de pièce jointe, création et suppression de documents, lecture des documents statiques et personnels. Le proxy générique accepte uniquement les documents UUID déposés par l’utilisateur ou consultés par un administrateur et force le téléchargement ; les accès métier doivent utiliser des contrôleurs dédiés.
- Synchronisation du Hub sans doublons, tag automatique des définitions d’application, invalidation des sessions après changement de droits, affichage de la version.
- Sélecteurs de fichiers et de documents personnels, outils de formulaire et autocomplétion d’adresse. Les valeurs insérées par les outils JavaScript sont échappées.
- Bibliothèques Bootstrap, Boxicons, DataTables, JSZip, pdfmake et jQuery servies localement ; support Markdown Twig/CommonMark.
- En-têtes de sécurité globaux préservant les politiques plus strictes des réponses de messagerie ; mises à jour ciblées des dépendances signalées par Composer.
- Déploiement : reconstruction Composer possible sans `composer.json`, migrations automatiques puis installation des assets des bundles.

## Adaptations génériques

Les namespaces, routes du profil et tags utilisent Open Demat. Les paramètres S3, le thème et l’identité de l’organisation restent configurables. Le lien « Besoin d’aide » utilise `PLATFORM_HELP_URL`, facultatif, plutôt qu’une route du bundle Ticket UPEC.

Les authentificateurs LDAP/SAML/CAS et administrateur local d’Open Demat sont conservés. Les comptes étudiants/invités UPEC, l’annuaire spécifique à l’établissement, les entités Bonita, les migrations des bundles métiers et leur contrôle d’accès ne sont pas importés. La convention UPEC de préfixe ou hôte des routes par bundle n’est pas imposée aux routes explicites existantes d’Open Demat. La modification locale du mailer UPEC redirigeant tous les messages vers une adresse personnelle n’est pas reprise.

## Déploiement et usage

`update.sh` applique automatiquement les migrations `20260706121000`, `20261001170000` et `20261001180000`, en plus des migrations déjà présentes, et installe les assets des bundles. Le portage n’applique aucune migration aux bases existantes pendant le développement.

```php
$mailer->sendBundleNotification('MON_APPLICATION', $user, 'Dossier mis à jour', 'emails/suivi.html.twig', ['dossier' => $dossier]);
```

Le nom du bundle doit correspondre à la clé de sa définition dans le Hub. Les préférences s’enregistrent dans `/profile` ; les copies sont consultables dans `/messages`. Les templates peuvent utiliser `markdown_to_html`. Les formulaires peuvent utiliser `window.OpenDematFormTools` pour préremplir leurs champs et sélectionner les documents personnels.

## Validation

- Suite PHPUnit complète : 41 tests, 143 assertions, PostgreSQL 15 temporaire isolé.
- Conteneur Symfony, templates Twig, YAML et mapping Doctrine validés ; syntaxe PHP, Bash et JavaScript contrôlée.
- Générateur Composer vérifié avec et sans fichier de sortie initial.
- Audit Composer après mises à jour : aucune vulnérabilité signalée.
- Trois migrations validées en montée, descente puis nouvelle montée sur PostgreSQL temporaire.
