# GeoGamerz 🎮

GeoGamerz est une application web de type quiz gaming : les joueurs doivent deviner un jeu vidéo affiché à l'écran (nom, année de sortie, éditeur, protagoniste) et grimpent dans un classement global. L'interface est "dark-mode first", inspirée des tableaux de bord e-sport.

## Sommaire

- [Instructions pour lancer le projet](#instructions-pour-lancer-le-projet)
- [Documentation](#documentation)
- [Commentaires](#commentaires)

---

## Instructions pour lancer le projet

### Prérequis

- PHP 8.2 ou supérieur
- Composer
- Symfony CLI (recommandé)
- Docker (pour lancer PostgreSQL via `compose.yaml`) — ou une instance PostgreSQL existante

### Étapes d'installation

1. **Cloner le dépôt et installer les dépendances PHP**
   ```bash
   git clone https://github.com/getill/geogamerz.git
   cd geogamerz
   composer install
   ```

2. **Configurer l'environnement**
   Créez un `.env.local` pour surcharger les variables sensibles (il n'est jamais versionné) :
   ```bash
   cp .env .env.local
   ```
   Variables à vérifier/renseigner dans `.env.local` :
   - `DATABASE_URL` — connexion PostgreSQL (par défaut `postgresql://app:symfony@127.0.0.1:5454/app?serverVersion=16&charset=utf8`, cohérente avec `compose.yaml`).
   - `RAWG_API_KEY` — clé d'API [RAWG](https://rawg.io/apidocs) (gratuite), nécessaire uniquement pour importer des jeux depuis l'admin ou la commande `app:import-games`. Le jeu fonctionne sans, tant que la base contient déjà des jeux.
   - `APP_SECRET` — générez une valeur aléatoire (ex. `openssl rand -hex 16`).

3. **Démarrer la base de données**
   ```bash
   docker compose up -d
   ```

4. **Créer le schéma et jouer les migrations**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Compiler les assets CSS (Tailwind)**
   Le projet utilise Symfony AssetMapper — aucun `npm install` n'est nécessaire.
   ```bash
   php bin/console tailwind:build
   # ou en continu pendant le développement :
   php bin/console tailwind:build --watch
   ```

6. **Lancer le serveur**
   ```bash
   symfony server:start -d
   # ou : php -S 127.0.0.1:8000 -t public/
   ```
   L'application est accessible sur [http://127.0.0.1:8000](http://127.0.0.1:8000).

7. **Peupler la base avec des jeux** (optionnel mais nécessaire pour jouer)
   Deux options, toutes deux basées sur l'API RAWG (nécessite `RAWG_API_KEY`) :
   - Depuis l'admin : `Administration` → `Jeu` → `Importer depuis RAWG` (formulaire avec mot-clé, tri, dates, pagination).
   - En ligne de commande :
     ```bash
     php bin/console app:import-games --page-size=30
     ```

8. **Créer un compte et obtenir les droits admin**
   Créez un compte via `/register`, puis passez-le en administrateur directement en base (aucune commande de promotion n'existe pour l'instant) :
   ```bash
   php bin/console dbal:run-sql "UPDATE \"user\" SET roles = '[\"ROLE_ADMIN\"]' WHERE username = 'votre_pseudo'"
   ```
   L'espace d'administration (EasyAdmin) est ensuite accessible sur `/admin` et protégé par `ROLE_ADMIN`.

---

## Documentation

### Objectifs du projet

- Proposer un mini-jeu de reconnaissance de jeux vidéo (image + champs à deviner) avec un niveau de difficulté et un nombre de manches configurables.
- Tenir un classement global des meilleurs scores, consultable sans y jouer.
- Permettre à un administrateur d'alimenter la base de jeux à deviner sans intervention manuelle (import automatisé depuis l'API RAWG).
- Mettre en œuvre les briques attendues d'une application Symfony complète : authentification, persistance relationnelle via Doctrine, rendu Twig, services dédiés à la logique métier, ressources HTTP multiples (pages HTML + endpoints JSON), assets JS/CSS.

### Spécifications textuelles

**Visiteur (non authentifié)**
- Consulte l'accueil : classement des meilleurs scores, aperçu des jeux disponibles.
- Peut créer un compte (`/register`) ou se connecter (`/login`).

**Joueur (`ROLE_USER`)**
- Accède à `/game`, choisit un niveau de difficulté (1 à 3 — plus il est élevé, plus il faut de champs corrects pour valider une manche) et un nombre de manches (5, 10 ou 15).
- Une partie tire aléatoirement N jeux en base (`GET /api/games?limit=N`, mélange côté serveur) et enchaîne les manches : image du jeu, saisie des champs demandés (nom, année, éditeur, protagoniste selon la difficulté), validation avec réponse acceptée en correspondance partielle (insensible à la casse et aux accents), révélation de la bonne réponse en cas d'erreur.
- À la fin de la partie, le score cumulé est envoyé et persisté (`POST /api/score`), puis apparaît dans le classement.

**Administrateur (`ROLE_ADMIN`)**
- Accède à `/admin` (EasyAdmin) : gestion CRUD des utilisateurs, des scores et des jeux.
- Peut importer des jeux depuis RAWG via un formulaire dédié (mot-clé, tri, plage de dates, pagination) ou via la commande `app:import-games`, avec dédoublonnage par nom.

### Dictionnaire de données

**`game`**

| Attribut       | Type              | Contraintes         | Description                                  |
|----------------|-------------------|----------------------|-----------------------------------------------|
| `id`           | integer            | PK, auto-increment   | Identifiant unique                            |
| `name`         | varchar(255)       | NOT NULL             | Nom du jeu vidéo                              |
| `publisher`    | varchar(255)       | NOT NULL             | Éditeur du jeu                                |
| `image_url`    | text               | NOT NULL             | URL de la jaquette (fournie par RAWG)         |
| `release_year` | integer            | nullable             | Année de sortie                               |
| `protagonist`  | varchar(255)       | nullable             | Nom du protagoniste principal (saisie manuelle, absent de RAWG) |

**`user`**

| Attribut   | Type          | Contraintes                  | Description                              |
|------------|---------------|-------------------------------|-------------------------------------------|
| `id`       | integer        | PK, auto-increment            | Identifiant unique                        |
| `username` | varchar(180)   | NOT NULL, UNIQUE               | Identifiant de connexion                  |
| `roles`    | json           | NOT NULL                      | Rôles Symfony (`ROLE_USER`, `ROLE_ADMIN`) |
| `password` | varchar        | NOT NULL                      | Mot de passe haché (auto)                 |
| `email`    | varchar(255)   | nullable                      | Adresse e-mail                            |

**`score`**

| Attribut    | Type     | Contraintes                          | Description                          |
|-------------|----------|----------------------------------------|----------------------------------------|
| `id`        | integer   | PK, auto-increment                     | Identifiant unique                     |
| `points`    | integer   | NOT NULL                               | Score obtenu sur une partie            |
| `player_id` | integer   | FK → `user.id`, nullable               | Joueur ayant réalisé le score          |

> `game` n'est pas relié à `score` : une manche n'est pas persistée individuellement, seul le score cumulé de la partie est enregistré.

### Modèle Conceptuel de Données (MCD)

```mermaid
erDiagram
    USER ||--o{ SCORE : "réalise"
    USER {
        int id PK
        string username
        json roles
        string password
        string email
    }
    SCORE {
        int id PK
        int points
        int player_id FK
    }
    GAME {
        int id PK
        string name
        string publisher
        string image_url
        int release_year
        string protagonist
    }
```

`GAME` est indépendant du reste du modèle : c'est le référentiel des jeux pouvant être proposés en manche, interrogé mais jamais lié en base à une partie ou un score précis.

### Diagramme — déroulé d'une partie

```mermaid
flowchart TD
    A[Écran de configuration<br/>niveau 1-3 + nombre de manches 5/10/15] --> B[Séquence de connexion<br/>GET /api/games?limit=N]
    B --> C[Manche i : affichage de l'image<br/>+ champs à deviner selon le niveau]
    C --> D{Validation de la manche}
    D -->|Champ correct| E[✓ + point]
    D -->|Champ incorrect| F[✕ + révélation de la bonne réponse]
    E --> G{Dernière manche ?}
    F --> G
    G -->|Non| C
    G -->|Oui| H[Écran de fin<br/>POST /api/score]
    H --> I[Score visible dans le classement /]
```

### Architecture & stack technique

- **Backend** : PHP 8.2+ / Symfony 8
- **Frontend** : Twig, Tailwind CSS v4, Symfony UX (Twig Components façon Shadcn UI), Stimulus (contrôleurs JS dans `assets/controllers/`), Turbo Drive
- **Base de données** : PostgreSQL, persistée via les entités Doctrine (`src/Entity/`)
- **Authentification** : `symfony/security-bundle`, session + CSRF, `ROLE_USER` / `ROLE_ADMIN` (`config/packages/security.yaml`)
- **Administration** : EasyAdmin (`src/Controller/Admin/`)
- **Logique métier en service dédié** : `src/Service/RawgGameImporter.php` (appel API RAWG, dédoublonnage, mapping vers l'entité `Game`)
- **Gestionnaire d'assets** : Symfony AssetMapper (pas de build Node.js/Webpack)

### Ressources HTTP principales

| Méthode | URL                          | Description                                  |
|---------|-------------------------------|-----------------------------------------------|
| GET     | `/`                            | Accueil + classement                          |
| GET     | `/game`                        | Écran de jeu (configuration puis partie)      |
| GET     | `/api/games?limit=N`           | Liste aléatoire de N jeux (JSON)              |
| POST    | `/api/score`                   | Enregistre le score d'une partie              |
| GET/POST| `/register`, `/login`, `/logout` | Authentification                            |
| GET/POST| `/admin/game/import-rawg`      | Formulaire d'import de jeux depuis RAWG       |
| —       | `/admin/*`                     | CRUD EasyAdmin (users, scores, jeux)          |

---

## Commentaires

*Section libre — à compléter avec l'avancement, les difficultés rencontrées et les retours sur le module.*

- **État d'avancement** :
- **Ce qui a bien fonctionné** :
- **Difficultés rencontrées** :
- **Pistes d'amélioration** :
- **Retours sur le module** :
