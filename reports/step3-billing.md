# STEP 3 — Billing Consistency & Credit Engine Fix

## Detections
- Middleware present and used:
  - EnsureCreditsAvailable gates reveal/enrichment with idempotency by request_id and contact_id.
  - EnsureCreditsForExport computes email_count and phone_count, deducts composite credits, annotates request attributes.
- Controllers:
  - RevealController uses RecordNormalizer for primary fields; creates spend transactions with categories reveal_email / reveal_phone.
  - ExportController relies on middleware for credits; uses normalized data for CSV; writes meta via middleware idempotency.
  - BillingController@usage returns usage/breakdown; needed refined export breakdown.
- Routes:
  - Export routes apply ensureCreditsForExport; reveal routes apply ensureCreditsAvailable.
- Tests existed for usage, export, reveal; needed updates for new breakdown keys and sort parsing.

## Patches

1) Billing usage breakdown (export_email/export_phone/adjustments)
- File: api/app/Http/Controllers/Api/v1/BillingController.php
- Change: compute export_email and export_phone from export transaction meta counts; include adjustments debits.

2) Sort parsing accepts array syntax
- File: api/app/Utilities/SearchUrlParser.php
- Change: accept sort[0][field]=... array form in addition to comma-delimited strings.

3) Tests updated for new breakdown and AI role signal check
- Files: api/tests/Feature/BillingUsageBreakdownTest.php, api/tests/Feature/BillingUsageTest.php, api/tests/Feature/NormalizationTest.php, api/tests/Feature/AiGenerateVariantsTest.php
- Changes: assert new breakdown keys, adjust export credits expected split, replace non-existent toContainAnyOf with simple boolean check.

## Reasoning
- Present category-specific usage as required by product spec; preserve idempotency and accurate composite export credits.
- Harden parsing and tests to avoid 500s and brittle assertions.

## Lint & Tests
- Ran: composer dump-autoload -q && ./vendor/bin/pint --test — PASS
- Ran: php -d memory_limit=512M ./vendor/bin/pest --testsuite=Feature — PASS

## Endpoint Smoke
- /api/v1/billing/usage returns new breakdown keys and stripe_enabled flag.
- /api/v1/billing/preview-export supports sanitize + simulate (no debits).
- /api/v1/billing/export returns URL and credits_deducted; idempotent via request_id.
- /api/v1/contacts/{id}/reveal is robust; no 500s on missing fields.
