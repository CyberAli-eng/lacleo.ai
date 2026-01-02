# Accounts Service - lacleo.ai

## Overview

The `accounts` service handles user authentication, registration, and account management for lacleo.ai using Laravel Jetstream.

## Tech Stack

- **Framework**: Laravel 11
- **Authentication**: Laravel Jetstream + Sanctum
- **Frontend**: Livewire (for auth pages)
- **Database**: MySQL
- **Session**: Redis

## Key Features

### 1. Authentication
- Email/password registration
- Login with remember me
- Password reset
- Email verification
- Two-factor authentication (optional)

### 2. Account Management
- Profile updates
- Password changes
- API token management
- Account deletion

### 3. Team Management (if enabled)
- Create teams
- Invite team members
- Role-based permissions

## Project Structure

```
accounts/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   │   └── User.php
│   └── Providers/
├── config/
│   ├── jetstream.php
│   ├── sanctum.php
│   └── fortify.php
├── database/
│   └── migrations/
├── resources/
│   └── views/          # Jetstream views
└── routes/
    └── web.php
```

## Environment Variables

### Required

```env
# Application
APP_NAME=lacleo.ai
APP_ENV=production
APP_DEBUG=false
APP_URL=https://accounts.lacleo.test

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lacleo_accounts
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Sanctum
SANCTUM_STATEFUL_DOMAINS=local-app.lacleo.test,local-accounts.lacleo.test
SESSION_DOMAIN=.lacleo.test

# CORS
CORS_ALLOWED_ORIGINS=https://local-app.lacleo.test

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@lacleo.ai
MAIL_FROM_NAME="${APP_NAME}"
```

## Installation

```bash
# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build
```

## Development

```bash
# Start development server
php artisan serve --host=local-accounts.lacleo.test --port=8001

# Watch assets
npm run dev

# Run tests
php artisan test
```

## Authentication Flow

1. User registers/logs in on `accounts.lacleo.test`
2. Sanctum creates session and CSRF token
3. Frontend app (`app.lacleo.test`) makes authenticated requests to API
4. API validates Sanctum token from shared session

## CORS Configuration

The accounts service must allow requests from the app frontend:

```php
// config/cors.php
'allowed_origins' => [
    'https://local-app.lacleo.test',
    'https://app.lacleo.ai',
],
'supports_credentials' => true,
```

## Security

- CSRF protection on all state-changing requests
- Session-based authentication
- Secure cookie settings (httpOnly, secure, sameSite)
- Password hashing with bcrypt
- Rate limiting on login attempts

## Troubleshooting

### CSRF Token Mismatch
```bash
# Clear sessions
php artisan session:flush

# Regenerate app key
php artisan key:generate

# Clear config cache
php artisan config:clear
```

### Session Not Persisting
- Check `SESSION_DOMAIN` is set to `.lacleo.test`
- Verify `SANCTUM_STATEFUL_DOMAINS` includes app domain
- Ensure cookies are being sent with `credentials: 'include'`

## License

Proprietary - All rights reserved