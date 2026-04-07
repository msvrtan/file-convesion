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

### OpenAPI documentation

The generated OpenAPI spec is available at `GET /doc.json`, and the Swagger UI is available at `GET /doc`.

### Pre-seeded credentials

Six test users are pre-seeded with the password `customer-password` — see [`AppFixtures`](api/src/DataFixtures/AppFixtures.php) for usernames and UUIDs.

## API Usage

Run these examples from the `api/` directory while the Symfony server and Messenger worker are running.

### Get a JWT

```bash
curl -X POST http://127.0.0.1:8000/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"username":"acme-corp","password":"customer-password"}'
```

### Submit a conversion

Replace `<TOKEN>` with the JWT from the previous response.

```bash
curl -X POST http://127.0.0.1:8000/conversions \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer <TOKEN>" \
  -F targetFormat=xml \
  -F file=@tests/Fixtures/sample.csv
```

### Check status

Replace `<CONVERSION_ID>` with the `id` returned by `POST /conversions`.

```bash
curl http://127.0.0.1:8000/conversions/<CONVERSION_ID> \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer <TOKEN>"
```

### Download the converted file

```bash
curl http://127.0.0.1:8000/conversions/<CONVERSION_ID>/download \
  -H "Authorization: Bearer <TOKEN>" \
  -o converted.xml
```

## Quality Checks

Run these commands from the `api/` directory:

```bash
make lint
make fix
make analyse
make test
```

## Architecture Overview


### Request flow

1. Client authenticates via `POST /auth/token` to get a JWT
2. Client uploads a file via `POST /conversions` with the desired output format
3. Server creates a `Conversion` entity with status `accepted` and dispatches an async message
4. Messenger worker transitions the job through `inprogress` to either `completed` or `failed`
5. Client polls `GET /conversions/{id}` until the status reaches a terminal state (`completed` or `failed`)
6. If the job completed, client downloads the result via `GET /conversions/{id}/download`; if it failed, client handles the failure accordingly

## Design Decisions

- **SQLite** — No external dependencies to install, appropriate for a coding task where reviewers need to clone and run immediately.
- **Symfony Messenger** — Part of the Symfony ecosystem. Doctrine transport is used because the filesystem transport is not supported out of the box.
- **UUIDv7 for primary keys** — ID generation is not database-bound. The v7 variant uses a time-ordered prefix, so IDs fill database indexes sequentially.
- **Accept header simplification** — Instead of exhaustive content negotiation, the API expects `application/json` or `application/xml` (defaulting to JSON when absent).
- **`Response::HTTP_*` constants** — Code and tests use named constants (`Response::HTTP_OK`, `Response::HTTP_NOT_FOUND`) instead of numeric status codes for readability.
