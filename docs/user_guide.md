# Guide Utilisateur - Agence Multiservice

Bienvenue dans le guide utilisateur de l'application Agence Multiservice. Ce guide vous aidera à comprendre comment utiliser les différentes fonctionnalités de l'application, que vous soyez Gérant ou Administrateur.

## Accès à l'Application

Pour accéder à l'application, ouvrez votre navigateur web et rendez-vous à l'adresse fournie par votre administrateur système. Vous serez accueilli par un écran de connexion.

## Pour les Gérants

Cette section décrit les fonctionnalités disponibles pour les utilisateurs avec le rôle "Gérant".

### 1. Connexion / Déconnexion
- **Connexion**: Entrez votre nom d'utilisateur et mot de passe fournis.
- **Déconnexion**: Cliquez sur le bouton "Déconnexion" (généralement dans la barre de navigation ou le menu utilisateur).

### 2. Tableau de Bord Gérant (`gerant-dashboard.html`)
- **Vue d'ensemble**: Affiche un résumé de vos informations financières clés pour la journée en cours (solde actuel par service, statistiques rapides).
- **Actions rapides**: Peut contenir des raccourcis vers les opérations les plus courantes.
- **Alertes**: Notifications sur des actions requises (ex: clôture en attente).

### 3. Gestion des Opérations (`operation.html`)
- **Enregistrement**: Permet d'ajouter de nouvelles opérations (dépôts, retraits, transferts, paiements de factures, etc.) au fur et à mesure qu'elles sont effectuées.
- **Liste des opérations**: Affiche les opérations que vous avez enregistrées, avec la possibilité de les modifier ou supprimer (selon les règles établies, ex: avant clôture).
- **Exports**: Possibilité d'exporter la liste de vos opérations en PDF ou Excel.

### 4. Clôture de Journée (`cloture.html`)
- **Processus crucial**: Permet de finaliser votre journée comptable.
- **Affichage des données**: Montre le solde initial calculé, la liste des opérations non encore clôturées pour la journée, et le solde théorique attendu.
- **Saisie du solde réel**: Vous devez compter votre caisse physique et entrer le montant total.
- **Calcul de l'écart**: L'application affichera la différence entre le solde théorique et votre solde réel.
- **Soumission**: Enregistre la clôture, lie les opérations du jour à cette clôture, et prépare le solde initial pour le lendemain.

### 5. Mes Résumés de Journée (`resume_jour.html`)
- **Historique**: Permet de consulter l'historique de vos clôtures de journée passées.
- **Détails**: Affiche les informations de chaque clôture (date, soldes, écart, notes).
- **Filtres**: Possibilité de filtrer par date ou période pour retrouver des résumés spécifiques.

### 6. Statistiques (Gérant) (`statistique.html`)
- **Performance**: Visualisation de vos performances et tendances.
- **Données disponibles**:
    - Statistiques par service (nombre d'opérations, montants totaux, commissions pour vos transactions).
    - Résumé financier sur une période (total dépôts, retraits, commissions pour vos transactions).
- **Filtres**: Possibilité de filtrer par période de dates et par service.
- **Exports**: Exporter certaines statistiques en PDF/Excel.

## Pour les Administrateurs

Cette section décrit les fonctionnalités disponibles pour les utilisateurs avec le rôle "Administrateur".

### 1. Connexion / Déconnexion
- Similaire aux gérants.

### 2. Tableau de Bord Administrateur (`admin-dashboard.html`)
- **Vue d'ensemble globale**: Affiche des indicateurs clés sur l'ensemble de l'agence (nombre total de transactions, chiffre d'affaires global, nombre de gérants actifs, alertes système, etc.).
- **Accès rapide aux sections de gestion**.

### 3. Gestion des Utilisateurs (`parametre.html` - Section Utilisateurs)
- **Liste des utilisateurs**: Voir tous les utilisateurs (gérants et admins), leur statut, rôle.
- **Création**: Ajouter de nouveaux utilisateurs (gérants ou admins), définir leur nom d'utilisateur, mot de passe initial, rôle.
- **Modification**: Changer les informations d'un utilisateur (nom, email, rôle, statut actif/inactif).
- **Désactivation/Suppression**: Désactiver des comptes utilisateurs.

### 4. Gestion des Services (`parametre.html` - Section Services)
- **Liste des services**: Voir tous les services offerts par l'agence, leur statut (actif/inactif).
- **Création**: Ajouter de nouveaux services (ex: un nouvel opérateur de transfert, un nouveau type de paiement de facture).
- **Modification**: Changer le nom, la description, le taux de commission par défaut, le statut d'un service.
- **Désactivation/Suppression**: Désactiver des services.

### 5. Suivi des Clôtures (`resume_jour_admin.html`)
- **Vue centralisée**: Consulter les résumés de clôture de journée de *tous* les gérants.
- **Filtres**: Filtrer par date, période, ou par gérant spécifique pour analyser les performances ou identifier des problèmes.
- **Détails**: Voir les mêmes informations que le gérant pour chaque clôture (soldes, écarts, notes).

### 6. Statistiques Avancées (`statistique.html`)
- **Vue agrégée**: Accès à toutes les statistiques, avec la possibilité de voir les données pour l'ensemble de l'agence ou filtrées par gérant.
    - **Statistiques Globales**: Chiffres clés sur l'ensemble de l'activité.
    - **Statistiques par Service**: Performance de chaque service (global ou par gérant).
    - **Résumé Financier**: Flux financiers globaux ou par gérant.
    - **Activité des Utilisateurs**: Suivi du nombre d'opérations et de la dernière activité de chaque gérant.
- **Exports**: Capacité d'exporter toutes ces données en PDF et Excel.

### 7. Configuration Générale (Conceptuel - Non implémenté dans cette phase)
- (Futur) Paramètres de l'application, configuration des types d'opérations avancés, etc.

---
Pour toute question non couverte par ce guide, veuillez contacter le support technique ou votre administrateur système.
