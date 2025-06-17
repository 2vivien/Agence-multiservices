# Documentation de l'API Agence Multiservice

Cette documentation décrit les endpoints de l'API RESTful pour l'application Agence Multiservice.

## Authentification

### `POST /api/login`
- **Description**: Connecte un utilisateur (gérant ou admin).
- **Rôles**: Public
- **Paramètres (corps JSON)**:
    - `username` (string, requis): Nom d'utilisateur.
    - `password` (string, requis): Mot de passe.
- **Réponse (Succès 200)**:
  ```json
  {
    "message": "Login successful.",
    "user": {
      "id": 1,
      "username": "admin",
      "full_name": "Admin User",
      "role": "admin"
    },
    "session_id": "session_id_value"
  }
  ```
- **Réponse (Erreur 401)**: `{ "error": "Invalid username or password, or account inactive." }`

### `POST /api/logout`
- **Description**: Déconnecte l'utilisateur actuellement authentifié.
- **Rôles**: Authentifié (tout rôle)
- **Paramètres**: Aucun
- **Réponse (Succès 200)**: `{ "message": "Logout successful." }`

### `GET /api/auth/status`
- **Description**: Vérifie le statut d'authentification de l'utilisateur actuel et retourne ses informations s'il est connecté.
- **Rôles**: Public (le résultat varie si authentifié ou non)
- **Paramètres**: Aucun
- **Réponse (Succès 200 - Authentifié)**:
  ```json
  {
    "authenticated": true,
    "user": { "id": 1, "username": "admin", "full_name": "Admin User", "role": "admin" }
  }
  ```
- **Réponse (Succès 200 - Non Authentifié)**: `{ "authenticated": false }`

## Opérations (CRUD)

Les endpoints suivants sont généralement accessibles aux 'gérants' pour leurs propres opérations et aux 'admins' pour toutes les opérations (avec possibilité de filtrer par `user_id`).

### `GET /api/operations`
- **Description**: Liste les opérations avec filtres et pagination.
- **Rôles**: Gérant (voit ses opérations), Admin (voit toutes, peut filtrer)
- **Paramètres (query)**:
    - `page` (int, optionnel): Numéro de page pour la pagination.
    - `limit` (int, optionnel): Nombre d'items par page.
    - `service_id` (int, optionnel): Filtrer par ID de service.
    - `operation_type_id` (int, optionnel): Filtrer par ID de type d'opération.
    - `date_from` (string YYYY-MM-DD, optionnel): Date de début de période.
    - `date_to` (string YYYY-MM-DD, optionnel): Date de fin de période.
    - `user_id` (int, optionnel, Admin seulement): Filtrer par ID de gérant.
- **Réponse (Succès 200)**:
  ```json
  {
    "message": "Operations list retrieved successfully.",
    "filters_applied": { ... },
    "data": [ { "id": 1, "user_id": 2, "service_id": 1, ... } ],
    "pagination": { "total_records": 100, "current_page": 1, "per_page": 10, "total_pages": 10 }
  }
  ```

### `POST /api/operations`
- **Description**: Crée une nouvelle opération.
- **Rôles**: Gérant (pour soi-même), Admin (peut spécifier `user_id`)
- **Paramètres (corps JSON)**:
    - `service_id` (int, requis)
    - `operation_type_id` (int, requis)
    - `amount` (float, requis)
    - `operation_time` (string YYYY-MM-DD HH:MM:SS, optionnel, défaut: maintenant)
    - `description` (string, optionnel)
    - `commission_applied` (float, optionnel, défaut: 0)
    - `reference_id` (string, optionnel)
    - `user_id` (int, optionnel, Admin seulement pour affecter à un autre gérant)
