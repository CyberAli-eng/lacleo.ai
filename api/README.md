# LaCleo AI Search API

**Version:** 1.0.0 (Production Ready)  
**Framework:** Laravel 11  
**PHP:** 8.2+

## Overview

The LaCleo AI API is the core engine behind the platform, powering the unified search, AI-driven filter generation, data enrichment, and billing services. It acts as the bridge between the frontend application, the Elasticsearch cluster, and various 3rd party services (OpenAI/Ollama, Stripe, LinkedIn Enrichment).

---

## 🚀 Requirement Checklist

Ensure your production environment meets these requirements:

| Component | Requirement | Notes |
| :--- | :--- | :--- |
| **PHP** | 8.2 or higher | Extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `curl` |
| **Database** | MySQL 8.0+ | Or MariaDB 10.3+. Connection via `DB_CONNECTION=mysql` |
| **Search** | Elasticsearch 8.x | Authentication via username/password required for production. |
| **Cache/Queue** | Redis 6.0+ | Preferred driver for production queues and sessions. |
| **AI LLM** | Ollama (Self-hosted) | Requires TinyLlama or similar model loaded. |
| **Web Server** | Nginx / Apache | Nginx recommended with PHP-FPM. |

---

## ⚙️ Configuration (.env)

Production environments **must** populate these variables.

### Admin Access (New!)
The specific google accounts that have "super admin" privileges (e.g. granting free credits).
```env
ADMIN_EMAILS=admin@lacleo.ai,shaizqurashi12345@gmail.com
```

### AI Search Configuration
```env
# Point to your internal Ollama instance or OpenAI API
AI_SERVICE_DRIVER=ollama
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_CHAT_MODEL=tinyllama
```

### Core Services
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.lacleo.ai

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=lacleo_prod
DB_USERNAME=forge
DB_PASSWORD=secret

ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=changeme
ELASTIC_COMPANY_INDEX=production_companies_v1
ELASTIC_CONTACT_INDEX=production_contacts_v1

# Security & CORS (Critical for Auth)
SANCTUM_STATEFUL_DOMAINS=app.lacleo.ai
SESSION_DOMAIN=.lacleo.ai
CORS_ALLOWED_ORIGINS=https://app.lacleo.ai,https://accounts.lacleo.ai
```

---

## 🛠️ Installation & Deployment

### 1. Initial Setup
```bash
# Install dependencies
composer install --optimize-autoloader --no-dev

# Generate encryption key
php artisan key:generate

# Storage linking & permissions
php artisan storage:link
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 2. Database & Search Indexing
```bash
# Run database migrations
php artisan migrate --force

# Seed database with initial meta-data (Filters, Plans, etc.)
php artisan db:seed --force
```

### 3. Production Optimizations (Run on every deploy)
```bash
# Optimize Configuration Loading
php artisan config:cache

# Optimize Route Loading
php artisan route:cache

# Optimize View Loading
php artisan view:cache
```

### 4. Queue Workers
The API relies on background workers for `Export CSV` and `Enrichment` tasks.
Use **Supervisor** to keep this running:
```conf
[program:lacleo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/api/storage/logs/worker.log
```

---

## 🔍 Admin & Debugging Commands

The codebase includes several utility scripts and commands for debugging production issues.

### Search Debugging
If search results look wrong, verify the Elasticsearch connection and mapping:
```bash
# Check raw interaction with specifically 'contacts' index
php scripts/adhoc/debug_searchability.php
```

### Testing Admin Configuration
Verify the `ADMIN_EMAILS` setting is correctly loaded:
```bash
php scripts/adhoc/test_admin_env.php
```

### Force Re-index (Careful!)
If you need to completely rebuild the search index from SQL:
```bash
# CAUTION: High Load Operation
php artisan scout:import "App\Models\Contact"
php artisan scout:import "App\Models\Company"
```

---

## 🛡️ Security Best Practices

1.  **PII Handling**: The `SanitizesPII` trait is applied to logging to prevent emails/phones from leaking into logs.
2.  **CORS**: Ensure `CORS_ALLOWED_ORIGINS` strictly matches your frontend URLs.
3.  **Sanctum**: `SESSION_DOMAIN` must match the cookie domain (e.g., `.lacleo.ai`) for cross-subdomain auth to work.
