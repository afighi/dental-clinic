-- ==============================================================================
-- Supabase (PostgreSQL) Migration Script
-- Converted from MySQL backup: if0_38930070_jrc_dental_care (2025-05-15)
-- ==============================================================================

-- 1. CLEANUP PREVIOUS TABLES (Reverse order of dependencies)
DROP TABLE IF EXISTS "user_logs" CASCADE;
DROP TABLE IF EXISTS "audittrail" CASCADE;
DROP TABLE IF EXISTS "report" CASCADE;
DROP TABLE IF EXISTS "password_reset" CASCADE;
DROP TABLE IF EXISTS "notification" CASCADE;
DROP TABLE IF EXISTS "notification_settings" CASCADE;
DROP TABLE IF EXISTS "calendar_settings" CASCADE;
DROP TABLE IF EXISTS "patient_info" CASCADE;
DROP TABLE IF EXISTS "dentist_info" CASCADE;
DROP TABLE IF EXISTS "dentist_break" CASCADE;
DROP TABLE IF EXISTS "dentist_schedule" CASCADE;
DROP TABLE IF EXISTS "billing" CASCADE;
DROP TABLE IF EXISTS "payment" CASCADE;
DROP TABLE IF EXISTS "treatment" CASCADE;
DROP TABLE IF EXISTS "appointment" CASCADE;
DROP TABLE IF EXISTS "clinic_settings" CASCADE;
DROP TABLE IF EXISTS "apiintegration" CASCADE;
DROP TABLE IF EXISTS "service" CASCADE;
DROP TABLE IF EXISTS "user" CASCADE;
DROP TABLE IF EXISTS "userrole" CASCADE;

-- ==============================================================================
-- 2. CREATE SCHEMAS & TABLES
-- ==============================================================================