- **Réponse (Succès 201)**: `{ "id": 123, "user_id": 2, ... }` (l'opération créée)
- **Réponse (Erreur 422)**: `{ "errors": { "field": "message" } }`

### `GET /api/operations/{id}`
- **Description**: Affiche une opération spécifique.
- **Rôles**: Gérant (sa propre opération), Admin
- **Paramètres (URL)**: `id` (int, requis)
- **Réponse (Succès 200)**: `{ "id": 123, "user_id": 2, ... }`
- **Réponse (Erreur 404)**: `{ "error": "Operation not found." }`
- **Réponse (Erreur 403)**: `{ "error": "Forbidden. You do not own this resource." }`

### `PUT /api/operations/{id}`
- **Description**: Met à jour une opération existante.
- **Rôles**: Gérant (sa propre opération, conditions limitées), Admin
- **Paramètres (URL)**: `id` (int, requis)
- **Paramètres (corps JSON)**: Champs à mettre à jour (similaires à POST).
- **Réponse (Succès 200)**: `{ "id": 123, ... }` (l'opération mise à jour)

### `DELETE /api/operations/{id}`
- **Description**: Supprime une opération (généralement désactivation/soft delete).
- **Rôles**: Gérant (sa propre opération, conditions limitées), Admin
- **Paramètres (URL)**: `id` (int, requis)
- **Réponse (Succès 204 No Content ou 200 avec l'objet désactivé)**

## Exports d'Opérations

### `GET /api/operations/export/pdf`
- **Description**: Exporte la liste filtrée des opérations en PDF.
- **Rôles**: Gérant (ses opérations), Admin (toutes ou filtrées)
- **Paramètres (query)**: Identiques à `GET /api/operations`.
- **Réponse**: Fichier PDF téléchargé.

### `GET /api/operations/export/excel`
- **Description**: Exporte la liste filtrée des opérations en Excel (.xlsx).
- **Rôles**: Gérant (ses opérations), Admin (toutes ou filtrées)
- **Paramètres (query)**: Identiques à `GET /api/operations`.
- **Réponse**: Fichier Excel téléchargé.

## Clôture de Journée

### `GET /api/cloture/today`
- **Description**: Récupère les données nécessaires pour la clôture du jour du gérant connecté.
- **Rôles**: Gérant
- **Paramètres**: Aucun
- **Réponse (Succès 200)**:
  ```json
  {
    "initial_amount_today": 150000.00,
    "unclosed_operations": [ ... ],
    "calculated_theoretical_amount": 165000.00,
    "total_commission_today_on_unclosed": 1500.00,
    "date_today": "YYYY-MM-DD"
  }
  ```

### `POST /api/cloture/submit`
- **Description**: Soumet la clôture de journée pour le gérant connecté.
- **Rôles**: Gérant
- **Paramètres (corps JSON)**:
    - `actual_final_amount` (float, requis): Solde réel final constaté.
    - `notes` (string, optionnel): Notes sur la clôture.
- **Réponse (Succès 201)**: `{ "message": "Clôture soumise avec succès.", "balance": { ...nouveau bilan... } }`
- **Réponse (Erreur 400/422)**: `{ "error": "message" }` ou `{ "errors": { ... } }`

## Résumés de Journée (Clôtures)

### `GET /api/admin/daily-summaries`
- **Description**: Liste les résumés de clôture pour l'administrateur (paginé, filtrable).
- **Rôles**: Admin
- **Paramètres (query)**: `date`, `date_from`, `date_to`, `user_id`, `page`, `limit`.
- **Réponse (Succès 200)**: Structure paginée avec liste des bilans (clôtures).

### `GET /api/gerant/daily-summaries`
- **Description**: Liste les résumés de clôture pour le gérant connecté (paginé, filtrable).
- **Rôles**: Gérant
- **Paramètres (query)**: `date`, `date_from`, `date_to`, `page`, `limit`.
- **Réponse (Succès 200)**: Structure paginée avec liste des bilans du gérant.

## Statistiques

Les endpoints de statistiques acceptent généralement les paramètres de query `date_from`, `date_to`. Si l'utilisateur est admin, `user_id` peut aussi être un filtre.

### `GET /api/stats/overall`
- **Description**: Récupère les statistiques globales (admin seulement).
- **Rôles**: Admin
- **Paramètres (query)**: `date_from`, `date_to`.
- **Réponse (Succès 200)**: `{ "data": { "total_operations": ..., "total_turnover": ..., ... } }`

### `GET /api/stats/services`
- **Description**: Statistiques par service.
- **Rôles**: Gérant (ses données), Admin (toutes ou filtrées par `user_id`)
- **Paramètres (query)**: `date_from`, `date_to`, `user_id` (admin), `service_id`.
- **Réponse (Succès 200)**: `{ "data": [ { "service_name": ..., "operation_count": ..., ... } ] }`

### `GET /api/stats/financial-summary`
- **Description**: Résumé financier (dépôts, retraits, commissions).
- **Rôles**: Gérant (ses données), Admin (toutes ou filtrées par `user_id`)
- **Paramètres (query)**: `date_from`, `date_to`, `user_id` (admin).
- **Réponse (Succès 200)**: `{ "data": { "total_deposits": ..., "total_withdrawals": ..., ... } }`

### `GET /api/stats/user-activity`
- **Description**: Activité des gérants (admin seulement).
- **Rôles**: Admin
- **Paramètres (query)**: `date_from`, `date_to`, `user_id` (optionnel pour un gérant spécifique).
- **Réponse (Succès 200)**: `{ "data": [ { "user_full_name": ..., "total_ops": ..., ... } ] }`

### Exports Statistiques (GET)
- `/api/stats/services/export/pdf`
- `/api/stats/services/export/excel`
- `/api/stats/financial-summary/export/pdf`
- `/api/stats/financial-summary/export/excel`
- `/api/stats/user-activity/export/pdf` (Admin)
- `/api/stats/user-activity/export/excel` (Admin)
- **Description**: Exportent les statistiques correspondantes.
- **Rôles**: Comme les endpoints de stats respectifs.
- **Paramètres (query)**: Identiques aux endpoints de stats respectifs.
- **Réponse**: Fichier PDF ou Excel téléchargé.

## Paramètres (Gestion Admin)

### Utilisateurs (`/api/admin/users`)
- **`GET /api/admin/users`**: Lister tous les utilisateurs.
  - **Rôles**: Admin
  - **Paramètres (query)**: `page`, `limit`, `role`, `is_active`.
- **`POST /api/admin/users`**: Créer un utilisateur.
  - **Rôles**: Admin
  - **Paramètres (corps JSON)**: `username`, `full_name`, `password`, `role`, `email` (optionnel), `is_active` (optionnel).
- **`GET /api/admin/users/{id}`**: Afficher un utilisateur.
- **`PUT /api/admin/users/{id}`**: Mettre à jour un utilisateur.
- **`DELETE /api/admin/users/{id}`**: Désactiver un utilisateur.

### Services (`/api/admin/services`)
- **`GET /api/admin/services`**: Lister tous les services (actifs et inactifs).
  - **Rôles**: Admin
  - **Paramètres (query)**: `page`, `limit`, `is_active`.
- **`POST /api/admin/services`**: Créer un service.
  - **Rôles**: Admin
  - **Paramètres (corps JSON)**: `name`, `description` (optionnel), `default_commission_rate` (optionnel), `is_active` (optionnel).
- **`GET /api/admin/services/{id}`**: Afficher un service.
- **`PUT /api/admin/services/{id}`**: Mettre à jour un service.
- **`DELETE /api/admin/services/{id}`**: Désactiver un service.

## Listes de Sélection (pour formulaires)

### `GET /api/services`
- **Description**: Liste tous les services *actifs*.
- **Rôles**: Authentifié (tout rôle)
- **Réponse (Succès 200)**: `[ { "id": 1, "name": "MTN Money", ... } ]`

### `GET /api/operation-types`
- **Description**: Liste tous les types d'opérations *actifs*.
- **Rôles**: Authentifié (tout rôle)
- **Réponse (Succès 200)**: `[ { "id": 1, "name": "Dépôt", ... } ]`

---
**Note**: Les exemples de réponses sont simplifiés. Les réponses réelles peuvent contenir plus de champs. Pour les endpoints paginés, la réponse est généralement structurée comme `{ "data": [...], "pagination": { ... } }`.
