# Engineering Requirements: File Conversion API

Based on the [business requirements](business-requirements.md), this document defines the technical specification for a long-running file format conversion API.

---

## 1. Functional Requirements

### API Endpoints

| Method | Endpoint                     | Description                                         |
|--------|------------------------------|-----------------------------------------------------|
| `POST` | `/auth/token`                | Obtain JWT token (pre-seeded user)                  |
| `POST` | `/conversions`               | Upload file + desired output format, returns job ID |
| `GET`  | `/conversions/{id}`          | Returns job status and metadata                     |
| `GET`  | `/conversions/{id}/download` | Download converted file                             |

### Authentication
- JWT authentication via `lexik/jwt-authentication-bundle`
- Pre-seeded test user for easy reviewer access
- All `/conversions` endpoints require a valid Bearer token

### Input Validation
- Accepted input formats: **CSV**, **JSON**, **XLSX**, **ODS**
- Accepted output formats: **JSON**, **XML**

### Job Lifecycle
- States: `pending` → `processing` → (`completed` or `failed`)
- Conversion is a dummy service (sleep-based) behind a strategy interface
- Job metadata stored in database

---

## 2. Non-Functional Requirements

### Tech Stack
- **PHP 8.5**, **Symfony 8.x**
- **SQLite** (file-based) for job metadata, customer entities and storing conversion records
- **Symfony Messenger** with **Doctrine transport** for async job processing
- **Flysystem** for easier future moving from current file storage to other storage types like S3 

### Code Quality
- **Symfony coding standards** enforced via **PHP-CS-Fixer**
- **PHPStan level max** with **phpstan-strict-rules** for maximum static analysis strictness

### Infrastructure
- **PHP built-in dev server** (`symfony server:start`
- No Docker — lightweight local setup

### API Documentation
- **nelmio/api-doc-bundle** providing OpenAPI spec
- Interactive Swagger UI at `/doc`

---

## 3. Reviewer Experience

### Setup
- **Makefile** sets up everything:
  - `make reviewer` — runs full setup, setting up JWT keypairs, migrations, fixtures 
- `symfony server:start` + Messenger worker — zero manual config
- Pre-seeded JWT user — reviewer grabs a token immediately
- Swagger UI available for interactive API exploration

### Convenience
- **Makefile** with targets:
  - `make lint` — run PHP-CS-Fixer (dry-run)
  - `make fix` — auto-fix coding style
  - `make analyse` — run PHPStan
  - `make test` — run PHPUnit
- **README** with:
  - Setup instructions
  - Architecture overview (text)
  - API usage examples (curl commands)
  - How to run tests

### Git History
- **Conventional commits** (`feat:`, `fix:`, `chore:`, etc.)
- **Feature branches** merged via PRs into `main`
- Clean, readable history showing incremental progress
- Most PRs will be squashed into a single commit for clarity, if detailed review is required just visit PR page (noted in commit message)

---

## 4. Testing Strategy

- **Unit tests**, **functional tests**, and **end-to-end tests**
- Snake case naming: `test_it_rejects_unsupported_file_type`
- **PHPUnit** via Symfony test framework

---

## Acceptance Criteria

- [ ] All endpoints implemented and documented in Swagger UI
- [ ] JWT auth working with pre-seeded user
- [ ] Async job processing via Messenger + Doctrine transport
- [ ] PHP dev server + Messenger worker starts everything
- [ ] Makefile targets work: test, lint, fix, analyse
- [ ] Unit, functional, and end-to-end tests pass
- [ ] PHPStan level max passes with no errors
- [ ] PHP-CS-Fixer (Symfony standards) reports no violations
- [ ] README documents setup, architecture, and usage
