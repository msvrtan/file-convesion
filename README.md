# AI conversion API

This project is a Symfony API for long-running file conversion jobs. It accepts `CSV`, `JSON`, `XLSX`, and `ODS` files, produces `JSON` or `XML`, and exposes endpoints to submit a job, check status, and download the result. The implementation is intentionally reviewer-friendly, with JWT auth, async processing, API docs, and test coverage as first-class concerns.
