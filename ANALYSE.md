# Analyse: Gestionnaire de Recherche

## Objectif
Application PHP pour gérer et consulter les recherches effectuées par OpenClaw. Permet de navigator entre projets, sources, résumés et d'importer/exporter les données.

---

## Structure des Données

```
projet/
├── definition.md          # Description du projet
├── sources.md            # Liste des sources (10+ par projet)
├── sources/
│   ├── 01.md             # Contenu source 1
│   ├── 02.md             # Contenu source 2
│   └── ...
├── resumes/
│   ├── resume-01.md      # Résumé source 1
│   ├── resume-02.md      # Résumé source 2
│   └── ...
└── non-pertinentes/     # Sources flagguées comme non pertinentes
    ├── sources.md
    ├── sources/
    └── resumes/
```

---

## Fonctionnalités Requises

### 1. Liste des Projets
- Afficher tous les dossiers dans le répertoire de recherche
- Afficher: nom du projet, date de création, nombre de sources
- Actions: voir, supprimer, exporter

### 2. Détail Projet
- Afficher definition.md
- Lister toutes les sources avec titre + URL
- Lister tous les résumés
- Actions: ajouter source, modifier, flagguer non pertinente

### 3. Consultation Source
- Afficher le contenu brut de la source (depuis sources/)
- Afficher le résumé associé
- Afficher metadata (URL, date采集)

### 4. Import/Export ZIP
- **Export**: Créer un ZIP avec:
  - definition.md
  - sources.md
  - dossier sources/ (contenu brut)
  - dossier resumes/
- **Import**: Importer un ZIP et créer le dossier projet

### 5. Gestion des Sources
- **Flagguer non pertinente**: Déplacer vers dossier `non-pertinentes/`
- **Restaurer**: Remettre une source dans la liste principale
- **Supprimer**: Effacer définitivement une source

### 6. Édition
- Modifier definition.md
- Modifier n'importe quel fichier resume
- Ajouter des notes

---

## Stack Technique

| Élément | Choix |
|---------|-------|
| Backend | **PHP 5.6** |
| Base de données | **MySQL** |
| Frontend | HTML + CSS minimal (style Dashboard OpenClaw) |
| Upload/Download | ZIP natif PHP ( ZipArchive ) |
| Sécurité | Authentification par mot de passe pour upload |

---

## Sécurité - Accès Upload

### Requirement
L'upload de fichiers ZIP ne sera **autorisé que sur le serveur** (localhost ou authentifié).

### Implémentation

```php
// Config auth dans config.php
define('UPLOAD_PASSWORD', 'votre_mot_de_passe_secure');

// API upload - nécessite mot de passe
if ($_POST['password'] !== UPLOAD_PASSWORD) {
    http_response_code(401);
    die('Accès refusé');
}
```

### Routes accessibles

| Action | Auth Required |
|--------|---------------|
| Lister projets | Non |
| Voir projet | Non |
| Voir source/résumé | Non |
| **Exporter ZIP** | **Non** (téléchargement) |
| **Importer ZIP** | **Oui** (mot de passe) |
| Supprimer projet | **Oui** |
| Modifier fichier | **Oui** |

---

## Base de Données MySQL

```sql
CREATE DATABASE IF NOT EXISTS gestionnaire_recherche;
USE gestionnaire_recherche;

CREATE TABLE IF NOT EXISTS projets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id INT UNSIGNED NOT NULL,
    titre VARCHAR(500) NOT NULL,
    url TEXT NOT NULL,
    resume TEXT,
    contenu TEXT,
    pertinente TINYINT(1) DEFAULT 1,
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (projet_id) REFERENCES projets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Fichiers à Créer

```
gestionnaire_recherche/
├── index.php              # Liste des projets
├── projet.php             # Détail d'un projet
├── source.php             # Consultation source
├── api.php                # API pour actions AJAX
├── exporter.php           # Export ZIP
├── importer.php           # Import ZIP (protégé par mot de passe)
├── supprimer.php          # Suppression projet (protégé)
├── modifier.php           # Édition fichier (protégé)
├── login.php              # Formulaire authentification
├── logout.php             # Déconnexion
├── config.php             # Configuration MySQL + auth
├── style.css              # Styles
└── assets/
    └── (logos, icônes)
```

---

## Accès Base de Données (MySQL)

```php
// config.php
$db = new mysqli('localhost', 'user', 'password', 'gestionnaire_recherche');
if ($db->connect_error) {
    die('Erreur connexion: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');
```

### Informations de connexion (à configurer)

| Paramètre | Valeur |
|-----------|--------|
| Host | `localhost` |
| Database | `gestionnaire_recherche` |
| User | *(à créer)* |
| Password | *(à créer)* |
| Password upload | *(à configurer)* |
gestionnaire_recherche/
├── index.php              # Liste des projets
├── projet.php             # Détail d'un projet
├── source.php             # Consultation source
├── api.php                # API pour actions AJAX
├── exporter.php           # Export ZIP
├── importer.php           # Import ZIP
├── supprimer.php          # Suppression projet
├── config.php             # Configuration BDD
├── style.css              # Styles
└── assets/
    └── (logos, icônes)
```

---

##Fonctionnalités Bonus (V2)

- [ ] Recherche plein texte dans les résumés
- [ ] Tags/catégories pour les sources
- [ ] Statistiques (nombre de sources par projet)
- [ ] Intégration API externe pour recharger contenu
- [ ] Mode collaboratif (plusieurs utilisateurs)

---

## Migration OpenClaw ↔ Serveur Web

### Export depuis OpenClaw
1. Sélectionner projet
2. Cliquer "Exporter ZIP"
3. Télécharger fichier .zip

### Import sur Serveur Web
1. Uploader fichier .zip
2. Décompresser dans dossier projets
3. L'application détecte automatiquement le nouveau projet

### Sens Inverse
Même principe: export depuis serveur web, import dans workspace OpenClaw

---

## Budget Temps Estimé

| Tâche | Heures |
|-------|--------|
| Structure base + config MySQL | 1.5h |
| Liste projets + CRUD | 2h |
| Consultation sources/résumés | 2h |
| Import/Export ZIP + sécurité | 2.5h |
| Flagging sources + édition | 1.5h |
| Authentification upload | 1h |
| Tests et corrections | 1.5h |
| **Total** | **12h** |
