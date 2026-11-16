# SPMU-ACPMP Interface Design

## Design direction

The interface uses a compact role menu, clear status badges, focused forms, and role-specific actions. Panels, controls, table containers, and navigation selections retain their original corner styling. Natural circles in logos, icons, and charts retain their geometry. Navigation exposes the existing destinations authorized for the signed-in classification.

## Portal menus

### Borrower

| Section | Features |
|---|---|
| Main Menu | Dashboard; Available Items; Borrowing Calendar |
| Operations | My Requests; My Borrowings; My Obligations |

### SPMU Action Officer

| Section | Features |
|---|---|
| Main Menu | Dashboard; Inventory; Operations Calendar |
| Operations | Borrower Registrations; For Verification; Release; Return; Laundry; Gate Pass; Accountability |
| Records | Request Records |

### SPMU Admin / Head

| Section | Features |
|---|---|
| Main Menu | Dashboard; Inventory; Operations Calendar |
| Operations | For Approval; Accountability |
| Records & Monitoring | Request Records; Analytics; Reports; Operational Audit Trail |
| Administration | Configuration; User Accounts |

Section headings are labels. The links retain their route names, authorization, count badges, active states, and Accountability/Analytics submenus. Expanded desktop and mobile menus show section labels; the collapsed desktop menu uses separators. Account settings and notifications remain available in the top bar.

### ICTU

ICTU has no portal menu. It is the Technical System Custodian and works on the server, database and source code; the SPMU Admin / Head menu holds User Accounts and Configuration.

There are no GSU or VPAF portals. Their names appear only where the physical Borrowing Request Letter identifies required institutional signatories.

## SPMU verification workspace

For a submitted request, the Action Officer uses a split verification screen:

- left: scanned fully signed Borrowing Request Letter (PDF/image preview);
- right: verification checklist and controls;
- actions: Mark VERIFIED or Return for Revision.

The Head receives only VERIFIED requests and separately chooses Approve, Return for Revision, or Reject. Verification and approval are distinct signed audit events.

## Shared usability rules

1. Show only role-authorized tools.
2. Use plain-language actions and concise helper copy.
3. Keep status/action information visible without exposing unnecessary technical metadata.
4. Use responsive tables/cards and accessible focus/labels.
5. Avoid duplicate controls and old workspace-switching UI.
6. Keep physical-document actions explicit: print, wet-sign, scan, upload, verify.
7. Use the shared 4, 8, 12, and 16 pixel spacing scale where practical; retain enough space for labels, focus indicators, and table scrolling.
8. Keep screen layout changes separate from the geometry of official printed forms and their signature fields.

## Source comment convention

Use short purpose comments for important screens, functions, and route groups, such as `This is the Inventory UI for SPMU Action Officer.`, `This is the function for the generation of Borrower Slip.`, and `This is the route group for authorized document previews and downloads.` Remove decorative revision banners and comments that repeat obvious markup. Preserve type annotations, directives, licensing notices, and concise explanations of non-obvious workflow constraints.

## Protected process rules

UI simplification must not bypass SPMU verification, self-action restrictions, exact reservation, physical preparation/release, Gate Pass/Laundry conditions, return inspection, accountability evidence, delegation attribution or audit records.
