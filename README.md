# AI conversion API

This project is a Symfony API for long-running file conversion jobs. It accepts `CSV`, `JSON`, `XLSX`, and `ODS` files, produces `JSON` or `XML`, and exposes endpoints to submit a job, check status, and download the result. The implementation is intentionally reviewer-friendly, with JWT auth, async processing, API docs, and test coverage as first-class concerns.


**Supported input formats:** CSV, JSON, XLSX, ODS
**Supported output formats:** JSON, XML


## Setup & Running

### Prerequisites

- PHP 8.5+
- [Symfony CLI](https://symfony.com/download)
- Composer

### Installation

```bash
cd api
make reviewer
```

### Start the server

```bash
# Terminal 1: web server
symfony server:start

# Terminal 2: async worker
php bin/console messenger:consume async -vv
```

### Pre-seeded credentials

Six test users are pre-seeded with the password `customer-password` — see [`AppFixtures`](api/src/DataFixtures/AppFixtures.php) for usernames and UUIDs.

## Architecture Overview


### Request flow

1. Client authenticates via `POST /auth/token` to get a JWT
2. Client uploads a file via `POST /conversions` with the desired output format
3. Server creates a `Conversion` entity (status: `accepted`) and dispatches an async message
4. Messenger worker picks up the job, transitions status through `in_progress` to `completed` or `errored`
5. Client polls `GET /conversions/{id}` for status 'completed' status
6. Once complete, client downloads the result via `GET /conversions/{id}/download`

## Design Decisions

- **SQLite** — No external dependencies to install, appropriate for a coding task where reviewers need to clone and run immediately.
- **Symfony Messenger** — Part of the Symfony ecosystem. Doctrine transport is used because the filesystem transport is not supported out of the box.
- **UUIDv7 for primary keys** — ID generation is not database-bound. The v7 variant uses a time-ordered prefix, so IDs fill database indexes sequentially.
- **Accept header simplification** — Instead of exhaustive content negotiation, the API expects `application/json` or `application/xml` (defaulting to JSON when absent).
- **`Response::HTTP_*` constants** — Code and tests use named constants (`Response::HTTP_OK`, `Response::HTTP_NOT_FOUND`) instead of numeric status codes for readability.

