# Dental Clinic Management and Appointment System

A comprehensive web-based dental practice management platform engineered to streamline clinical scheduling, patient records, payment workflows, and administrative operations.

---

## Academic Context

This project was originally designed and developed during my second year of college as a primary capstone project for the **Database Systems** course. The objective was to architect a robust, fully-normalized relational database system and build a complete web application utilizing secure transaction handling, relational integrity, role-based security, and automated clinical reporting.

---

## System Overview

The Dental Clinic Management System provides end-to-end operational automation for a multi-disciplinary dental clinic. It replaces manual scheduling and paper-based records with an integrated digital workflow supporting four distinct user roles:

1. **Patients:** Self-service registration, real-time appointment booking based on dentist availability, payment tracking, treatment history, and account profile management.
2. **Dentists:** Clinical schedule management, daily and weekly appointment calendars, patient dental charts, treatment documentation, and break time configuration.
3. **Staff / Receptionists:** Front-desk booking assistance, patient check-in management, and appointment coordination.
4. **Administrators:** Comprehensive system oversight, user role administration, service catalog pricing, financial reporting, API integration management, and immutable audit log inspection.

---

## Technical Architecture

### Core Technologies
- **Backend Environment:** PHP (Version 8.0+) utilizing PDO (PHP Data Objects) for database abstraction and query execution.
- **Database Layer:** Relational architecture designed for MySQL and fully adapted for PostgreSQL / Supabase with schema migrations and index optimization.
- **Frontend Architecture:** Semantic HTML5, Vanilla JavaScript, Bootstrap 5, and responsive custom CSS.
- **Document Generation:** FPDF and TCPDF libraries for generating exportable PDF invoices, clinical treatment summaries, and revenue breakdown reports.

### Third-Party Service Integrations
- **Email Dispatching:** PHPMailer with SMTP authentication for transactional notifications, account verification, and password recovery.
- **Payment Processing:** PayMongo Payment Intent API integration for digital credit/debit and e-wallet checkout flows.
- **Calendar Synchronization:** Google Calendar API client for synchronizing approved appointments to external calendars.

---

## Key Modules and Capabilities

### 1. Appointment Scheduling Engine
- Dynamic slot allocation based on operating hours, procedure duration, and dentist availability.
- Conflict prevention avoiding overlapping bookings and respecting dentist scheduled break times.
- Status lifecycle tracking: Pending, Confirmed, Completed, and Cancelled.

### 2. Clinical Treatment Documentation
- Longitudinal patient treatment tracking with procedure notes, attending dentist records, and prescription details.
- Pre-configured service catalog with standardized procedure durations and pricing.

### 3. Payment Processing and Invoicing
- Multi-channel payment recording (Cash and Online via PayMongo gateway).
- Real-time payment verification and status webhooks/checks.
- Printable and downloadable PDF receipts with transaction breakdowns.

### 4. Role-Based Access Control (RBAC) and Security
- Granular session-based permission enforcement on every administrative and clinical endpoint.
- Cryptographic password hashing utilizing PHP native `password_hash()` with standard BCRYPT salt routines.
- Parameterized SQL prepared statements across all database transactions to protect against SQL Injection.
- Complete separation of application secrets and credentials using environment variables (`.env`).

### 5. Audit Logging and Observability
- Immutable system audit trail capturing user actions, administrative role shifts, and critical data modifications with IP and timestamp metadata.
- Automated notification queue dispatching alerts to patients and clinic personnel.

---

## Database Design and Schema

The relational schema implements Third Normal Form (3NF) principles to eliminate data redundancy and preserve referential integrity:

| Table | Description |
| :--- | :--- |
| `userrole` | System roles (Admin, Dentist, Staff, Patient) |
| `user` | Core user entities, credentials, profile details, and verification status |
| `service` | Dental procedure catalog, duration guidelines, and pricing |
| `appointment` | Patient bookings, dentist assignments, procedure types, and status |
| `treatment` | Clinical session notes, completed procedures, and treatment dates |
| `payment` | Financial transactions, gateway identifiers, and payment states |
| `notification` | Transactional alerts and message delivery queue |
| `notification_settings` | Per-user preferences for notification channels |
| `calendar_settings` | External Google Calendar synchronization parameters |
| `dentist_schedule` | Weekly working day shifts and operational hours per dentist |
| `dentist_break` | Configured break intervals restricting appointment slots |
| `clinic_settings` | Global clinic metadata, contact info, and operational parameters |
| `apiintegration` | Configured third-party service credentials and connectivity status |
| `user_logs` | Dedicated audit trail capturing historical user profile alterations |
| `password_reset` | Time-sensitive cryptographic tokens for secure credential resets |

Complete table definitions, relationship constraints, and sample seed records are provided in `supabase_migration.sql`.

---

## Installation and Setup

### Prerequisites
- PHP 8.0 or higher
- PostgreSQL 14+ (or Supabase instance) / MySQL 8.0+
- Composer package manager
- Apache / Nginx or local environment (e.g., XAMPP, Laragon)

### Setup Instructions

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/afighi/dental-clinic.git
   cd dental-clinic
   ```

2. **Install Composer Dependencies:**
   ```bash
   composer install
   ```

3. **Configure Environment Variables:**
   Copy the example environment file and configure your credentials:
   ```bash
   cp .env.example .env
   ```
   Open `.env` and fill in your database and SMTP connection details:
   ```ini
   DB_DRIVER=pgsql
   DB_HOST=your_host_here
   DB_PORT=5432
   DB_NAME=your_db_name
   DB_USER=your_db_username
   DB_PASS=your_db_password

   SMTP_USER=your_email@example.com
   SMTP_PASS=your_smtp_app_password
   ```

4. **Initialize Database Schema:**
   Execute the migration script against your target database:
   - For PostgreSQL / Supabase: Import `supabase_migration.sql` through the SQL editor or command-line client (`psql`).

5. **Serve the Application:**
   Deploy the folder to your web server root (e.g., `C:/xampp/htdocs/dental-clinic`) or launch via the PHP built-in server:
   ```bash
   php -S localhost:8000
   ```

6. **Access Default Accounts:**
   The initial migration provisions administrative and clinical accounts for testing purposes:
   - **Administrator:** `admin` / Password set in migration seed
   - **Dentist:** `dentist` / Password set in migration seed

---

## Repository Structure

```text
├── config/
│   └── database.php                # Database connection factory & environment loader
├── components/                     # Reusable interface components and templates
├── includes/                       # Shared navigation, headers, footers, and auth guards
├── fpdf/                           # PDF rendering utilities
├── ajax/                           # Asynchronous AJAX request endpoints
├── supabase_migration.sql          # Relational database schema and seed data
├── .env.example                    # Template configuration for environment variables
├── .gitignore                      # Git exclusion rules for secrets, vendor, and logs
├── composer.json                   # PHP dependency definitions
├── README.md                       # System technical documentation
└── [Module Pages]                  # Functional clinical, billing, and management controllers
```

---

## Security Notice

All production deployments should ensure:
- The `.env` file is protected and never committed to source version control.
- HTTPS/TLS is enforced across all client connections.
- Secure, randomized application passwords and database credentials are used.

---

## License

This project is released under the MIT License. Developed for educational and academic demonstration purposes.
