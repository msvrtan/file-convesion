# Review Notes

## P1: Default unsupported `Accept` headers to JSON

File:
`api/src/Controller/ConversionController.php`

Problem:
The current `Accept` handling requires an exact match for `application/json` or `application/xml`. Requests with missing headers or common values such as `*/*` or `application/json, */*` are rejected with `400`.

Action:
- Change response media type resolution to default to JSON when the `Accept` header is missing or unsupported.
- Accept normal multi-value headers instead of requiring one exact string.
- Keep XML support when the client explicitly requests XML.
- Add tests for missing `Accept`, `*/*`, and multi-value `Accept` headers.

## P1: Prevent partial durable state in `AcceptConversion`

File:
`api/src/Service/AcceptConversion.php`

Problem:
The service performs three side effects in sequence:
1. move file to storage
2. save conversion entity
3. dispatch queue message

If step 2 or 3 fails, earlier side effects remain persisted and the request still returns `500`, which can leave orphaned files or accepted conversions without queued work.

Action:
- Introduce rollback/cleanup when a later step fails.
- At minimum:
  - delete the stored file if database save fails
  - delete the stored file and persisted conversion if message dispatch fails
- Prefer a design that makes the operation atomic from the caller’s perspective.
- Add unit tests for cleanup behavior on database and messenger failures.

## P2: Normalize uploaded file extension before validation

File:
`api/src/Model/ConversionRequest.php`

Problem:
`getClientOriginalExtension()` preserves client casing, so valid uploads such as `report.CSV` or `sheet.XLSX` produce uppercase `sourceFormat` values that fail the `Choice` constraint.

Action:
- Normalize the extracted extension to lowercase before assigning `sourceFormat`.
- Keep existing validation rules unchanged after normalization.
- Add tests for uppercase and mixed-case input filenames.
