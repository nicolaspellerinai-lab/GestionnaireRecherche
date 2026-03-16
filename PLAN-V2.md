# Plan de Développement - Gestionnaire de Recherche v2

## Objectif
Améliorer le Gestionnaire de Recherche pour mieux gérer et afficher les projets de veille.

---

## 1. Affichage Markdown (Rendu HTML)

### Problème
Les fichiers `.md` sont affichés en texte brut, illisible.

### Solution
- Intégrer une librairie PHP Markdown (ex: `parsedown` ou `cebe/markdown`)
- Créer une fonction `renderMarkdown($content)` 
- Utiliser dans:
  - `projet.php` - afficher definition.md
  - `source.php` - afficher contenu des sources
  - Ajouter page "Voir résumé" avec rendu Markdown

### Fichiers à modifier
- `projet.php`
- `source.php`
- `config.php` (ajouter lib)

### Effort: **2-3 heures**

---

## 2. Édition des Informations du Projet

### Problème
Les infos (definition.md, description) sont statiques.

### Solution
Créer une page d'édition:
- `edit_projet.php` - formulaire pour modifier:
  - Nom du projet
  - Description courte
  - Definition.md (éditeur textarea)
  - Tags/Catégories

### Fonctionnalités
- Bouton "Modifier" sur la page projet
- Formulaire avec:
  - Titre
  - Description (textarea)
  - Definition.md (textarea avec preview Markdown)
  - Tags (input séparés par virgules)
- Sauvegarde directe dans les fichiers

### Fichiers à créer
- `edit_projet.php`

### Fichiers à modifier
- `projet.php` (ajouter bouton Modifier)

### Effort: **3-4 heures**

---

## 3. Ajout de Sources (Interface + API)

### 3.1 Interface Web

#### Page `add_source.php`
Formulaire pour ajouter une source:
- URL (obligatoire)
- Titre (optionnel, sinon récupérer automatiquement)
- ID personnalisé (optionnel)

#### Processus
1. Vérifier si URL déjà existante
2. Récupérer le contenu via curl
3. Sauvegarder dans `sources/XX.md`
4. Mettre à jour `sources.md`

### 3.2 API REST

#### Endpoints

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/sources?projet=XXX` | Liste des sources |
| GET | `/api/source?projet=XXX&id=1` | Détail source |
| POST | `/api/source` | Ajouter source |
| PUT | `/api/source` | Modifier source |
| DELETE | `/api/source?projet=XXX&id=1` | Supprimer source |

#### Format POST/PUT
```json
{
  "projet": "01-risques-couts-limites-ia",
  "url": "https://example.com/article",
  "titre": "Titre optionnel"
}
```

#### Configuration API
- Clé API dans `config.php`
- Authentification par header: `X-API-Key: your-key`

### Fichiers à créer
- `api.php` - routeur API
- `add_source.php` - formulaire ajout
- `api.md` - documentation

### Fichiers à modifier
- `config.php` (API key)
- `.htaccess` (routes API)

### Effort: **5-6 heures**

---

## 4. Bonus (Optionnel)

- [ ] Recherche full-text dans les sources
- [ ] Export PDF d'un projet
- [ ] Statistiques d'utilisation
- [ ] Tags et filtres avancés

---

## Ordre de Priorité

1. **Markdown Rendering** - UX immédiate
2. **Édition Projet** - Utilité quotidienne
3. **API + Ajout Sources** - Automatisation

---

## Tech Stack

- PHP 5.6+ (compatibilité existante)
- Librairie Markdown: `cebe/markdown` ou Parsedown
- Pas de framework (garder simple)
- jQuery pour AJAX (déjà inclus)

---

## Détails Techniques

### Librairie Markdown Recommandée
```php
// config.php
require_once 'vendor/autoload.php';
use cebe\markdown\Markdown;
$parser = new Markdown();
$html = $parser->parse($markdownContent);
```

### Structure API
```
recherche/
├── api.php          # Routeur principal
├── add_source.php   # Interface ajout
├── edit_projet.php  # Édition projet
├── config.php       # + API_KEY
├── vendor/          # Librairies (gitignore)
└── .htaccess       # Rewrite rules pour /api/
```

---

## Estimation Totale

| Fonctionnalité | Heures |
|----------------|--------|
| Markdown | 2-3 |
| Édition Projet | 3-4 |
| API + Ajout | 5-6 |
| **Total** | **10-13** |

---

## Risques

1. **CORS** - Configurer correctement pour API
2. **Rate Limiting** - Limiter les requêtes API
3. **Validation URL** - Vérifier format et accès
4. **Backup** - Sauvegarder avant modifications

---

*Document généré le 16 Mars 2026*
*Pour: Nicolas Pellerin*
