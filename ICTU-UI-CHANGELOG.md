# ICTU User Administration — UI Update

## Change Log

**Modified**

1. `resources/views/layouts/app.blade.php`
   - Adds a route-scoped full-width `ictu-admin-main` class to the authenticated main container.
   - Displays the requested User Administration subtitle in the shared top bar.
   - Leaves the existing light-blue sidebar, ICTU route-aware active navigation, notifications, and profile menu intact.
2. `resources/views/administration/users/index.blade.php`
   - Uses four equal-width desktop filter tracks, with Borrowing and Reset together in track four.
   - Uses a semantic full-width table; keeps server data, statuses, Edit and Delete routes, and filters.
   - Makes page-size selection (7/14/28) and previous/next pagination functional.
   - Disables self-deletion in the UI, in addition to existing server-side protection.
   - Uses `data-user-name` for the confirmation prompt instead of interpolating a name into inline JavaScript.
3. `resources/views/administration/users/form.blade.php`
   - Removes the narrow 860px layout constraint.
   - Organizes Identity, Organizational Assignment, Additional Requesting Unit, Classification/Status/Contact, and Administrative Actions into clearly separated full-width cards.
   - Uses uniform 1fr/1fr and 1fr/1fr/1fr grids; the additional-unit picker uses 85fr/15fr.
   - Places **Send verification link** directly below the official email field for an unverified saved account. Verified accounts instead show confirmation; newly created accounts receive verification automatically on save.
   - Preserves existing field names, model bindings, authorization, historical assignment handling, server-side validation, password policy and ICTU self-edit protections.
4. **Added** `resources/views/administration/users/partials/ui-styles.blade.php`
   - Route-scoped CSS for both interfaces, including keyboard focus, desktop/tablet/mobile layout, native dropdown behavior, icon buttons, dark-theme variables and readable typography.

**Deleted**
- Nothing from the application. The previous page-local CSS implementations are replaced by one shared stylesheet partial.

## Applying this package

Copy the `resources/` tree from the patch ZIP into the corresponding Laravel project root, preserving subfolders and replacing the three existing files. The shared `ui-styles.blade.php` partial is new.

In your Docker-based environment, clear any previous compiled Blade/config assets:

```bash
docker compose exec app php artisan optimize:clear
```

No database migration, new route, controller edit or frontend build is required. The user-count badge reflects actual database records; the requested `38 total users` appears only if 38 users match the current directory query.

## Validation

- Existing and revised form field names were compared: none of the form's original submitted fields were removed.
- Every JavaScript `getElementById` target resolves in its respective view.
- Index/form inline JavaScript passes `node --check`.
- All four Blade files compile and their compiled PHP passes `php -l`.
- Shared CSS parses without errors using `tinycss2`.
- A full feature-test run was **not** possible in the validation environment because PHP lacks the DOM and a database PDO driver. Re-run role-specific feature tests in the project's Docker environment before deployment.

## Functional notes

- Native select controls provide working dropdown arrows.
- Edit and Delete use the project's existing named routes. Deletion of a record referenced by other transactions may be rejected by the existing backend; use account status `Inactive` to retain audit history instead.
- The new verification link acts on the **currently saved** email; users must save a changed address first. The existing backend sends email verification after an address change.
- Only Borrower accounts can assign additional requesting units, per current controller rules.
