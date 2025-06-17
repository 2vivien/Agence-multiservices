-- Check if the database exists, and create it if not
-- Note: This command might need to be run manually or with superuser privileges
-- depending on the PostgreSQL setup. For the purpose of this file,
-- we assume the database is created and we are connecting to it.
-- CREATE DATABASE agence_multiservice_db;

-- Users table (Gérants)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NULL, -- Added email column
    role VARCHAR(50) NOT NULL DEFAULT 'gerant', -- e.g., 'gerant', 'admin'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Services table (MTN, MOOV, CELTIS, WU, RIA...)
CREATE TABLE IF NOT EXISTS services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    default_commission_rate DECIMAL(5, 2) DEFAULT 0.00, -- Default commission rate for the service
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Operation Types table (transfert, forfait, retrait...)
CREATE TABLE IF NOT EXISTS operation_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Balances table (Solde initial et final par service, par jour et par gérant)
CREATE TABLE IF NOT EXISTS balances (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_id INTEGER REFERENCES services(id) ON DELETE CASCADE NULL, -- Made nullable for global balances
    balance_date DATE NOT NULL,
    initial_amount DECIMAL(15, 2) NOT NULL, -- Renamed from initial_balance
    calculated_final_amount DECIMAL(15, 2) NULL, -- Theoretical final balance, calculated by app
    actual_final_amount DECIMAL(15, 2) NULL, -- Actual final balance, entered by user (was final_balance)
    difference_amount DECIMAL(15, 2) NULL, -- Difference, calculated by app
    total_commission_calculated DECIMAL(15, 2) NULL, -- To be calculated based on operations for this balance period
    notes TEXT NULL,
    is_closed BOOLEAN DEFAULT FALSE, -- Kept for now, can be derived from closed_at
    closed_at TIMESTAMP NULL, -- Timestamp when the balance was closed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Unique constraint for per-service daily balances
    CONSTRAINT unique_user_service_date UNIQUE (user_id, service_id, balance_date),
    -- Unique constraint for global daily balances (service_id IS NULL)
    -- Note: Partial unique indexes syntax can vary. This is conceptual for PostgreSQL.
    -- CREATE UNIQUE INDEX unique_user_date_global ON balances (user_id, balance_date) WHERE service_id IS NULL;
    -- For simplicity in cross-DB schema, we might rely on application logic to enforce uniqueness for global balances,
    -- or use a more complex setup if strict DB enforcement for this is needed immediately.
    -- A simpler UNIQUE constraint that allows NULLs but might not fully enforce the "global" aspect without care:
    UNIQUE (user_id, balance_date, service_id) -- Standard unique constraint, service_id can be NULL
);

-- Operations table (Enregistrement des transactions)
-- No changes needed here as balance_id already exists.
CREATE TABLE IF NOT EXISTS operations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, -- Gérant who performed the operation
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    operation_type_id INTEGER NOT NULL REFERENCES operation_types(id) ON DELETE CASCADE,
    balance_id INTEGER REFERENCES balances(id) ON DELETE SET NULL, -- Link to the daily balance
    amount DECIMAL(15, 2) NOT NULL,
    commission_applied DECIMAL(10, 2) NOT NULL,
    operation_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    reference_id VARCHAR(255), -- Optional reference for the transaction
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, -- User associated with the alert, if any
    alert_type VARCHAR(255) NOT NULL, -- e.g., 'closing_due', 'large_discrepancy', 'missing_data'
    message TEXT NOT NULL,
    severity VARCHAR(50) DEFAULT 'warning', -- e.g., 'info', 'warning', 'critical'
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reports table (To store generated reports or metadata)
CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(100) NOT NULL, -- e.g., 'daily_summary', 'monthly_gerant_performance'
    generated_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    generation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255), -- Path to the generated PDF/Excel file
    criteria JSONB, -- Parameters used for generating the report
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Audit Log table (Historique complet des actions par utilisateur)
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(255) NOT NULL, -- e.g., 'login', 'create_operation', 'delete_user'
    target_entity VARCHAR(100), -- e.g., 'operation', 'user'
    target_id INTEGER,
    details JSONB, -- Additional details about the action
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
   NEW.updated_at = NOW();
   RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers to update updated_at timestamp for relevant tables
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_services_updated_at BEFORE UPDATE ON services FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_operation_types_updated_at BEFORE UPDATE ON operation_types FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_balances_updated_at BEFORE UPDATE ON balances FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_operations_updated_at BEFORE UPDATE ON operations FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_alerts_updated_at BEFORE UPDATE ON alerts FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_reports_updated_at BEFORE UPDATE ON reports FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Note: More specific constraints, indexes, and potentially more tables
-- (e.g., for sessions, roles_permissions) might be needed as development progresses.
