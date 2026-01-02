# API Service - lacleo.ai

## Overview

The `api` service is the core backend for lacleo.ai, providing search, filtering, enrichment, and billing functionality for B2B contact and company data.

## Tech Stack

- **Framework**: Laravel 11
- **Authentication**: Laravel Sanctum
- **Search Engine**: Elasticsearch 8.x
- **Payment Processing**: Stripe
- **Queue**: Redis
- **Cache**: Redis

## Key Features

### 1. Search & Filtering
- Unified search across companies and contacts
- 23 filters including technologies, funding, seniority, departments
- Apollo-style aggregations with counts
- Advanced DSL-based filtering

### 2. Contact Enrichment
- Email finding and verification
- Phone number discovery
- LinkedIn profile enrichment
- Company data enrichment

### 3. Export & Reveal
- CSV export with credit deduction
- Email/phone reveal functionality
- Bulk operations support

### 4. AI-Powered Search
- Natural language query translation
- Automatic filter generation
- Entity detection (company vs contact)

### 5. Billing & Credits
- Stripe integration for payments
- Credit-based usage tracking
- Subscription management
- Usage analytics

## Project Structure

```
api/
├── app/
│   ├── Elasticsearch/        # ES index management
│   ├── Filters/              # Filter system
│   │   ├── FilterManager.php
│   │   ├── Handlers/         # Filter type handlers
│   │   └── DslValidator.php
│   ├── Http/
│   │   ├── Controllers/Api/v1/
│   │   ├── Middleware/
│   │   └── Traits/           # Reusable traits
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic
│   │   ├── SearchService.php
│   │   ├── FilterRegistry.php
│   │   ├── ContactEnrichmentService.php
│   │   └── BillingService.php
│   └── Jobs/                 # Queue jobs
├── config/                   # Configuration files
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php              # API routes
└── tests/                   # Test suite
```

## Environment Variables

### Required

```env
# Application
APP_NAME=lacleo.ai
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.lacleo.test

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lacleo_api
DB_USERNAME=root
DB_PASSWORD=

# Elasticsearch
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=
ELASTICSEARCH_PREFIX=stage_lacleo
ELASTIC_COMPANY_INDEX=company
ELASTIC_CONTACT_INDEX=contact

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Stripe
STRIPE_KEY=sk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Sanctum
SANCTUM_STATEFUL_DOMAINS=local-app.lacleo.test,local-accounts.lacleo.test
SESSION_DOMAIN=.lacleo.test

# CORS
CORS_ALLOWED_ORIGINS=https://local-app.lacleo.test,https://local-accounts.lacleo.test
```

### Optional

```env
# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
```

## Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Development

```bash
# Start development server
php artisan serve --host=local-api.lacleo.test --port=8000

# Run queue worker
php artisan queue:work

# Run tests
php artisan test

# Code formatting
./vendor/bin/php-cs-fixer fix
```

## API Endpoints

### Search
- `POST /api/v1/search` - Unified search
- `GET /api/v1/filters` - List available filters
- `GET /api/v1/filters/{filter}/values` - Get filter values

### Enrichment
- `POST /api/v1/enrich/contact` - Enrich contact data
- `GET /api/v1/enrich/{requestId}` - Check enrichment status

### Export
- `POST /api/v1/export/preview` - Preview export
- `POST /api/v1/export` - Create export job
- `GET /api/v1/export/{requestId}/download` - Download export

### Billing
- `GET /api/v1/billing/usage` - Get credit usage
- `POST /api/v1/billing/purchase` - Purchase credits
- `POST /api/v1/billing/subscribe` - Create subscription

### AI Search
- `POST /api/v1/ai/translate` - Translate natural language query

## Filter System

The filter system is the core of the search functionality:

### Available Filters (23 total)

**Company Filters**:
- company_name, company_domain, business_category
- keywords, technologies (comma-separated)
- employee_count, annual_revenue, founded_year (ranges)
- total_funding (range), has_funding (boolean)
- countries, states, cities

**Contact Filters**:
- first_name, last_name, full_name
- job_title, seniority, departments
- contact_country
- work_email_exists, mobile_number_exists, direct_number_exists

### Filter Configuration

Filters are defined in `app/Services/FilterRegistry.php` with:
- Field mappings to Elasticsearch
- Input types (text, multi_select, range, toggle)
- Aggregation settings
- Preloaded values (for seniority, departments)

## Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage

# Test specific filter
php scripts/adhoc/test_specific_filters.php
```

## Troubleshooting

### Elasticsearch Connection Issues
```bash
# Check ES status
curl -X GET "localhost:9200/_cluster/health?pretty"

# Verify indexes
curl -X GET "localhost:9200/_cat/indices?v"
```

### Cache Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Queue Issues
```bash
# Restart queue workers
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## Security

- All routes require Sanctum authentication (except webhooks)
- CSRF protection enabled for stateful requests
- PII sanitization in logs via `SanitizesPII` trait
- Rate limiting on API endpoints
- Stripe webhook signature verification

## Performance

- Redis caching for search results (60s for public queries)
- Elasticsearch query optimization
- Pagination limits (max 100 per page, max depth 10,000)
- Queue processing for heavy operations

## Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update documentation
4. Run code formatter before committing

## License

Proprietary - All rights reserved
