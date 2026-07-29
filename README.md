# GeoGamerz 🎮

GeoGamerz est une application web orientée gaming, conçue pour offrir une expérience utilisateur premium. Elle dispose d'une interface hyper moderne, épurée et "dark-mode first", avec des accents visuels rouges vibrants inspirés des tableaux de bord e-sport professionnels.

## 🚀 Fonctionnalités

- **Interface "Dark-Mode First"** : Un design maîtrisé, élégant, utilisant des effets de glassmorphism et des micro-animations pour une expérience utilisateur fluide.
- **Composants Shadcn UI** : Intégration native des composants Shadcn via Symfony UX (`<twig:Card>`, `<twig:Button>`, `<twig:Input>`, etc.) pour une consistance parfaite.
- **Classement Global** : Un leaderboard dynamique mettant en avant les meilleurs joueurs.
- **Jeu Interactif "Game Guesser"** : Défiez vos connaissances du jeu vidéo dans un mini-jeu intégré.
- **Authentification** : Système complet de création de compte et de connexion, entièrement rhabillé avec le nouveau design system.

## 🛠️ Stack Technique

- **Backend** : PHP 8.2+ / Symfony 7
- **Frontend** : Twig, Tailwind CSS v4, Symfony UX
- **Design System** : Shadcn UI (Symfony UX Twig Components)
- **Gestionnaire d'Assets** : Symfony AssetMapper (sans Webpack ni Node.js requis)
- **Base de données** : PostgreSQL / MySQL / SQLite (configurable via Doctrine)

## 📦 Installation & Déploiement

### Prérequis

- PHP 8.2 ou supérieur
- Composer
- Symfony CLI (recommandé)

### Étapes d'installation

1. **Cloner le dépôt**
   ```bash
   git clone https://github.com/votre-utilisateur/geogamerz.git
   cd geogamerz
   ```

2. **Installer les dépendances PHP**
   ```bash
   composer install
   ```

3. **Configurer l'environnement**
   Copiez le fichier `.env` en `.env.local` et configurez votre connexion à la base de données (`DATABASE_URL`).
   ```bash
   cp .env .env.local
   ```

4. **Préparer la base de données**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Compiler les assets CSS (Tailwind)**
   Le projet utilise Tailwind CSS via AssetMapper. Il est nécessaire de compiler les styles.
   ```bash
   php bin/console tailwind:build
   ```
   *Pour le développement continu : `php bin/console tailwind:build --watch`*

6. **Lancer le serveur de développement**
   ```bash
   symfony server:start -d
   ```
   L'application sera accessible sur [http://127.0.0.1:8000](http://127.0.0.1:8000).

## 🎨 Design System

Le thème principal du projet est géré dans `assets/styles/app.css`. 
Les composants d'interface (Button, Card, Input, Label, Table, Badge, Avatar) se trouvent dans le dossier `templates/components/` et peuvent être appelés directement dans Twig sous la forme `<twig:ComponentName>`.

---
*Développé avec passion pour l'élite des joueurs.*
