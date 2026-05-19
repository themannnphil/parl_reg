# ParlReg — Parliament Event Registration Suite

**Backend API** | PHP 8.x + MySQL 8.x | Raw MVC, no framework

---

## Quick Start

### 1. Requirements

| Tool | Version |
|------|---------|
| PHP  | 8.1+    |
| MySQL | 8.0+   |
| Composer | any |
| Apache/Nginx | any |

### 2. Clone & Install

```bash
# Install PHPMailer
composer require phpmailer/phpmailer

# Copy config
cp config/config.php config/config.local.php  # edit with your DB creds
```

### 3. Create Database

```sql
CREATE DATABASE parlreg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'parlreg_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON parlreg.* TO 'parlreg_user'@'localhost';
FLUSH PRIVILEGES;
```

Run the migration:
```bash
mysql -u parlreg_user -p parlreg < database/migrations/001_initial_schema.sql
```

### 4. Create Storage Directories

```bash
mkdir -p storage/uploads storage/logs
chmod 750 storage/uploads storage/logs
```

### 5. Set Environment Variables (or edit config/config.php)

```bash
export DB_HOST=localhost
export DB_NAME=parlreg
export DB_USER=parlreg_user
export DB_PASS=your_password
export APP_ENV=development
export APP_URL=http://localhost:8000
```

### 6. Start Dev Server

```bash
php -S localhost:8000 -t public/
```

### 7. Run API Test Suite

```bash
php tests/api_test.php http://localhost:8000
```

---

## API Reference

### Base URL
```
/api/v1
```

### Auth
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/login` | — | Login |
| POST | `/auth/logout` | ✓ | Logout |
| GET | `/auth/me` | ✓ | Current user |
| POST | `/auth/forgot-password` | — | Send reset link |
| POST | `/auth/reset-password` | — | Reset with token |

### Events
| Method | Endpoint | Roles | Description |
|--------|----------|-------|-------------|
| GET | `/events` | admin, organizer | List events |
| POST | `/events` | admin, organizer | Create event |
| GET | `/events/{id}` | admin, organizer | Get event |
| PUT | `/events/{id}` | admin, organizer | Update event |
| DELETE | `/events/{id}` | admin | Delete event |
| POST | `/events/{id}/clone` | admin, organizer | Clone event |
| POST | `/events/{id}/publish` | admin, organizer | Publish |
| POST | `/events/{id}/unpublish` | admin, organizer | Unpublish |
| GET | `/events/{id}/sections` | admin, organizer | Get sections |
| PUT | `/events/{id}/sections` | admin, organizer | Update sections |
| GET | `/events/{id}/schema` | admin, organizer | Get form schema |
| PUT | `/events/{id}/schema` | admin, organizer | Save form schema |
| GET | `/events/{id}/faqs` | admin, organizer | List FAQs |
| POST | `/events/{id}/faqs` | admin, organizer | Create FAQ |
| PUT | `/events/{id}/faqs/{fid}` | admin, organizer | Update FAQ |
| DELETE | `/events/{id}/faqs/{fid}` | admin, organizer | Delete FAQ |

### Registrations
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/events/{id}/register` | — | Public form submit |
| GET | `/events/{id}/registrations` | admin, organizer | List registrations |
| GET | `/events/{id}/registrations/{rid}` | admin, organizer | Single registration |
| PUT | `/events/{id}/registrations/{rid}/status` | admin, organizer | Update status |
| POST | `/events/{id}/registrations/bulk-status` | admin, organizer | Bulk status |
| GET | `/events/{id}/registrations/export` | admin, organizer | CSV export |
| GET | `/files/{fid}/download` | admin, organizer | Download uploaded file |

### Public Portal
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/portal/{slug}` | — | Event data for rendering |
| GET | `/portal/{slug}/schema` | — | Form schema for JS renderer |

### Users
| Method | Endpoint | Roles | Description |
|--------|----------|-------|-------------|
| GET | `/users` | admin | List users |
| POST | `/users` | admin | Create user |
| GET | `/users/{id}` | admin | Get user |
| PUT | `/users/{id}` | admin | Update user |
| DELETE | `/users/{id}` | admin | Deactivate user |

### Settings
| Method | Endpoint | Roles | Description |
|--------|----------|-------|-------------|
| GET | `/settings/smtp` | admin | List SMTP profiles |
| POST | `/settings/smtp` | admin | Create SMTP profile |
| PUT | `/settings/smtp/{id}` | admin | Update SMTP profile |
| DELETE | `/settings/smtp/{id}` | admin | Delete SMTP profile |
| POST | `/settings/smtp/{id}/test` | admin | Test SMTP connection |
| GET | `/audit-log` | admin | View audit log |

---

## Default Admin Credentials

```
Email:    admin@parliament.local
Password: Admin@ParlReg1
```

> **Change immediately after first login.**

---

## Project Structure

```
parlreg/
├── public/                  ← Web root only
│   ├── index.php            ← Front controller / router
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       ├── js/
│       └── icons/           ← Heroicons SVGs
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── EventController.php
│   │   ├── RegistrationController.php
│   │   ├── UserController.php  (+ SettingsController)
│   │   └── PortalController.php
│   ├── helpers/
│   │   ├── DB.php
│   │   ├── Auth.php
│   │   ├── Helpers.php      ← CSRF, RateLimit, Logger, Audit, Response, Validator
│   │   ├── FileHandler.php
│   │   ├── Mailer.php
│   │   └── Translator.php
│   └── views/
├── config/
│   └── config.php
├── database/
│   └── migrations/
│       └── 001_initial_schema.sql
├── lang/
│   ├── en.php
│   └── fr.php
├── storage/                 ← NOT web-accessible
│   ├── uploads/
│   └── logs/
└── tests/
    └── api_test.php
```

---

## Form Schema JSON Format

Each field in the schema array:

```json
{
  "id": "field_001",
  "type": "text|email|phone|number|textarea|select|radio|checkbox|date|file|header",
  "label": { "en": "Full Name", "fr": "Nom complet" },
  "placeholder": { "en": "Enter name", "fr": "Entrez le nom" },
  "helpText": { "en": "As on passport", "fr": "Tel que sur le passeport" },
  "required": true,
  "options": [{ "value": "mp", "label": { "en": "MP", "fr": "Député" } }],
  "validation": { "maxSize": 5, "acceptedTypes": ["application/pdf"] },
  "order": 1,
  "sectionId": "field_000"
}
```

---

## Security Checklist (Production)

- [ ] Change default admin password
- [ ] Set `APP_ENV=production` in environment
- [ ] Configure HTTPS + HSTS
- [ ] Set real `ENCRYPT_KEY` (32-byte hex)
- [ ] Configure reCAPTCHA keys
- [ ] Set `storage/` directory outside web root
- [ ] Configure PHP error logging (not display_errors)
- [ ] Review `.htaccess` rules for your server
- [ ] Run `composer audit` for dependency vulnerabilities
