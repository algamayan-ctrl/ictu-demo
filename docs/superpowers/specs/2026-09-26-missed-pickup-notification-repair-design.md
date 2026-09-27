# Missed-pickup notification repair

## Scope

Correct only the stale copy used when `CustodyService::repairPickupExpiredNotifications()` creates or retries a `PICKUP_EXPIRED` notification. Align the default `PICKUP_EXPIRED` notification copy with the normal expiry path.

## Preserved workflow

`ApprovedReadyForRelease` + `PREPARING_RELEASE` + `pickup_expired_at` produces the derived `PICKUP_EXPIRED` / Pickup Missed stage. The reservation remains active and no request or custody record is automatically cancelled. Borrowers cannot request a reschedule or cancel at this stage. An SPMU Action Officer may continue the existing release within the `needed_from` boundary; the existing authorized SPMU cancellation flow remains the only final-cancellation path.

## Change

Both normal and repaired `PICKUP_EXPIRED` notifications use this borrower-facing message:

> Your scheduled pickup was not completed. SPMU is reviewing whether the items can still be released or whether the borrowing must be cancelled. No action is required from you at this time.

The event code and title remain `PICKUP_EXPIRED` and `Pickup Schedule Missed`.

## Regression coverage

Extend the missed-pickup feature test to assert the normal and repaired/recreated notification payloads have the neutral review wording and exclude `Request Reschedule`, `Cancel Request`, and automatic-cancellation language.

## Non-goals

No change to status persistence, expiration scheduling, reservation handling, borrower action guards, SPMU continuation/cancellation, inventory, dashboards, queues, calendar closures, routes, or authorization.
