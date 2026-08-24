# Mfano Bora Resources Portal - Backend & Admin Documentation

The Mfano Bora Resources Portal is a lightweight, backend API and administration system built to manage and serve corporate, educational, and operational digital resources across 10 core organizational categories. It utilizes native PHP with PDO, PostgreSQL, and a zero-build vanilla JavaScript/CSS dashboard.

---

## System Overview & Architecture

The system serves public clients via REST endpoints and provides secure administrative CRUD capabilities for content managers.

* **Data Layer:** PostgreSQL database utilizing indexing for full-text search (`to_tsvector`) and foreign key relations (`categories` $\rightarrow$ `sub_categories` $\rightarrow$ `resources`).


* **API Layer:** PHP scripts processing standard HTTP methods (GET, POST, PUT, DELETE) and returning structured JSON payloads.


* **Admin Dashboard:** A standalone web portal (`admin/index.html`) using HTML5, CSS, and Vanilla JS.


* **Authentication:** Stateless API authentication using a shared secret key passed via the `X-Api-Key` HTTP header.



---

## Directory Structure

```text
mfano-resources/
├── config/                  # Server & database configuration files
│   ├── config.example.php   # Configuration template
│   ├── config.php           # Active environment settings (git-ignored)
│   └── db.php               # Shared PDO database connection handler
├── database/                # Relational data definitions and seed data
│   ├── schema.sql           # Database structure & indexes
│   └── seed.sql             # Default categories, subcategories & resources
├── api/                     # Public REST API endpoints
│   ├── categories.php       # Retrieve category/subcategory tree
│   ├── resources.php        # Resource listing, filtering & search
│   ├── health.php           # Server uptime check endpoint
│   └── admin/               # Authenticated administrative endpoints
│       ├── categories.php   # Category management
│       ├── subcategories.php# Sub-category management
│       └── resources.php    # Resource CRUD operations
├── includes/                # Common helper scripts
│   ├── auth.php             # X-Api-Key authorization guard
│   └── helpers.php          # JSON responses & CORS functions
└── admin/                   # Web-based Admin Dashboard
    ├── index.html           # Admin dashboard markup
    ├── css/admin.css        # Stylesheet
    └── js/admin.js          # Admin API integration script

```

---

## Database Creation & Setup

The database requires PostgreSQL with the `uuid-ossp` extension enabled.

### 1. Create Database

Open your terminal or PostgreSQL manager (e.g., DBeaver or `psql`) and execute:

```sql
CREATE DATABASE mfano_bora_db;

```

### 2. Execute Database Schema

Run `database/schema.sql` against your target database to construct the table hierarchy and search indexes:

```bash
psql -U postgres -d mfano_bora_db -f database/schema.sql

```

### 3. Deploy Seed Data

Deploy `database/seed.sql` to populate the 10 core categories, sub-categories, and default resource records:

```bash
psql -U postgres -d mfano_bora_db -f database/seed.sql

```

---

## Backend PHP Configuration

### 1. Environment Configuration

Create the runtime configuration file by copying the example file:

```bash
cp config/config.example.php config/config.php

```

### 2. Configure Settings

Update `config/config.php` with your local database credentials and domain origins:

```php
return [
    'db' => [
        'host'     => 'localhost',
        'port'     => '5432',
        'dbname'   => 'mfano_bora_db',
        'user'     => 'postgres',
        'password' => 'your_db_password',
    ],
    'admin_api_key'  => 'YOUR_SECURE_GENERATED_KEY',
    'allowed_origin' => 'https://mfanobora.com', // Use '*' for local testing
];

```

### 3. Generate Secret Admin API Key

Generate a secure random 48-character hex string using PHP CLI:

```bash
php -r "echo bin2hex(random_bytes(24));"

```

Assign this key string to `'admin_api_key'` in `config/config.php`.

---

## Running & Deploying the Backend Server

### Prerequisites

* PHP 8.0 or higher with `pdo_pgsql` enabled.


* PostgreSQL service running.



### Running Locally (PHP Built-in Server)

To start a lightweight local development server from the project root:

```bash
php -S localhost:8000

```

* **Public API:** `http://localhost:8000/api/resources.php`

* **Admin Dashboard:** `http://localhost:8000/admin/index.html`


### Production Server (Apache / Nginx / XAMPP)

1. Ensure the `pdo_pgsql` extension is enabled in your `php.ini` file.


2. Upload the `mfano-resources` directory to your web server document root (e.g., `/var/www/html/` or `/opt/lampp/htdocs/`).


3. Verify directory access permissions so Apache/Nginx can execute the PHP scripts.



---

## Integrating Backend to Main Mfano Bora Root

To attach this portal to the primary Mfano Bora website root:

### Option A: Subdirectory Deployment

Move the entire compiled folder into a subdirectory within the main website root folder:

```text
[Main Website Root]/
├── index.html
├── assets/
└── resources-portal/   <-- Compiled backend & admin folder

```

### Option B: API Path Configuration

If deploying API endpoints to a custom subfolder path (e.g., `/resources-portal/api`), open `admin/js/admin.js` and update the base endpoint constant:

```javascript
// Update API_BASE path relative to site root
const API_BASE = '/resources-portal/api'; 

```

### Option C: Reverse Proxy (Nginx Configuration)

For unified domain setups, route `/api/` and `/admin/` requests to the resource portal server instance:

```nginx
location /api/ {
    proxy_pass http://127.0.0.1:8000/api/;
    proxy_set_header Host $host;
}

location /admin/ {
    proxy_pass http://127.0.0.1:8000/admin/;
    proxy_set_header Host $host;
}

```

---

## API Endpoints Reference

| Category | Method | Endpoint | Description | Headers / Auth |
| --- | --- | --- | --- | --- |
| **Public** | `GET` | `/api/health.php` | System status check

 | None |
| **Public** | `GET` | `/api/categories.php` | Full category/subcategory tree

 | None |
| **Public** | `GET` | `/api/resources.php` | Filter/search published resources

 | None |
| **Public** | `POST` | `/api/resources.php?id={id}&action=download` | Increment resource download count

 | None |
| **Admin** | `POST` | `/api/admin/resources.php` | Create new resource

 | `X-Api-Key`<br> |
| **Admin** | `PUT` | `/api/admin/resources.php?id={id}` | Update existing resource

 | `X-Api-Key`<br> |
| **Admin** | `DELETE` | `/api/admin/resources.php?id={id}` | Remove resource

 | `X-Api-Key`<br> |