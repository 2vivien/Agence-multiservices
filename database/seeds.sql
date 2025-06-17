-- Ensure this script is idempotent, e.g., by checking for existing data or using ON CONFLICT DO NOTHING/UPDATE.
-- For simplicity in this seed script, we'll assume a clean database or handle conflicts gracefully if possible.

-- Users (Gérants & Admin)
-- Passwords should be hashed in a real application. For seeds, we'll use placeholders.
-- It's better to have a default admin user created through a secure process.
INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES
('admin', 'hashed_password_admin', 'Admin User', 'admin', TRUE) ON CONFLICT (username) DO NOTHING,
('gerant01', 'hashed_password_gerant01', 'Gérant Un', 'gerant', TRUE) ON CONFLICT (username) DO NOTHING,
('gerant02', 'hashed_password_gerant02', 'Gérant Deux', 'gerant', TRUE) ON CONFLICT (username) DO NOTHING,
('gerant_inactive', 'hashed_password_gerant03', 'Gérant Trois', 'gerant', FALSE) ON CONFLICT (username) DO NOTHING;

-- Services
INSERT INTO services (name, description, is_active, default_commission_rate) VALUES
('MTN Money', 'MTN Mobile Money Services', TRUE, 1.0),
('MOOV Money', 'MOOV Mobile Money Services', TRUE, 1.1),
('CELTIS Cash', 'CELTIS Mobile Money Services', TRUE, 0.9),
('Western Union', 'Western Union Money Transfer', TRUE, 2.5),
('RIA Money Transfer', 'RIA Money Transfer Services', TRUE, 2.3),
('Orange Money', 'Orange Mobile Money Services', FALSE, 1.0) -- Example of an inactive service
ON CONFLICT (name) DO NOTHING;

-- Operation Types
INSERT INTO operation_types (name, description, is_active) VALUES
('Dépôt', 'Dépôt d''argent', TRUE),
('Retrait', 'Retrait d''argent', TRUE),
('Transfert National', 'Transfert d''argent national', TRUE),
('Achat de Crédit', 'Achat de crédit téléphonique', TRUE),
('Paiement de Facture', 'Paiement de facture', TRUE),
('Transfert International', 'Transfert d''argent international', TRUE)
ON CONFLICT (name) DO NOTHING;

-- Retrieve IDs for linking (this is a bit tricky in plain SQL seeds without scripting)
-- For actual seeding, you might run these SELECTs and use the results in subsequent INSERTs,
-- or use a procedural language block if your SQL environment supports it.
-- For this example, we'll assume IDs are known or will be 1, 2, 3... based on insertion order.
-- Let's assume:
-- user_id for gerant01 is 2
-- user_id for gerant02 is 3
-- service_id for MTN Money is 1
-- service_id for MOOV Money is 2
-- operation_type_id for Dépôt is 1
-- operation_type_id for Retrait is 2

-- Balances (Solde initial pour quelques jours)
-- Gérant Un (user_id 2), MTN Money (service_id 1)
INSERT INTO balances (user_id, service_id, balance_date, initial_balance, final_balance, total_commission_calculated, is_closed, closed_at) VALUES
(2, 1, CURRENT_DATE - INTERVAL '2 day', 100000.00, 150000.00, 500.00, TRUE, (CURRENT_DATE - INTERVAL '2 day') + TIME '20:00:00'),
(2, 1, CURRENT_DATE - INTERVAL '1 day', 150000.00, 180000.00, 750.00, TRUE, (CURRENT_DATE - INTERVAL '1 day') + TIME '20:30:00'),
(2, 1, CURRENT_DATE, 180000.00, NULL, NULL, FALSE, NULL) -- Today's balance, not closed yet
ON CONFLICT (user_id, service_id, balance_date) DO NOTHING;

-- Gérant Un (user_id 2), MOOV Money (service_id 2)
INSERT INTO balances (user_id, service_id, balance_date, initial_balance, final_balance, total_commission_calculated, is_closed, closed_at) VALUES
(2, 2, CURRENT_DATE - INTERVAL '1 day', 200000.00, 220000.00, 1200.00, TRUE, (CURRENT_DATE - INTERVAL '1 day') + TIME '21:00:00'),
(2, 2, CURRENT_DATE, 220000.00, NULL, NULL, FALSE, NULL)
ON CONFLICT (user_id, service_id, balance_date) DO NOTHING;

-- Gérant Deux (user_id 3), MTN Money (service_id 1)
INSERT INTO balances (user_id, service_id, balance_date, initial_balance, final_balance, total_commission_calculated, is_closed, closed_at) VALUES
(3, 1, CURRENT_DATE - INTERVAL '1 day', 50000.00, 70000.00, 300.00, TRUE, (CURRENT_DATE - INTERVAL '1 day') + TIME '19:00:00'),
(3, 1, CURRENT_DATE, 70000.00, NULL, NULL, FALSE, NULL)
ON CONFLICT (user_id, service_id, balance_date) DO NOTHING;


