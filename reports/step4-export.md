# STEP 4 — Export Logic, Credits, Dynamic Headers

## Changes
- Implemented dynamic CSV headers for contacts/companies with and without PII.
- Sanitization blanks PII values while preserving header choice based on dataset PII presence.
- Accurate credit counts in export middleware (sum of email and phone entries, company phone included).
- Preview adds `total_rows` and `can_export_free`; export remains idempotent via `request_id`.
- Fixed array-form sort parsing (earlier) and aligned Billing usage breakdown keys.

## Files Updated
- `api/app/Exports/ExportCsvBuilder.php`: added CONTACT_HEADERS_PII/FREE and COMPANY_HEADERS_PII/FREE; new composition logic.
- `api/app/Http/Middleware/EnsureCreditsForExport.php`: count totals, attach `export_rows`, block when rows > 50k, idempotent spend.
- `api/app/Http/Controllers/Api/v1/ExportController.php`: preview returns `total_rows` and `can_export_free`.
- Tests updated for new headers and robust assertions.

## Verification
- Pint: PASS
- Pest (Feature suite): PASS
- Endpoints return no 500s for preview/export/reveal.

