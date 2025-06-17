# Documentation de la Base de Données

Ce document décrit la structure des tables principales de la base de données de l'application Agence Multiservice. La base de données utilisée est PostgreSQL.

## Table: `users`

- **Rôle**: Stocke les informations des utilisateurs de l'application (gérants et administrateurs).
- **Colonnes importantes**:
    - `id` (SERIAL PRIMARY KEY): Identifiant unique de l'utilisateur.
    - `username` (VARCHAR(255) UNIQUE NOT NULL): Nom d'utilisateur pour la connexion.
    - `password_hash` (VARCHAR(255) NOT NULL): Hash du mot de passe de l'utilisateur.
    - `full_name` (VARCHAR(255) NOT NULL): Nom complet de l'utilisateur.
    - `email` (VARCHAR(255) UNIQUE NULL): Adresse e-mail de l'utilisateur (unique si fournie).
    - `role` (VARCHAR(50) NOT NULL DEFAULT 'gerant'): Rôle de l'utilisateur (ex: 'gerant', 'admin').
    - `is_active` (BOOLEAN DEFAULT TRUE): Indique si le compte utilisateur est actif ou désactivé (pour la suppression douce).
    - `created_at`, `updated_at` (TIMESTAMP): Timestamps de création et de dernière modification.

## Table: `services`

- **Rôle**: Liste les services financiers ou autres offerts par l'agence.
- **Colonnes importantes**:
    - `id` (SERIAL PRIMARY KEY): Identifiant unique du service.
    - `name` (VARCHAR(255) UNIQUE NOT NULL): Nom du service (ex: 'MTN Money', 'Paiement Facture SBEE').
    - `description` (TEXT NULL): Description détaillée du service.
    - `is_active` (BOOLEAN DEFAULT TRUE): Indique si le service est actuellement offert.
    - `default_commission_rate` (DECIMAL(5,2) DEFAULT 0.00): Taux de commission par défaut pour ce service, si applicable globalement. Peut être surchargé ailleurs.
    - `created_at`, `updated_at` (TIMESTAMP).

## Table: `operation_types`

- **Rôle**: Définit les différents types d'opérations qu'un gérant peut enregistrer. Cette table est cruciale pour la logique métier et les calculs de solde.
- **Colonnes importantes**:
    - `id` (SERIAL PRIMARY KEY): Identifiant unique du type d'opération.
    - `name` (VARCHAR(255) UNIQUE NOT NULL): Nom du type d'opération (ex: 'Dépôt Client', 'Retrait Client', 'Paiement Facture ORANGE', 'Commission Transfert X').
    - `description` (TEXT NULL): Description.
    - `is_active` (BOOLEAN DEFAULT TRUE): Si ce type d'opération est utilisable.
    - `balance_effect` (VARCHAR(10) NOT NULL DEFAULT 'neutral'): Indique l'impact de l'opération sur le solde de caisse du gérant. Valeurs possibles:
        - `'positive'`: Augmente le solde de caisse (ex: dépôt client, paiement de facture par client au gérant).
        - `'negative'`: Diminue le solde de caisse (ex: retrait client, envoi d'un transfert par le gérant).
        - `'neutral'`: N'affecte pas directement le solde de caisse principal (ex: une opération purement informative, ou une commission qui est déjà comptabilisée via le champ `commission_applied` d'une opération principale).
    - `category` (VARCHAR(50) NULL): Catégorie fonctionnelle de l'opération (ex: 'cash_movement', 'service_sale', 'service_payment', 'commission_earned', 'internal_transfer', 'operational_expense'). Utile pour les statistiques et rapports.
    - `is_commission` (BOOLEAN DEFAULT FALSE NOT NULL): Indique si ce type d'opération représente *en soi* une commission. Si TRUE, le champ `amount` de l'opération est le montant de la commission. Si FALSE, la commission est généralement dans le champ `commission_applied` de l'opération principale.
    - `created_at`, `updated_at` (TIMESTAMP).

