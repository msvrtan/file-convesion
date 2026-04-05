# Review Notes

## P2: Reject array-valued `file` inputs before constructing `ConversionRequest`

File:
`api/src/Service/RequestResolver.php`

Problem:
`RequestResolver::convertRequest()` assumes `$httpRequest->files->get('file')` is either an `UploadedFile` or `null`, but Symfony returns an array for malformed multipart shapes such as `file[]`. That currently causes a `TypeError` and a `500` response instead of a proper `400`.

Action:
- Guard against array-valued `file` inputs before building `ConversionRequest`.
- Treat malformed multipart file payloads as bad requests.
- Raise the existing bad-request flow instead of letting a `TypeError` escape.
- Add tests for `file[]` or other array-shaped `file` payloads to confirm the endpoint returns `400`.

## P2: Parse `Accept` headers consistently for bad-request responses

File:
`api/src/EventSubscriber/BadRequestSubscriber.php`

Problem:
`BadRequestSubscriber::resolveResponseMediaType()` matches the raw `Accept` header string exactly, so requests with standard multi-value or weighted XML headers such as `application/xml, */*` or `application/xml;q=0.9,application/json;q=0.8` still receive JSON error bodies.

Action:
- Reuse the same `Accept` parsing behavior as the controller success path.
- Honor XML preference for multi-value and weighted XML `Accept` headers.
- Keep JSON as the fallback when the header is missing or unsupported.
- Add tests that verify bad-request responses return XML for multi-value and weighted XML `Accept` headers.