-- Operations (Quelques opérations pour les jours passés)
-- Assuming balance_id for (gerant01, MTN, CURRENT_DATE - INTERVAL '1 day') is 2
-- Assuming balance_id for (gerant01, MOOV, CURRENT_DATE - INTERVAL '1 day') is 4
-- Assuming balance_id for (gerant02, MTN, CURRENT_DATE - INTERVAL '1 day') is 5
-- These IDs would need to be fetched dynamically in a real seeding script or ORM.
-- For simplicity, we'll insert some without direct balance_id linkage, or assume they link to the most recent relevant balance.

-- Operations for Gérant Un (user_id 2), MTN Money (service_id 1), Dépôt (op_type_id 1)
INSERT INTO operations (user_id, service_id, operation_type_id, amount, commission_applied, operation_time, description, balance_id) VALUES
(2, 1, 1, 10000.00, 100.00, (CURRENT_DATE - INTERVAL '1 day') + TIME '09:00:00', 'Dépôt client A', (SELECT id from balances WHERE user_id=2 AND service_id=1 AND balance_date = CURRENT_DATE - INTERVAL '1 day')),
(2, 1, 2, 5000.00, 50.00, (CURRENT_DATE - INTERVAL '1 day') + TIME '10:30:00', 'Retrait client B', (SELECT id from balances WHERE user_id=2 AND service_id=1 AND balance_date = CURRENT_DATE - INTERVAL '1 day')),
(2, 1, 1, 20000.00, 200.00, (CURRENT_DATE - INTERVAL '1 day') + TIME '14:15:00', 'Dépôt client C', (SELECT id from balances WHERE user_id=2 AND service_id=1 AND balance_date = CURRENT_DATE - INTERVAL '1 day'));

-- Operations for Gérant Un (user_id 2), MOOV Money (service_id 2), Retrait (op_type_id 2)
INSERT INTO operations (user_id, service_id, operation_type_id, amount, commission_applied, operation_time, description, balance_id) VALUES
(2, 2, 2, 15000.00, 150.00, (CURRENT_DATE - INTERVAL '1 day') + TIME '11:00:00', 'Retrait MOOV client D', (SELECT id from balances WHERE user_id=2 AND service_id=2 AND balance_date = CURRENT_DATE - INTERVAL '1 day'));

-- Operations for Gérant Deux (user_id 3), MTN Money (service_id 1), Achat de Crédit (op_type_id 4)
INSERT INTO operations (user_id, service_id, operation_type_id, amount, commission_applied, operation_time, description, balance_id) VALUES
(3, 1, 4, 5000.00, 25.00, (CURRENT_DATE - INTERVAL '1 day') + TIME '16:00:00', 'Achat crédit MTN client E', (SELECT id from balances WHERE user_id=3 AND service_id=1 AND balance_date = CURRENT_DATE - INTERVAL '1 day'));

-- Today's operations (no final balance yet)
INSERT INTO operations (user_id, service_id, operation_type_id, amount, commission_applied, operation_time, description, balance_id) VALUES
(2, 1, 1, 12000.00, 120.00, CURRENT_TIMESTAMP - INTERVAL '2 hour', 'Dépôt client F', (SELECT id from balances WHERE user_id=2 AND service_id=1 AND balance_date = CURRENT_DATE)),
(3, 1, 2, 3000.00, 30.00, CURRENT_TIMESTAMP - INTERVAL '1 hour', 'Retrait client G', (SELECT id from balances WHERE user_id=3 AND service_id=1 AND balance_date = CURRENT_DATE));


-- Alerts (Exemples d'alertes)
INSERT INTO alerts (user_id, alert_type, message, severity, is_resolved, resolved_at) VALUES
(2, 'large_discrepancy', 'Écart important détecté pour Gérant Un le ' || (CURRENT_DATE - INTERVAL '2 day')::text, 'critical', TRUE, (CURRENT_DATE - INTERVAL '1 day') + TIME '10:00:00'),
(NULL, 'system_info', 'Base de données initialisée avec des données de test.', 'info', TRUE, CURRENT_TIMESTAMP),
(3, 'closing_due', 'Clôture non effectuée pour Gérant Deux hier.', 'warning', FALSE, NULL);

-- Audit Log (Exemples d'actions)
INSERT INTO audit_logs (user_id, action, target_entity, target_id, details, ip_address, user_agent) VALUES
(1, 'login', 'user', 1, '{"status": "success"}', '127.0.0.1', 'System Seed'),
(2, 'create_operation', 'operations', 1, '{"amount": 10000, "service": "MTN Money"}', '192.168.1.10', 'Mobile App v1.0');

-- Note: The dynamic fetching of IDs (e.g., for user_id, service_id, balance_id in operations)
-- is simplified here. In a robust seeding script, you would typically use variables or a
-- scripting language approach to ensure correct foreign key references.
-- The ON CONFLICT DO NOTHING clauses help prevent errors if the script is run multiple times,
-- but for more complex scenarios, specific conflict handling (like DO UPDATE) might be needed.