## Table: `operations`

- **Rôle**: Enregistre chaque transaction individuelle effectuée par un gérant. C'est une table centrale pour le suivi financier.
- **Colonnes importantes**:
    - `id` (SERIAL PRIMARY KEY): Identifiant unique de l'opération.
    - `user_id` (INTEGER NOT NULL): Référence à `users.id` (le gérant qui a effectué l'opération).
    - `service_id` (INTEGER NOT NULL): Référence à `services.id` (le service concerné).
    - `operation_type_id` (INTEGER NOT NULL): Référence à `operation_types.id`.
    - `balance_id` (INTEGER NULL): Référence à `balances.id` (la clôture de journée à laquelle cette opération est rattachée). `NULL` si l'opération n'est pas encore clôturée.
    - `amount` (DECIMAL(15,2) NOT NULL): Le montant principal de l'opération. Son interprétation (débit/crédit pour le gérant) dépend de `operation_types.balance_effect`.
    - `commission_applied` (DECIMAL(10,2) NOT NULL DEFAULT 0): La commission gagnée par le gérant sur cette opération spécifique (si applicable, et si ce n'est pas une opération de type `is_commission=TRUE`).
    - `operation_time` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP): Date et heure exactes de l'opération.
    - `description` (TEXT NULL): Notes ou description libre sur l'opération.
    - `reference_id` (VARCHAR(255) NULL): Référence externe pour la transaction (ex: ID de transaction de l'opérateur).
    - `created_at`, `updated_at` (TIMESTAMP).

## Table: `balances`

- **Rôle**: Stocke les informations de clôture de caisse journalière pour chaque gérant (et potentiellement par service, bien que la logique de clôture actuelle se concentre sur un solde global journalier par gérant).
- **Colonnes importantes**:
    - `id` (SERIAL PRIMARY KEY): Identifiant unique de l'enregistrement de solde.
    - `user_id` (INTEGER NOT NULL): Référence à `users.id` (le gérant concerné).
    - `service_id` (INTEGER NULL): Référence à `services.id`. `NULL` si c'est un enregistrement de solde global journalier pour le gérant. Utilisé si l'on souhaite aussi stocker des soldes par service.
    - `balance_date` (DATE NOT NULL): Date à laquelle ce solde s'applique.
    - `initial_amount` (DECIMAL(15,2) NOT NULL): Solde de départ pour la journée (généralement le `actual_final_amount` de la veille).
    - `calculated_final_amount` (DECIMAL(15,2) NULL): Solde théorique final calculé (`initial_amount` +/- somme des `amount` des opérations du jour selon leur `balance_effect`).
    - `actual_final_amount` (DECIMAL(15,2) NULL): Solde réel final constaté et entré par le gérant.
    - `difference_amount` (DECIMAL(15,2) NULL): Écart calculé (`actual_final_amount - calculated_final_amount`).
    - `total_commission_calculated` (DECIMAL(15,2) NULL): Somme des `commission_applied` de toutes les opérations incluses dans cette clôture.
    - `notes` (TEXT NULL): Notes du gérant sur la clôture (ex: explication d'un écart).
    - `is_closed` (BOOLEAN DEFAULT FALSE): Indique si cette période de solde est clôturée. (Redondant si `closed_at` est utilisé de manière fiable).
    - `closed_at` (TIMESTAMP NULL): Date et heure exactes de la soumission de la clôture.
    - `created_at`, `updated_at` (TIMESTAMP).

## Relations Clés (Exemples)

- Une `operation` appartient à un `user` (gérant).
- Une `operation` est d'un certain `operation_type`.
- Une `operation` concerne un `service`.
- Une `operation` peut être liée à une `balance` (clôture).
- Une `balance` appartient à un `user`.
- Une `balance` peut être (optionnellement) pour un `service` spécifique ou globale si `service_id` est `NULL`.

D'autres tables comme `alerts`, `reports`, `audit_logs` existent pour des fonctionnalités de support et de suivi.
