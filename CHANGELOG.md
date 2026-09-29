# Change Log — Audit Trail layout approval

| Change | File | Description |
| --- | --- | --- |
| Modified | `resources/views/reports/audit.blade.php` | Replaced tall date-grouped audit cards with compact full-width audit table, four filters, five columns, 7/14/28 page sizes, Prev/Next and row-level expandable details. Preserved original category classification, reason and audit metadata. |
| Modified | `resources/views/layouts/app.blade.php` | Added route-scoped `ictu-audit-shell` and `ictu-audit-main` classes and the Audit Trail header subtitle, without changing other routes. |
| Retained | `app/Http/Controllers/ReportController.php` | Original access control, audit source and latest-500 cap unchanged. |
| Not changed | Routes, database, migrations, User Accounts, Manage User and notification workflows | The update is dedicated to the Audit Trail interface. |