-- User Role Table
CREATE TABLE "userrole" (
    "role_id" SERIAL PRIMARY KEY,
    "role_name" VARCHAR(50) NOT NULL UNIQUE,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- User Table ("user" is a reserved word in PostgreSQL, keep quoted)
CREATE TABLE "user" (
    "user_id" SERIAL PRIMARY KEY,
    "first_name" VARCHAR(255) NOT NULL,
    "middle_name" VARCHAR(255) DEFAULT NULL,
    "last_name" VARCHAR(255) NOT NULL,
    "suffix" VARCHAR(10) DEFAULT NULL,
    "username" VARCHAR(50) NOT NULL UNIQUE,
    "email" VARCHAR(255) NOT NULL UNIQUE,
    "email_verified" SMALLINT DEFAULT 0,
    "password_hash" VARCHAR(255) NOT NULL,
    "phone_number" VARCHAR(15) DEFAULT NULL,
    "address" TEXT DEFAULT NULL,
    "date_of_birth" DATE DEFAULT NULL,
    "role_id" INTEGER NOT NULL REFERENCES "userrole"("role_id"),
    "is_deleted" SMALLINT DEFAULT 0,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "verification_token" VARCHAR(255) DEFAULT NULL,
    "email_verified_at" TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    "verification_token_expires_at" TIMESTAMP WITH TIME ZONE DEFAULT NULL
);

CREATE INDEX "idx_user_role" ON "user"("role_id");

-- Service Table
CREATE TABLE "service" (
    "service_id" SERIAL PRIMARY KEY,
    "service_name" VARCHAR(255) NOT NULL,
    "description" TEXT DEFAULT NULL,
    "price" INTEGER NOT NULL DEFAULT 0,
    "duration_minutes" INTEGER NOT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- API Integration Table
CREATE TABLE "apiintegration" (
    "api_id" SERIAL PRIMARY KEY,
    "api_name" VARCHAR(50) NOT NULL,
    "api_type" VARCHAR(20) NOT NULL,
    "credentials" JSONB DEFAULT NULL,
    "is_active" SMALLINT DEFAULT 1,
    "last_sync_time" TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Clinic Settings Table
CREATE TABLE "clinic_settings" (
    "setting_id" SERIAL PRIMARY KEY,
    "clinic_name" VARCHAR(255) NOT NULL DEFAULT 'JRC Dental Care',
    "address" TEXT NOT NULL,
    "phone" VARCHAR(50) NOT NULL,
    "email" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Appointment Table
CREATE TABLE "appointment" (
    "appointment_id" SERIAL PRIMARY KEY,
    "patient_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "dentist_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "appointment_date" TIMESTAMP WITH TIME ZONE NOT NULL,
    "status" VARCHAR(20) DEFAULT 'pending' CHECK ("status" IN ('pending', 'confirmed', 'canceled', 'completed')),
    "service_id" INTEGER NOT NULL REFERENCES "service"("service_id"),
    "google_event_id" VARCHAR(255) DEFAULT NULL,
    "notes" TEXT DEFAULT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_appointment_patient" ON "appointment"("patient_id");
CREATE INDEX "idx_appointment_dentist" ON "appointment"("dentist_id");
CREATE INDEX "idx_appointment_service" ON "appointment"("service_id");

-- Treatment Table
CREATE TABLE "treatment" (
    "treatment_id" SERIAL PRIMARY KEY,
    "patient_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "dentist_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "service_id" INTEGER NOT NULL REFERENCES "service"("service_id"),
    "appointment_id" INTEGER REFERENCES "appointment"("appointment_id"),
    "treatment_date" DATE NOT NULL,
    "status" VARCHAR(20) DEFAULT 'planned' CHECK ("status" IN ('planned', 'in_progress', 'completed')),
    "cost" NUMERIC(10,2) NOT NULL,
    "notes" TEXT DEFAULT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_treatment_patient" ON "treatment"("patient_id");
CREATE INDEX "idx_treatment_dentist" ON "treatment"("dentist_id");
CREATE INDEX "idx_treatment_service" ON "treatment"("service_id");
CREATE INDEX "idx_treatment_appointment" ON "treatment"("appointment_id");

-- Payment Table
CREATE TABLE "payment" (
    "payment_id" SERIAL PRIMARY KEY,
    "appointment_id" INTEGER NOT NULL REFERENCES "appointment"("appointment_id"),
    "paymongo_payment_intent_id" VARCHAR(50) NOT NULL,
    "paymongo_payment_id" VARCHAR(50) DEFAULT NULL,
    "amount" INTEGER NOT NULL,
    "currency" VARCHAR(10) DEFAULT 'PHP',
    "status" VARCHAR(20) DEFAULT 'pending' CHECK ("status" IN ('pending', 'succeeded', 'failed')),
    "payment_method" VARCHAR(20) NOT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_payment_appointment" ON "payment"("appointment_id");

-- Billing Table
CREATE TABLE "billing" (
    "billing_id" SERIAL PRIMARY KEY,
    "patient_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "treatment_id" INTEGER REFERENCES "treatment"("treatment_id"),
    "total_amount" NUMERIC(10,2) NOT NULL,
    "payment_status" VARCHAR(20) DEFAULT 'pending' CHECK ("payment_status" IN ('pending', 'paid', 'failed')),
    "billing_date" DATE NOT NULL,
    "due_date" DATE NOT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_billing_patient" ON "billing"("patient_id");
CREATE INDEX "idx_billing_treatment" ON "billing"("treatment_id");

-- Dentist Schedule Table
CREATE TABLE "dentist_schedule" (
    "schedule_id" SERIAL PRIMARY KEY,
    "dentist_id" INTEGER NOT NULL REFERENCES "user"("user_id") ON DELETE CASCADE ON UPDATE CASCADE,
    "day_of_week" VARCHAR(10) NOT NULL,
    "start_time" TIME NOT NULL DEFAULT '09:00:00',
    "end_time" TIME NOT NULL DEFAULT '17:00:00',
    "is_available" SMALLINT NOT NULL DEFAULT 1,
    "created_at" TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT NULL
);

CREATE INDEX "idx_dentist_schedule_dentist" ON "dentist_schedule"("dentist_id");

-- Dentist Break Table
CREATE TABLE "dentist_break" (
    "break_id" SERIAL PRIMARY KEY,
    "schedule_id" INTEGER NOT NULL REFERENCES "dentist_schedule"("schedule_id") ON DELETE CASCADE ON UPDATE CASCADE,
    "start_time" TIME NOT NULL,
    "end_time" TIME NOT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT NULL
);

CREATE INDEX "idx_dentist_break_schedule" ON "dentist_break"("schedule_id");

-- Dentist Info Table
CREATE TABLE "dentist_info" (
    "dentist_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "license_number" VARCHAR(50) NOT NULL UNIQUE,
    "specialization" VARCHAR(255) DEFAULT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_dentist_info_user" ON "dentist_info"("user_id");

-- Patient Info Table
CREATE TABLE "patient_info" (
    "patient_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "date_of_birth" DATE DEFAULT NULL,
    "emergency_contact_name" VARCHAR(255) DEFAULT NULL,
    "emergency_contact_phone" VARCHAR(15) DEFAULT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_patient_info_user" ON "patient_info"("user_id");

-- Calendar Settings Table
CREATE TABLE "calendar_settings" (
    "setting_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL UNIQUE REFERENCES "user"("user_id") ON DELETE CASCADE ON UPDATE CASCADE,
    "google_calendar_enabled" SMALLINT NOT NULL DEFAULT 0,
    "default_reminder_time" INTEGER NOT NULL DEFAULT 60,
    "created_at" TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT NULL
);

-- Notification Settings Table
CREATE TABLE "notification_settings" (
    "setting_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL UNIQUE REFERENCES "user"("user_id") ON DELETE CASCADE ON UPDATE CASCADE,
    "email_notifications" SMALLINT NOT NULL DEFAULT 1,
    "appointment_reminders" SMALLINT NOT NULL DEFAULT 1,
    "treatment_updates" SMALLINT NOT NULL DEFAULT 1,
    "created_at" TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT NULL
);

-- Notification Table
CREATE TABLE "notification" (
    "notification_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "notification_type" VARCHAR(50) NOT NULL,
    "notification_message" TEXT NOT NULL,
    "related_entity_id" INTEGER DEFAULT NULL,
    "related_table" VARCHAR(50) DEFAULT NULL,
    "is_read" SMALLINT DEFAULT 0,
    "is_sent" SMALLINT DEFAULT 0,
    "send_via" VARCHAR(20) DEFAULT 'email',
    "scheduled_time" TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    "sent_time" TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_notification_user" ON "notification"("user_id");

-- Password Reset Table
CREATE TABLE "password_reset" (
    "reset_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "token" VARCHAR(255) NOT NULL,
    "expiry_time" TIMESTAMP WITH TIME ZONE NOT NULL
);

CREATE INDEX "idx_password_reset_user" ON "password_reset"("user_id");

-- Report Table
CREATE TABLE "report" (
    "report_id" SERIAL PRIMARY KEY,
    "report_name" VARCHAR(255) NOT NULL,
    "report_type" VARCHAR(30) NOT NULL CHECK ("report_type" IN ('operational', 'financial', 'user', 'appointment')),
    "generated_by" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "file_path" VARCHAR(255) NOT NULL,
    "parameters" JSONB DEFAULT NULL,
    "generated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "created_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX "idx_report_user" ON "report"("generated_by");

-- Audit Trail Table
CREATE TABLE "audittrail" (
    "audit_id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL REFERENCES "user"("user_id"),
    "action_performed" TEXT NOT NULL,
    "table_affected" VARCHAR(255) NOT NULL,
    "timestamp" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    "ip_address" VARCHAR(45) DEFAULT NULL,
    "details" TEXT DEFAULT NULL
);

CREATE INDEX "idx_audittrail_user" ON "audittrail"("user_id");

-- User Logs Table
CREATE TABLE "user_logs" (
    "log_id" SERIAL PRIMARY KEY,
    "action_type" VARCHAR(20) DEFAULT NULL,
    "user_id" INTEGER NOT NULL,
    "first_name" VARCHAR(255) NOT NULL,
    "middle_name" VARCHAR(255) DEFAULT NULL,
    "last_name" VARCHAR(255) NOT NULL,
    "suffix" VARCHAR(10) DEFAULT NULL,
    "username" VARCHAR(50) NOT NULL,
    "email" VARCHAR(255) NOT NULL,
    "phone_number" VARCHAR(15) DEFAULT NULL,
    "address" TEXT DEFAULT NULL,
    "date_of_birth" DATE DEFAULT NULL,
    "role_id" INTEGER NOT NULL,
    "is_deleted" SMALLINT DEFAULT 0,
    "log_timestamp" TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ==============================================================================
-- 3. INSERT MIGRATION DATA
-- ==============================================================================

-- 3.1 userrole
INSERT INTO "userrole" ("role_id", "role_name", "created_at", "updated_at") VALUES
(1, 'Admin', '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(2, 'Dentist', '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(3, 'Patient', '2025-05-08 03:03:34', '2025-05-08 03:03:34');

-- 3.2 user
INSERT INTO "user" ("user_id", "first_name", "middle_name", "last_name", "suffix", "username", "email", "email_verified", "password_hash", "phone_number", "address", "date_of_birth", "role_id", "is_deleted", "created_at", "verification_token", "email_verified_at", "verification_token_expires_at") VALUES
(1, 'Ahiah', 'Maximo', 'De Guzman', '', 'ardg', 'developer@example.com', 1, '$2y$10$19zTCnSok08K6Cimn76XIe35UbX8sN3ukhnZHEiKlneK5PybWKOQW', '09123456781', 'Bulacan, Philippines', '2000-01-01', 1, 0, '2025-05-08 03:04:47', NULL, NULL, NULL),
(2, 'Admin', NULL, 'User', NULL, 'admin', 'admin@jrcdentalcare.com', 1, '$2y$10$B1M5yzLenXx8UGrMEpHO5eJVAMn.5ImPdpSaQIf5wzKKqyefqiOLa', '09123456782', 'JRC Dental Care, Bonga Menor, Bustos', '1990-01-01', 1, 0, '2025-05-08 03:14:56', NULL, NULL, NULL),
(3, 'John', 'Robert', 'Cruz', 'DMD', 'dentist', 'dentist@jrcdentalcare.com', 1, '$2y$10$GXtFfXzgV5qE1QHEhRKJJefnPX91Vj0xD5tI8sEEjP8hqJHHnN3/q', '09123456783', 'JRC Dental Care, Bonga Menor, Bustos', '1985-05-15', 2, 0, '2025-05-08 03:14:56', NULL, NULL, NULL),
(5, 'Admin', NULL, 'User', NULL, 'admin_jrc', 'admin_staff@jrcdentalcare.com', 1, '0192023a7bbd73250516f069df18b500', '09123456784', 'JRC Dental Care, Bonga Menor, Bustos', '1990-01-01', 1, 0, '2025-05-08 03:17:28', NULL, NULL, NULL),
(6, 'John', 'Robert', 'Cruz', 'DMD', 'dentist_jrc', 'lead_dentist@jrcdentalcare.com', 1, '0192023a7bbd73250516f069df18b500', '09123456785', 'JRC Dental Care, Bonga Menor, Bustos', '1985-05-15', 2, 0, '2025-05-08 03:17:28', NULL, NULL, NULL),
(8, 'Naga', '', 'Che', '', 'naga', 'staff@example.com', 1, '$2y$10$lZAHKEkWsT27O5W1MW8xY.K1jXUjN/lH9SE/9CxB8OSzbJkL0GFcu', '09123456786', 'Bustos, Bulacan', '1998-05-04', 3, 0, '2025-05-08 03:22:07', NULL, NULL, NULL),
(9, 'Jose', '', 'Kim', '', 'hkim', 'dentist2@example.com', 1, '$2y$10$2p5TY7QDIe/QVwCy6prm0u7kohN3wvMI4tznrx55WpIDdRlKcPfw6', '09123456787', 'Bustos, Bulacan', '1992-05-16', 2, 0, '2025-05-08 03:23:22', NULL, NULL, NULL),
(10, 'Lee', 'Jung', 'Ki', '', 'kijun', 'patient1@example.com', 1, '$2y$10$pr8h/vc1Qyea/6SY2L9yiudy26W4LaFcyRWG/iOgcgElN9tr.fi82', '09123456788', 'Bustos, Bulacan', '1995-05-13', 4, 0, '2025-05-15 08:15:13', NULL, NULL, NULL),
(11, 'Maria', 'Santos', 'Cruz', '', 'patient2', 'patient2@example.com', 0, '$2y$10$l6sRSetl0a36p.VJrBvz.e/E0VHYeWIRQMaT8aaqAC/k8d72/yJdC', '09123456789', 'Bustos, Bulacan', '1999-05-22', 4, 0, '2025-05-15 11:51:56', NULL, NULL, NULL);

-- 3.3 service
INSERT INTO "service" ("service_id", "service_name", "description", "price", "duration_minutes", "created_at", "updated_at") VALUES
(1, 'Consultation Fee', 'Initial dental consultation', 45000, 15, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(2, 'Oral Prophylaxis', 'Routine teeth cleaning and scaling', 120000, 30, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(3, 'Topical Fluoride Treatment', 'Fluoride application for upper and lower teeth', 150000, 20, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(4, 'Tooth Extraction', 'Removal of a tooth', 120000, 30, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(5, 'Odontecomy', 'Surgical removal of a tooth (minimum)', 1350000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(6, 'Composite Restoration', 'Tooth-colored filling, per tooth (single surface)', 150000, 45, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(7, 'Temporary Filling', 'Temporary filling per tooth', 75000, 15, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(8, 'Pits & Fissure Sealant', 'Sealant application per tooth', 105000, 20, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(9, 'Root Canal Treatment', 'Root canal treatment per canal', 900000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(10, 'Direct Veneers', 'Direct veneer application per tooth', 750000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(11, 'Indirect Veneers', 'Indirect veneer application per tooth', 1950000, 90, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(12, 'Whitening Conventional', 'Conventional teeth whitening for upper and lower teeth', 1800000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(13, 'Light Activated Whitening', 'Light-activated teeth whitening for upper and lower teeth', 3000000, 90, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(14, 'Fixed Partial Denture (Plastic)', 'Plastic fixed partial denture per unit', 600000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(15, 'Fixed Partial Denture (PFM)', 'Porcelain-fused-to-metal fixed partial denture per unit', 900000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(16, 'Fixed Partial Denture (E-Max)', 'E-Max fixed partial denture per unit', 2700000, 90, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(17, 'Fixed Partial Denture (Zirconia)', 'Zirconia fixed partial denture per unit', 3750000, 90, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(18, 'Complete Denture (Plastic Ordinary)', 'Plastic ordinary complete denture for upper and lower', 1800000, 120, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(19, 'Complete Denture (Plastic US)', 'Plastic US complete denture for upper and lower', 3000000, 120, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(20, 'Complete Denture (Porcelain China)', 'Porcelain China complete denture for upper and lower', 2250000, 120, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(21, 'Complete Denture (Porcelain US)', 'Porcelain US complete denture for upper and lower', 3750000, 120, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(22, 'Removable Partial Denture (Assemblage)', 'Assemblage removable partial denture for upper or lower', 750000, 60, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(23, 'Removable Partial Denture (One Piece Casting)', 'One piece casting removable partial denture for upper or lower', 1500000, 90, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(24, 'Removable Partial Denture (Flexible)', 'Flexible removable partial denture for upper or lower', 3000000, 90, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(25, 'Orthodontics', 'Orthodontic treatment for upper and lower teeth', 7500000, 180, '2025-05-08 03:03:34', '2025-05-08 03:03:34');

-- 3.4 apiintegration
INSERT INTO "apiintegration" ("api_id", "api_name", "api_type", "credentials", "is_active", "last_sync_time", "created_at", "updated_at") VALUES
(1, 'PHPMailer', 'email', '{"email": "clinic@example.com", "app_password": "your_app_password_here"}', 1, NULL, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(2, 'GoogleCalendar', 'calendar', '{"client_id": "your_google_client_id.apps.googleusercontent.com", "client_secret": "your_google_client_secret"}', 1, NULL, '2025-05-08 03:03:34', '2025-05-08 03:03:34'),
(3, 'PayMongo', 'payment', '{"public_key": "pk_test_sample_key", "secret_key": "sk_test_sample_key"}', 1, NULL, '2025-05-08 03:03:34', '2025-05-08 03:03:34');

-- 3.5 clinic_settings
INSERT INTO "clinic_settings" ("setting_id", "clinic_name", "address", "phone", "email", "created_at", "updated_at") VALUES
(1, 'JRC Dental Care', 'Bonga Mayos, BUstos', '(+63)0991 678 4567', 'jrcdentalclinic@gmail.com', '2025-05-08 03:03:34', '2025-05-08 03:03:34');

-- 3.6 appointment
INSERT INTO "appointment" ("appointment_id", "patient_id", "dentist_id", "appointment_date", "status", "service_id", "google_event_id", "notes", "created_at", "updated_at") VALUES
(1, 8, 9, '2025-05-16 10:30:00', 'canceled', 1, 'event_1746700266', '574564564', '2025-05-08 03:31:06', '2025-05-08 05:26:04'),
(2, 8, 9, '2025-05-21 12:00:00', 'pending', 20, 'event_1746707179', '76856483456', '2025-05-08 05:26:19', '2025-05-08 05:26:19'),
(3, 8, 9, '2025-05-28 12:30:00', 'pending', 6, 'event_1746713366', '7484yyt', '2025-05-08 07:09:26', '2025-05-08 07:09:26');

-- 3.7 payment
INSERT INTO "payment" ("payment_id", "appointment_id", "paymongo_payment_intent_id", "paymongo_payment_id", "amount", "currency", "status", "payment_method", "created_at", "updated_at") VALUES
(1, 1, 'pi_Xfyn4UddxucJrrWPrdHCnuRK', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 03:31:54', '2025-05-08 03:31:54'),
(2, 1, 'pi_HgSwbQuzBPmZcpdM32UYGEH2', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 03:33:01', '2025-05-08 03:33:01'),
(3, 1, 'pi_pMTcVLP3hp2AikboxCh8iBdn', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 03:44:20', '2025-05-08 03:44:20'),
(4, 1, 'pi_xaevYC6qLLpoh6pCAwxqwNL9', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 03:51:42', '2025-05-08 03:51:42'),
(5, 1, 'pi_48hts2NvA4hKtX94ZeUMpZ6b', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 04:24:45', '2025-05-08 04:24:45'),
(6, 1, 'pi_CkkJ3v81gtTBE6VDFDf2HVei', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 04:26:34', '2025-05-08 04:26:34'),
(7, 1, 'pi_o6arQWWa4L8mZ1wuJLYEuqzW', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 04:27:11', '2025-05-08 04:27:11'),
(8, 1, 'pi_B4PFovfhnu8B83bYETzKX6xT', NULL, 45000, 'PHP', 'pending', 'gcash', '2025-05-08 04:36:17', '2025-05-08 04:36:17'),
(9, 1, 'pi_SqLDwSmbeBgQswLaBdM2kDG2', NULL, 45000, 'PHP', 'succeeded', 'gcash', '2025-05-08 04:37:12', '2025-05-08 04:56:37'),
(10, 2, 'pi_cdcDFYVgT2Exw8TWycuBF4QP', NULL, 2250000, 'PHP', 'succeeded', 'gcash', '2025-05-08 05:26:23', '2025-05-08 05:26:33'),
(11, 3, 'pi_zkbefU4T5WsrL6Lc5WQowV7B', NULL, 150000, 'PHP', 'succeeded', 'gcash', '2025-05-08 07:09:30', '2025-05-09 16:55:15'),
(12, 3, 'pi_JCybEN1ELYmsVMjEZJDmyXA3', NULL, 150000, 'PHP', 'succeeded', 'gcash', '2025-05-08 07:11:40', '2025-05-08 07:12:26');

-- 3.8 dentist_info
INSERT INTO "dentist_info" ("dentist_id", "user_id", "license_number", "specialization", "created_at", "updated_at") VALUES
(1, 3, 'DMD-12345', 'General Dentistry', '2025-05-08 03:14:56', '2025-05-08 03:14:56'),
(3, 6, 'DMD-123345', 'General Dentistry', '2025-05-08 03:17:48', '2025-05-08 03:17:48');

-- 3.9 patient_info
INSERT INTO "patient_info" ("patient_id", "user_id", "date_of_birth", "emergency_contact_name", "emergency_contact_phone", "created_at", "updated_at") VALUES
(1, 1, '2025-04-30', NULL, NULL, '2025-05-08 03:04:47', '2025-05-08 03:04:47'),
(2, 8, '2025-05-04', NULL, NULL, '2025-05-08 03:22:07', '2025-05-08 03:22:07'),
(3, 9, '2025-05-16', NULL, NULL, '2025-05-08 03:23:22', '2025-05-08 03:23:22');

-- 3.10 notification
INSERT INTO "notification" ("notification_id", "user_id", "notification_type", "notification_message", "related_entity_id", "related_table", "is_read", "is_sent", "send_via", "scheduled_time", "sent_time", "created_at") VALUES
(1, 9, 'appointment', 'Patient Naga Che has requested an appointment for Consultation Fee on May 16, 2025 10:30 AM', 1, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 03:31:06'),
(2, 8, 'appointment', 'Your appointment request with Dr. Jose Kim for Consultation Fee on May 16, 2025 10:30 AM has been submitted and is pending confirmation.', 1, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 03:31:06'),
(3, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 1, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 03:31:55'),
(4, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 2, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 03:33:01'),
(5, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 3, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 03:44:21'),
(6, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 4, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 03:51:43'),
(7, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 5, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:24:45'),
(8, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 6, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:26:35'),
(9, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 7, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:27:11'),
(10, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 8, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:36:17'),
(11, 9, 'payment', 'Naga Che has initiated payment for appointment #1', 9, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:37:12'),
(12, 8, 'payment_confirmed', 'Your payment has been confirmed.', 9, 'payment', true, false, 'email', NULL, NULL, '2025-05-08 04:56:37'),
(13, 9, 'payment', 'Naga Che has successfully paid for appointment on May 16, 2025', 9, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:56:44'),
(14, 9, 'payment', 'Naga Che has successfully paid for appointment on May 16, 2025', 9, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 04:57:28'),
(15, 9, 'appointment', 'Naga Che has canceled the appointment for Consultation Fee on May 16, 2025 at 10:30 AM', 1, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 05:26:04'),
(16, 9, 'appointment', 'Patient Naga Che has requested an appointment for Complete Denture (Porcelain China) on May 21, 2025 12:00 PM', 2, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 05:26:19'),
(17, 8, 'appointment', 'Your appointment request with Dr. Jose Kim for Complete Denture (Porcelain China) on May 21, 2025 12:00 PM has been submitted and is pending confirmation.', 2, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 05:26:19'),
(18, 9, 'payment', 'Naga Che has initiated payment for appointment #2', 10, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 05:26:24'),
(19, 8, 'payment_confirmed', 'Your payment has been confirmed.', 10, 'payment', true, false, 'email', NULL, NULL, '2025-05-08 05:26:33'),
(20, 9, 'payment', 'Naga Che has successfully paid for appointment on May 21, 2025', 10, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 05:26:37'),
(21, 9, 'payment', 'Naga Che has successfully paid for appointment on May 21, 2025', 10, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 05:26:53'),
(22, 9, 'appointment', 'Patient Naga Che has requested an appointment for Composite Restoration on May 28, 2025 12:30 PM', 3, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 07:09:26'),
(23, 8, 'appointment', 'Your appointment request with Dr. Jose Kim for Composite Restoration on May 28, 2025 12:30 PM has been submitted and is pending confirmation.', 3, 'appointment', true, true, 'email', NULL, NULL, '2025-05-08 07:09:26'),
(24, 9, 'payment', 'Naga Che has initiated payment for appointment #3', 11, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 07:09:30'),
(25, 9, 'payment', 'Naga Che has initiated payment for appointment #3', 12, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 07:11:41'),
(26, 8, 'payment_confirmed', 'Your payment has been confirmed.', 12, 'payment', true, false, 'email', NULL, NULL, '2025-05-08 07:12:26'),
(27, 9, 'payment', 'Naga Che has successfully paid for appointment on May 28, 2025', 12, 'payment', true, true, 'email', NULL, NULL, '2025-05-08 08:26:37'),
(28, 8, 'payment_confirmed', 'Your payment has been confirmed.', 11, 'payment', true, false, 'email', NULL, NULL, '2025-05-09 16:55:15'),
(29, 10, 'account_created', 'Your account has been created. Username: kijun, Password: 8$BCya!yuZ', 10, 'user', true, false, 'email', '2025-05-15 08:15:13', NULL, '2025-05-15 08:15:13'),
(30, 3, 'account_recovered', 'Your account has been recovered.', 3, 'user', false, false, 'email', '2025-05-15 08:58:51', NULL, '2025-05-15 08:58:51'),
(31, 2, 'account_recovered', 'Your account has been recovered.', 2, 'user', false, false, 'email', '2025-05-15 08:59:16', NULL, '2025-05-15 08:59:16'),
(32, 11, 'account_created', 'Your account has been created. Username: huggy, Password: oFQYrymv(U', 11, 'user', false, false, 'email', '2025-05-15 11:51:56', NULL, '2025-05-15 11:51:56');

-- 3.11 report
INSERT INTO "report" ("report_id", "report_name", "report_type", "generated_by", "file_path", "parameters", "generated_at", "created_at", "updated_at") VALUES
(1, 'Gshqhavahs', 'user', 1, 'reports/gshqhavahs_20250513_105929.pdf', '{"date_start":"2025-04-13","date_end":"2025-05-13","include_appointments":0,"include_services":0,"include_new_users":1,"include_user_roles":1,"include_status":0,"include_dentists":0}', '2025-05-13 07:59:29', '2025-05-13 07:59:29', '2025-05-13 07:59:29');

-- 3.12 audittrail
INSERT INTO "audittrail" ("audit_id", "user_id", "action_performed", "table_affected", "timestamp", "ip_address", "details") VALUES
(1, 1, 'EMAIL_VERIFICATION', 'user', '2025-05-08 03:05:16', '111.125.106.204', 'Email verified successfully'),
(2, 1, 'LOGIN', 'user', '2025-05-08 03:05:22', '111.125.106.204', 'User logged in successfully'),
(3, 1, 'LOGOUT', 'user', '2025-05-08 03:15:21', '111.125.106.204', 'User logged out'),
(4, 1, 'LOGIN', 'user', '2025-05-08 03:21:04', '111.125.106.204', 'User logged in successfully'),
(5, 1, 'LOGOUT', 'user', '2025-05-08 03:21:17', '111.125.106.204', 'User logged out'),
(6, 8, 'EMAIL_VERIFICATION', 'user', '2025-05-08 03:22:26', '111.125.106.204', 'Email verified successfully'),
(7, 8, 'LOGIN', 'user', '2025-05-08 03:22:37', '111.125.106.204', 'User logged in successfully'),
(8, 9, 'EMAIL_VERIFICATION', 'user', '2025-05-08 03:23:33', '111.125.106.204', 'Email verified successfully'),
(9, 9, 'LOGIN', 'user', '2025-05-08 03:23:44', '111.125.106.204', 'User logged in successfully'),
(10, 9, 'LOGOUT', 'user', '2025-05-08 03:25:33', '111.125.106.204', 'User logged out'),
(11, 9, 'LOGIN', 'user', '2025-05-08 03:25:39', '111.125.106.204', 'User logged in successfully'),
(12, 1, 'LOGIN', 'user', '2025-05-08 03:26:25', '111.125.106.204', 'User logged in successfully'),
(13, 8, 'INSERT', 'appointment', '2025-05-08 03:31:06', NULL, 'Created new appointment request for Consultation Fee with Dr. Jose Kim'),
(14, 8, 'LOGIN', 'user', '2025-05-08 03:32:47', '131.226.99.196', 'User logged in successfully'),
(15, 1, 'VIEW', 'payment', '2025-05-08 04:37:57', '111.125.106.204', 'Viewed payment details for payment ID: 9'),
(16, 1, 'VIEW', 'payment', '2025-05-08 04:40:16', '111.125.106.204', 'Viewed payment details for payment ID: 9'),
(17, 1, 'Manual payment confirmation', 'payment', '2025-05-08 04:56:37', '111.125.106.204', 'Payment ID: 9 manually confirmed by admin'),
(18, 8, 'INSERT', 'appointment', '2025-05-08 05:26:19', NULL, 'Created new appointment request for Complete Denture (Porcelain China) with Dr. Jose Kim'),
(19, 1, 'Manual payment confirmation', 'payment', '2025-05-08 05:26:33', '111.125.106.204', 'Payment ID: 10 manually confirmed by admin'),
(20, 8, 'LOGOUT', 'user', '2025-05-08 05:27:13', '111.125.106.204', 'User logged out'),
(21, 8, 'LOGOUT', 'user', '2025-05-08 05:28:59', '131.226.99.116', 'User logged out'),
(22, 1, 'LOGOUT', 'user', '2025-05-08 05:50:19', '111.125.106.204', 'User logged out'),
(23, 8, 'LOGIN', 'user', '2025-05-08 06:01:01', '111.125.106.204', 'User logged in successfully'),
(24, 8, 'LOGOUT', 'user', '2025-05-08 06:01:05', '111.125.106.204', 'User logged out'),
(25, 1, 'LOGIN', 'user', '2025-05-08 06:21:04', '111.125.106.204', 'User logged in successfully'),
(26, 1, 'LOGOUT', 'user', '2025-05-08 06:23:43', '111.125.106.204', 'User logged out'),
(27, 8, 'LOGIN', 'user', '2025-05-08 06:23:51', '111.125.106.204', 'User logged in successfully'),
(28, 8, 'LOGOUT', 'user', '2025-05-08 06:24:32', '111.125.106.204', 'User logged out'),
(29, 9, 'LOGIN', 'user', '2025-05-08 06:24:36', '111.125.106.204', 'User logged in successfully'),
(30, 8, 'LOGIN', 'user', '2025-05-08 07:09:12', '111.125.106.204', 'User logged in successfully'),
(31, 8, 'INSERT', 'appointment', '2025-05-08 07:09:26', NULL, 'Created new appointment request for Composite Restoration with Dr. Jose Kim'),
(32, 1, 'LOGIN', 'user', '2025-05-08 07:12:22', '111.125.106.204', 'User logged in successfully'),
(33, 1, 'Manual payment confirmation', 'payment', '2025-05-08 07:12:26', '111.125.106.204', 'Payment ID: 12 manually confirmed by admin'),
(34, 1, 'LOGIN', 'user', '2025-05-09 16:54:46', '180.232.85.242', 'User logged in successfully'),
(35, 1, 'Manual payment confirmation', 'payment', '2025-05-09 16:55:15', '180.232.85.242', 'Payment ID: 11 manually confirmed by admin'),
(36, 1, 'LOGOUT', 'user', '2025-05-09 16:55:42', '180.232.85.242', 'User logged out'),
(37, 1, 'LOGIN', 'user', '2025-05-13 07:58:14', '111.125.106.204', 'User logged in successfully'),
(38, 1, 'GENERATE', 'report', '2025-05-13 07:59:29', NULL, 'Generated report: Gshqhavahs'),
(39, 1, 'LOGOUT', 'user', '2025-05-13 08:16:12', '111.125.106.204', 'User logged out'),
(40, 1, 'LOGIN', 'user', '2025-05-13 08:16:33', '111.125.106.204', 'User logged in successfully'),
(41, 1, 'LOGOUT', 'user', '2025-05-13 08:16:40', '111.125.106.204', 'User logged out'),
(42, 8, 'LOGIN', 'user', '2025-05-13 08:16:47', '111.125.106.204', 'User logged in successfully'),
(43, 1, 'LOGIN', 'user', '2025-05-15 08:08:05', '111.125.106.204', 'User logged in successfully'),
(44, 1, 'EXPORT', 'audittrail', '2025-05-15 08:11:36', '111.125.106.204', 'Exported audit trail data'),
(45, 1, 'INSERT', 'user', '2025-05-15 08:15:13', NULL, 'Added new user: kijun with role ID: 1'),
(46, 1, 'LOGOUT', 'user', '2025-05-15 08:15:24', '111.125.106.204', 'User logged out'),
(47, 10, 'EMAIL_VERIFICATION', 'user', '2025-05-15 08:16:08', '111.125.106.204', 'Email verified successfully'),
(48, 10, 'LOGIN', 'user', '2025-05-15 08:17:24', '111.125.106.204', 'User logged in successfully'),
(49, 10, 'DELETE', 'user', '2025-05-15 08:52:10', NULL, 'Deleted user: dentist'),
(50, 10, 'DELETE', 'user', '2025-05-15 08:58:44', NULL, 'Deleted user: dentist'),
(51, 10, 'RECOVER', 'user', '2025-05-15 08:58:51', NULL, 'Recovered user: dentist'),
(52, 10, 'DELETE', 'user', '2025-05-15 08:59:11', NULL, 'Deleted user: admin'),
(53, 10, 'RECOVER', 'user', '2025-05-15 08:59:16', NULL, 'Recovered user: admin'),
(54, 10, 'INSERT', 'user', '2025-05-15 11:51:56', NULL, 'Added new user: huggy with role ID: 1');

-- 3.13 user_logs
INSERT INTO "user_logs" ("log_id", "action_type", "user_id", "first_name", "middle_name", "last_name", "suffix", "username", "email", "phone_number", "address", "date_of_birth", "role_id", "is_deleted", "log_timestamp") VALUES
(1, 'DELETE', 3, 'John', 'Robert', 'Cruz', 'DMD', 'dentist', 'dentist@jrcdentalcare.com', '09187654321', 'JRC Dental Care, Bonga Mayos, Bustos', '1985-05-15', 2, true, '2025-05-15 08:52:10'),
(2, 'DELETE', 3, 'John', 'Robert', 'Cruz', 'DMD', 'dentist', 'dentist@jrcdentalcare.com', '09187654321', 'JRC Dental Care, Bonga Mayos, Bustos', '1985-05-15', 2, true, '2025-05-15 08:58:44'),
(3, '', 3, 'John', 'Robert', 'Cruz', 'DMD', 'dentist', 'dentist@jrcdentalcare.com', '09187654321', 'JRC Dental Care, Bonga Mayos, Bustos', '1985-05-15', 2, false, '2025-05-15 08:58:51'),
(4, 'DELETE', 2, 'Admin', NULL, 'User', NULL, 'admin', 'admin@jrcdentalcare.com', '09123456789', 'JRC Dental Care, Bonga Mayos, Bustos', '1990-01-01', 1, true, '2025-05-15 08:59:11'),
(5, '', 2, 'Admin', NULL, 'User', NULL, 'admin', 'admin@jrcdentalcare.com', '09123456789', 'JRC Dental Care, Bonga Mayos, Bustos', '1990-01-01', 1, false, '2025-05-15 08:59:16'),
(6, 'INSERT', 11, 'Lee', 'jung', 'DG', '', 'huggy', 'akirodeguzman267@gmail.com', '09979788696', '112 purok 3', '2025-05-22', 1, false, '2025-05-15 11:51:56');

-- ==============================================================================
-- 4. SYNCHRONIZE ID SEQUENCES
-- (Crucial for Postgres/Supabase so subsequent INSERTs do not collide with existing IDs)
-- ==============================================================================
SELECT setval(pg_get_serial_sequence('"userrole"', 'role_id'), coalesce((SELECT MAX("role_id") FROM "userrole"), 1));
SELECT setval(pg_get_serial_sequence('"user"', 'user_id'), coalesce((SELECT MAX("user_id") FROM "user"), 1));
SELECT setval(pg_get_serial_sequence('"service"', 'service_id'), coalesce((SELECT MAX("service_id") FROM "service"), 1));
SELECT setval(pg_get_serial_sequence('"apiintegration"', 'api_id'), coalesce((SELECT MAX("api_id") FROM "apiintegration"), 1));
SELECT setval(pg_get_serial_sequence('"clinic_settings"', 'setting_id'), coalesce((SELECT MAX("setting_id") FROM "clinic_settings"), 1));
SELECT setval(pg_get_serial_sequence('"appointment"', 'appointment_id'), coalesce((SELECT MAX("appointment_id") FROM "appointment"), 1));
SELECT setval(pg_get_serial_sequence('"payment"', 'payment_id'), coalesce((SELECT MAX("payment_id") FROM "payment"), 1));
SELECT setval(pg_get_serial_sequence('"dentist_info"', 'dentist_id'), coalesce((SELECT MAX("dentist_id") FROM "dentist_info"), 1));
SELECT setval(pg_get_serial_sequence('"patient_info"', 'patient_id'), coalesce((SELECT MAX("patient_id") FROM "patient_info"), 1));
SELECT setval(pg_get_serial_sequence('"notification"', 'notification_id'), coalesce((SELECT MAX("notification_id") FROM "notification"), 1));
SELECT setval(pg_get_serial_sequence('"report"', 'report_id'), coalesce((SELECT MAX("report_id") FROM "report"), 1));
SELECT setval(pg_get_serial_sequence('"audittrail"', 'audit_id'), coalesce((SELECT MAX("audit_id") FROM "audittrail"), 1));
SELECT setval(pg_get_serial_sequence('"user_logs"', 'log_id'), coalesce((SELECT MAX("log_id") FROM "user_logs"), 1));
SELECT setval(pg_get_serial_sequence('"dentist_schedule"', 'schedule_id'), coalesce((SELECT MAX("schedule_id") FROM "dentist_schedule"), 1));
SELECT setval(pg_get_serial_sequence('"dentist_break"', 'break_id'), coalesce((SELECT MAX("break_id") FROM "dentist_break"), 1));
SELECT setval(pg_get_serial_sequence('"calendar_settings"', 'setting_id'), coalesce((SELECT MAX("setting_id") FROM "calendar_settings"), 1));
SELECT setval(pg_get_serial_sequence('"notification_settings"', 'setting_id'), coalesce((SELECT MAX("setting_id") FROM "notification_settings"), 1));
SELECT setval(pg_get_serial_sequence('"treatment"', 'treatment_id'), coalesce((SELECT MAX("treatment_id") FROM "treatment"), 1));
SELECT setval(pg_get_serial_sequence('"billing"', 'billing_id'), coalesce((SELECT MAX("billing_id") FROM "billing"), 1));
SELECT setval(pg_get_serial_sequence('"password_reset"', 'reset_id'), coalesce((SELECT MAX("reset_id") FROM "password_reset"), 1));
