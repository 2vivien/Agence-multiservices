# Gestion Multiservice - Application de Gestion d'Agence

## Description du Projet

Cette application web est conçue pour la gestion des opérations quotidiennes d'une agence multiservice. Elle permet aux gérants d'enregistrer leurs transactions (dépôts, retraits, transferts, paiements de factures, etc.), de suivre leurs soldes de caisse, et de clôturer leur journée comptable. Une interface d'administration permet de gérer les utilisateurs (gérants), les services offerts, de visualiser les résumés de clôture de tous les gérants, et d'accéder à des statistiques globales.

**Architecture Principale:**
L'application suit une architecture de type Modèle-Vue-Contrôleur (MVC) simplifiée, sans l'utilisation d'un framework PHP majeur.
- **Backend**: PHP natif. Les requêtes sont dirigées par un point d'entrée unique (`public/index.php` - non créé dans ce projet, mais c'est le concept) qui utilise un routeur simple (`routes/api.php`) pour appeler les méthodes des contrôleurs. Les contrôleurs interagissent avec les modèles pour la logique métier et l'accès à la base de données. La base de données est PostgreSQL.
- **Frontend**: HTML, CSS (Tailwind CSS), et JavaScript natif. Les pages interagissent avec le backend via des appels API (fetch).
- **API**: Une API RESTful est exposée pour toutes les interactions de données entre le frontend et le backend.

**Technologies Utilisées:**
- **Backend**: PHP 8.x, PostgreSQL
- **Frontend**: HTML5, Tailwind CSS v3, JavaScript (ES6+)
- **Bibliothèques (simulées pour export)**: DomPDF (pour PDF), PhpSpreadsheet (pour Excel)
- **Tests (conceptuels)**: PHPUnit pour le backend, Jest/Vitest pour le frontend.

## Installation

**Prérequis:**
- Serveur web (Apache, Nginx) avec support PHP (8.0 ou supérieur recommandé)
- PostgreSQL (version 12 ou supérieure recommandée)
- Composer (pour la gestion des dépendances PHP, notamment pour les bibliothèques d'export)
- Extension PDO PHP pour PostgreSQL (`pdo_pgsql`)

**Configuration de la Base de Données:**
1.  Créez une base de données PostgreSQL (ex: `agence_multiservice_db`).
2.  Copiez `config/database.php.example` vers `config/database.php` (si un example est fourni, sinon créez le fichier).
3.  Modifiez `config/database.php` avec vos identifiants de connexion PostgreSQL (hôte, port, nom de la base, utilisateur, mot de passe).
    ```php
    <?php
    // config/database.php
    return [
        'driver' => 'pgsql',
        'host' => 'localhost', // ou votre hôte DB
        'port' => '5432',
        'database' => 'agence_multiservice_db',
        'username' => 'votre_utilisateur_pg',
        'password' => 'votre_mot_de_passe_pg',
        'charset' => 'utf8',
        // ... autres options ...
    ];
    ```
4.  Appliquez le schéma de la base de données en exécutant le contenu de `database/schema.sql` sur votre base de données.
    ```bash
    psql -U votre_utilisateur_pg -d agence_multiservice_db -a -f database/schema.sql
    ```
5.  Populez les données initiales (types d'opérations, services, utilisateur admin) en exécutant `database/seeds.sql`.
    ```bash
    psql -U votre_utilisateur_pg -d agence_multiservice_db -a -f database/seeds.sql
    ```

**Dépendances Backend:**
Si des bibliothèques comme DomPDF ou PhpSpreadsheet sont utilisées :
```bash
composer install
```

**Lancement du Serveur (Exemple avec le serveur de développement PHP):**
Pour un développement simple, vous pouvez utiliser le serveur web intégré de PHP. Naviguez jusqu'au répertoire `public/` (qui devrait être le root de votre serveur web) et lancez :
```bash
php -S localhost:8000
```
(Note: `public/index.php` comme point d'entrée unique n'a pas été explicitement créé et configuré dans le cadre de ce projet. La structure actuelle sert les fichiers HTML directement depuis la racine ou `public/`.)
Pour une configuration de production, configurez votre serveur web (Apache/Nginx) pour pointer vers le répertoire `public` comme racine du document, avec des réécritures d'URL vers `index.php` pour gérer le routage.

## Structure des Répertoires Principaux

-   `app/`: Contient la logique PHP principale de l'application.
    -   `Controllers/`: Gère les requêtes HTTP, la logique métier et les réponses.
    -   `Models/`: Représente les données et l'interaction avec la base de données.
-   `config/`: Fichiers de configuration (ex: `database.php`).
-   `database/`: Contient les fichiers de schéma SQL (`schema.sql`) et de seeding (`seeds.sql`).
-   `docs/`: Documentation du projet.
-   `frontend_tests/`: (Conceptuel) Tests JavaScript avec Jest/Vitest.
-   `public/`: Racine web. Contient les fichiers HTML, CSS, et JavaScript côté client.
    -   `js/`: Scripts JavaScript pour les pages frontend.
-   `routes/`: Définition des routes de l'API (`api.php`).
-   `tests/`: Tests PHPUnit.
    -   `Unit/`: Tests unitaires pour les classes PHP.
    *   `Feature/`: Tests d'intégration/fonctionnels.
-   `vendor/`: Dépendances Composer (si utilisées).

## Tests

**Tests Backend (PHPUnit - Conceptuel):**
1.  Assurez-vous que PHPUnit est installé (via Composer `dev-dependencies`).
2.  Configurez votre base de données de test dans `phpunit.xml` ou via des variables d'environnement.
3.  Lancez les tests depuis la racine du projet :
    ```bash
    ./vendor/bin/phpunit
    ```
    (Voir `phpunit.xml.dist` et `tests/bootstrap.php` pour la configuration.)

**Tests Frontend (Jest/Vitest - Conceptuel):**
1.  Assurez-vous que Node.js et npm/yarn sont installés.
2.  Installez les dépendances de développement : `npm install` ou `yarn install`.
3.  Lancez les tests :
    ```bash
    npm test
    ```
    (ou la commande configurée dans `package.json` pour Jest/Vitest).

## Documentation Supplémentaire

-   [Documentation de l'API](./docs/api.md) (À créer)
-   [Description de la Base de Données](./docs/database.md) (À créer)
-   [Guide Utilisateur](./docs/user_guide.md) (Ébauche à créer)
-   [Détails d'Architecture](./docs/architecture.md) (Optionnel, à créer si besoin)
-   [Stratégie de Test](./docs/testing.md) (Optionnel, à créer si besoin)
