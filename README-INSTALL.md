# ICTU Manage User Dropdown Icon Hotfix — September 29, 2026

## Problem fixed
- The disabled **Office / College / Unit** dropdown in **Additional Authorized Requesting Unit** displayed dozens of repeated chevrons.
- Sticky header was slightly transparent, permitting pale text from the scrolled form to show through.

## Cause
The app's global `input:disabled, select:disabled, textarea:disabled { background: var(--surface-muted) }` shorthand resets `background-repeat` to `repeat`. The select chevron is an SVG background image, so it tiles until a Division is selected and the control becomes enabled.

## Install
Copy the `resources` directory in this patch over the equivalent path in your current project. ONLY ONE shared Blade stylesheet is replaced:
`resources/views/administration/users/partials/ui-styles.blade.php`.

Your form/controller/routes/business logic remain unchanged.

Your Docker setup copies application source into the image, so execute in the project root:
```
docker compose build app
docker compose up -d --no-deps --force-recreate app
docker compose exec app php artisan optimize:clear
```
Then use Ctrl + Shift + R on the Edit User page.

## Expected behavior
1. Initially, additional Office/College/Unit is disabled with one chevron, and Add Unit is disabled.
2. Choose Division: unit selector becomes enabled and lists matching units.
3. Choose unit: Add Unit becomes enabled.
4. The header stays solid on page scroll.
