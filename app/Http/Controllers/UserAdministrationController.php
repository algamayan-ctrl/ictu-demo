<?php

namespace App\Http\Controllers;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\EmploymentType;
use App\Enums\RegistrationType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\AuditService;
use App\Services\BorrowerRequestAffiliationService;
use App\Services\EmailVerificationService;
use App\Services\NotificationService;
use App\Services\PendingRegistrationSafetyService;
use App\Services\UserRoleAssignmentService;
use App\Support\OrganizationalStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserAdministrationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $activeClassifications = array_map(
            fn (AccessClassification $classification) => $classification->value,
            app(UserPolicy::class)->assignableClassifications($request->user()),
        );

        $query = User::with(['roles', 'organizationalUnit', 'authorizedOrganizationalUnits', 'borrowerRegistration'])
            ->whereIn('access_classification', $activeClassifications);

        /*
         * Mirrors the dashboard's Active Accounts / Inactive Accounts KPI
         * queries exactly, so the drilldown always shows the same subset the
         * card counted - "inactive" is every non-ACTIVE status, not a single
         * discrete value the client-side status filter could already select.
         */
        $statusFilter = null;
        $requestedStatus = (string) $request->query('status', '');

        if ($requestedStatus === 'active') {
            $statusFilter = 'active';
            $query->where('account_status', 'ACTIVE');
        } elseif ($requestedStatus === 'inactive') {
            $statusFilter = 'inactive';
            $query->where('account_status', '!=', 'ACTIVE');
        }

        $verificationFilter = null;
        if ((string) $request->query('verification', '') === 'pending') {
            $verificationFilter = 'pending';
            $query->whereNull('email_verified_at');
        }

        return view('administration.users.index', [
            'users' => $query->orderBy('full_name')->get(),
            'statusFilter' => $statusFilter,
            'verificationFilter' => $verificationFilter,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);
        return $this->form(new User);
    }

    public function edit(User $user): View
    {
        abort_unless(
            $user->access_classification?->isPortalEnabled(),
            404
        );
        Gate::authorize('update', $user);

        return $this->form($user->load(['roles', 'organizationalUnit', 'authorizedOrganizationalUnits']));
    }

    public function store(
        Request $request,
        AuditService $audit,
        UserRoleAssignmentService $roleAssignments,
        EmailVerificationService $emailVerification,
    ): RedirectResponse {
        abort_unless(Gate::forUser($request->user())->allows('create', User::class), 403);

        $data = $this->validated($request, null, $emailVerification);

        $user = DB::transaction(function () use ($data, $audit, $roleAssignments, $request): User {
            $classification = AccessClassification::from($data['access_classification']);
            $unit = $this->resolveOrganizationalUnit($data, $classification);

            $user = User::query()->create([
                'organizational_unit_id' => $unit->id,
                'employee_no' => $data['employee_no'],
                'full_name' => $data['full_name'],
                'designation' => $data['designation'] ?? null,
                'employment_type' => $data['employment_type'],
                'employment_status' => $data['employment_status'],
                'email' => strtolower($data['email']),
                'mobile_no' => $data['mobile_no'] ?? null,
                'notification_preferences' => [
                    'system' => true,
                    'email' => true,
                ],
                'account_status' => $data['account_status'],
                'access_classification' => $classification,
                // Email remains pending until the recipient submits the OTP sent to this mailbox.
                'password' => $data['password'],
            ]);

            $this->synchronizeOrganizationalAssignments(
                $user,
                $classification,
                (int) $unit->id,
                $data['additional_organizational_unit_ids'] ?? [],
                $request->user()->id,
            );

            $roleAssignments->synchronize(
                $user,
                $classification,
                $request->user()->id,
            );

            $audit->record('USER_ACCOUNT_CREATED', $user, after: [
                'access_classification' => $classification->value,
                'portal' => $classification->primaryWorkspace()?->value,
                'employee_no' => $user->employee_no,
                'division_code' => $unit->divisionCode(),
                'organizational_unit_id' => $unit->id,
                'organizational_unit' => $unit->unit_name,
                'authorized_organizational_unit_ids' => $user->authorizedOrganizationalUnits()
                    ->pluck('organizational_units.id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ]);

            return $user;
        });

        // Mail must be sent after committing the account, not inside the transaction.
        $sent = $emailVerification->send($user);
        $audit->record(
            $sent ? 'USER_EMAIL_VERIFICATION_SENT' : 'USER_EMAIL_VERIFICATION_DELIVERY_FAILED',
            $user,
            after: ['email' => $user->email, 'verified' => false],
        );

        return redirect()
            ->route('administration.users.index')
            ->with('status', $sent
                ? "Account created for {$user->full_name}. A 6-digit verification OTP was sent; sign-in remains blocked until verified."
                : "Account created for {$user->full_name}, but the verification OTP could not be sent. Confirm the institutional mail transport, then resend the OTP.");
    }

    public function update(
        Request $request,
        User $user,
        AuditService $audit,
        UserRoleAssignmentService $roleAssignments,
        NotificationService $notifications,
    ): RedirectResponse {
        abort_unless(Gate::forUser($request->user())->allows('update', $user), 403);

        $emailVerification = app(EmailVerificationService::class);
        $data = $this->validated($request, $user, $emailVerification);

        /*
         * The form disables these two controls and submits hidden inputs
         * when ICTU is editing their own account, but a disabled control is
         * a client-side convenience only. The server must not trust the
         * submitted value for the acting user's own account_status or
         * access_classification; it always keeps what is already stored,
         * regardless of what a crafted request sends.
         */
        if ($request->user()->id === $user->id) {
            $data['account_status'] = $user->account_status->value;
            $data['access_classification'] = $user->access_classification->value;
        }

        $accountDisabled = false;
        $emailChanged = false;
        $accountChanged = false;
        $passwordChanged = filled($data['password'] ?? null);
        $isExternalAccount = false;

        DB::transaction(function () use ($data, $user, $audit, $roleAssignments, $request, &$accountDisabled, &$emailChanged, &$accountChanged, &$isExternalAccount, $passwordChanged, $emailVerification): void {
            // Match SAO review's registration -> User lock order.
            $user->borrowerRegistration()->lockForUpdate()->first();
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();
            // Actor/target/type may have changed since binding or initial validation.
            $actor = $request->user()->fresh();
            abort_unless($actor && Gate::forUser($actor)->allows('update', $user), 403);
            $data = $this->validated($request, $user, $emailVerification, $actor);
            if ($actor->id === $user->id) {
                $data['account_status'] = $user->account_status->value;
                $data['access_classification'] = $user->access_classification->value;
            }
            $isExternalAccount = app(BorrowerRequestAffiliationService::class)->isExternal($user);
            /*
             * Compare only meaningful account information. Technical timestamps
             * and pivot metadata must never turn an unchanged form submission
             * into a fake User Account Updated audit event.
             */
            $before = $this->accountAuditSnapshot($user);

            if ($passwordChanged) {
                // Never persist or expose the password/hash in the audit trail.
                $before['credential'] = 'Unchanged';
            }

            $beforeAccountStatus = $user->account_status?->value;
            $beforeEmail = strtolower(trim((string) $user->email));
            $classification = AccessClassification::from($data['access_classification']);
            $unit = $isExternalAccount ? null : $this->resolveOrganizationalUnit($data, $classification, $user);

            $updates = collect($data)
                ->except([
                    'password',
                    'division_code',
                    'new_organizational_unit_name',
                    'additional_organizational_unit_ids',
                ])
                ->all();

            if (! $isExternalAccount) {
                $updates['organizational_unit_id'] = $unit->id;
            }

            if (filled($data['password'] ?? null)) {
                $updates['password'] = $data['password'];
            }

            $user->update($updates);
            $emailChanged = $beforeEmail !== strtolower(trim((string) $user->email));
            if ($emailChanged) {
                // Never carry the previous mailbox's verified timestamp to a new address.
                $user->markInstitutionalEmailAsUnverified();
            }

            $accountDisabled = $beforeAccountStatus === AccountStatus::Active->value
                && in_array($user->account_status?->value, [AccountStatus::Inactive->value, AccountStatus::Suspended->value], true);

            if (! $isExternalAccount) {
                $this->synchronizeOrganizationalAssignments(
                    $user,
                    $classification,
                    (int) $unit->id,
                    $data['additional_organizational_unit_ids'] ?? [],
                    $actor->id,
                );

                $roleAssignments->synchronize($user, $classification, $actor->id);
            }

            $after = $this->accountAuditSnapshot($user);

            if ($passwordChanged) {
                $after['credential'] = 'Updated';
            }

            $accountChanged = $before !== $after;

            /*
             * Saving an unchanged form must not create audit noise.
             * Persist USER_ACCOUNT_UPDATED only when a meaningful account,
             * role, organizational assignment, or credential value changed.
             */
            if ($accountChanged) {
                $audit->record(
                    'USER_ACCOUNT_UPDATED',
                    $user,
                    before: $before,
                    after: $after,
                );
            }
        });

        if ($accountDisabled) {
            $user->refresh();
            $notifications->send(
                'ACCOUNT_ACCESS_DISABLED',
                collect([$user]),
                'Your account access has been disabled. Contact ICTU for assistance.',
                $user,
                ['SYSTEM', 'EMAIL']
            );
        }

        if ($emailChanged) {
            $user->refresh();
            $sent = $emailVerification->send($user);
            $audit->record(
                $sent ? 'USER_EMAIL_REVERIFICATION_SENT' : 'USER_EMAIL_REVERIFICATION_DELIVERY_FAILED',
                $user,
                after: ['email' => $user->email, 'verified' => false],
            );
            return redirect()
                ->route('administration.users.index')
                ->with('status', $sent
                    ? 'Account updated. A new OTP was sent to the changed email; verification is required again before sign-in.'
                    : ($isExternalAccount
                        ? 'Account updated and its changed email is unverified. OTP delivery failed; contact ICTU for mail assistance or request a new OTP on the account verification page.'
                        : 'Account updated and its changed email is unverified. OTP delivery failed; open Manage to resend the OTP.'));
        }

        return redirect()
            ->route('administration.users.index')
            ->with(
                'status',
                $accountChanged
                    ? ($isExternalAccount
                        ? 'External borrower account updated with an audit record.'
                        : 'Account and authorized organizational assignments updated with an audit record.')
                    : 'No account changes were detected.'
            );
    }

    /** Deactivate account access without deleting identities or transaction history. */
    public function deactivate(
        Request $request,
        User $user,
        AuditService $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        Gate::authorize('deactivate', $user);

        // Lock the account to prevent two simultaneous deactivation requests
        // from writing duplicate status-change audit events.
        $changed = DB::transaction(function () use ($request, $user, $audit): bool {
            $account = User::query()->lockForUpdate()->findOrFail($user->getKey());
            Gate::authorize('deactivate', $account);
            if ($account->account_status !== AccountStatus::Active) {
                return false;
            }

            $before = ['account_status' => AccountStatus::Active->value];
            $account->account_status = AccountStatus::Inactive;
            $account->save();

            $audit->record(
                'USER_ACCOUNT_UPDATED',
                $account,
                before: $before,
                after: [
                    'account_status' => AccountStatus::Inactive->value,
                    'change_source' => $request->user()->access_classification === AccessClassification::IctuMaintainer
                        ? 'ICTU_DIRECTORY_DEACTIVATION' : 'ADMIN_DIRECTORY_DEACTIVATION',
                ],
            );

            return true;
        });

        if (! $changed) {
            return redirect()->route('administration.users.index')
                ->with('status', 'This account is already inactive or suspended. No changes were made.');
        }

        $user->refresh();
        $notifications->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$user]),
            'Your account access has been disabled. Contact Admin or ICTU for assistance.',
            $user,
            ['SYSTEM', 'EMAIL'],
        );

        return redirect()->route('administration.users.index')
            ->with('status', "{$user->full_name}'s account has been deactivated. Historical records were preserved.");
    }

    /** Restore portal access for a verified account without changing its history. */
    public function reactivate(
        Request $request,
        User $user,
        AuditService $audit,
    ): RedirectResponse {
        $this->authorizeIctu($request);
        abort_unless($user->access_classification?->isPortalEnabled(), 404);

        if ((int) $request->user()->getKey() === (int) $user->getKey()) {
            return redirect()->route('administration.users.index')
                ->withErrors(['account_status' => 'You cannot reactivate your own ICTU account.']);
        }

        $changed = DB::transaction(function () use ($user, $audit): bool {
            $account = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $account->hasVerifiedInstitutionalEmail()) {
                throw ValidationException::withMessages([
                    'account' => 'Only verified inactive accounts can be reactivated.',
                ]);
            }

            if ($account->account_status !== AccountStatus::Inactive) {
                return false;
            }

            $before = ['account_status' => AccountStatus::Inactive->value];
            $account->account_status = AccountStatus::Active;
            $account->save();

            $audit->record(
                'USER_ACCOUNT_UPDATED',
                $account,
                before: $before,
                after: [
                    'account_status' => AccountStatus::Active->value,
                    'change_source' => 'ICTU_DIRECTORY_REACTIVATION',
                ],
            );

            return true;
        });

        if (! $changed) {
            return redirect()->route('administration.users.index')
                ->with('status', 'This account is not an inactive verified account. No changes were made.');
        }

        return redirect()->route('administration.users.index')
            ->with('status', "{$user->full_name}'s account has been reactivated. Historical records were preserved.");
    }

    /**
     * Remove a mistaken registration only while it is unverified and has never
     * been used. This is deliberately narrower than a general User::delete().
     * Existing audit events remain to document the removed record and the ICTU actor.
     */
    public function deletePendingRegistration(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $this->authorizeIctu($request);
        abort_unless($user->access_classification?->isPortalEnabled(), 404);

        $actingUserId = (int) $request->user()->getKey();
        $blocker = $this->pendingRegistrationDeletionBlocker($user, $actingUserId);
        if ($blocker !== null) {
            return redirect()->route('administration.users.edit', $user)
                ->withErrors(['account' => $this->pendingRegistrationDeletionBlockerMessage($blocker)]);
        }

        DB::transaction(function () use ($user, $audit, $actingUserId): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->getKey());

            // Re-evaluate after the lock so UI state can never authorize a stale deletion.
            $blocker = $this->pendingRegistrationDeletionBlocker($account, $actingUserId);
            if ($blocker !== null) {
                throw ValidationException::withMessages([
                    'account' => $this->pendingRegistrationDeletionBlockerMessage($blocker),
                ]);
            }

            $audit->record('USER_PENDING_REGISTRATION_DELETED', $account,
                reason: 'ICTU removed an unused, unverified registration.',
                before: [
                    'employee_no' => $account->employee_no,
                    'email' => $account->email,
                    'full_name' => $account->full_name,
                    'access_classification' => $account->access_classification?->value,
                    'email_verified_at' => null,
                ],
                after: ['removed' => true],
            );

            // Registration-created role/unit pivots cascade on account deletion.
            // Clear any stale password reset token so the mistyped email can be re-used.
            DB::table('password_reset_tokens')->where('email', $account->email)->delete();
            $account->delete();
        });

        return redirect()->route('administration.users.index')
            ->with('status', 'The unused pending registration was deleted. You can register the corrected institutional email.');
    }

    /**
     * Return the first reason an account cannot be removed as an unused pending
     * registration. The form and deletion endpoint share this decision; the
     * endpoint rechecks it while the account row is locked.
     */
    private function pendingRegistrationDeletionBlocker(User $user, ?int $actingUserId = null): ?string
    {
        if (! $user->exists) {
            return 'not_persisted';
        }

        $actingUserId ??= auth()->id();
        if ($actingUserId !== null && $actingUserId === (int) $user->getKey()) {
            return 'current_user';
        }

        if ($user->hasVerifiedInstitutionalEmail()) {
            return 'verified_email';
        }

        if ($user->last_login_at !== null) {
            return 'previous_login';
        }

        if ($this->pendingAccountHasActiveSession((int) $user->getKey())) {
            return 'active_session';
        }

        if (app(PendingRegistrationSafetyService::class)->hasLinkedRecords((int) $user->getKey())) {
            return 'linked_records';
        }

        return null;
    }

    private function pendingRegistrationDeletionBlockerMessage(string $blocker): string
    {
        return match ($blocker) {
            'current_user' => 'You cannot delete the account you are currently using.',
            'verified_email' => 'Account deactivation is handled in the User Accounts list.',
            'previous_login' => 'This account cannot be deleted because it has previously signed in.',
            'active_session' => 'This account cannot be deleted while an active session exists.',
            'linked_records' => 'This account cannot be deleted because linked system records must be preserved.',
            default => 'This account is not eligible for deletion.',
        };
    }

    private function pendingAccountHasActiveSession(int $userId): bool
    {
        return DB::table('sessions')->where('user_id', $userId)->exists();
    }

    /*
     * Do not block a never-signed-in, unverified registration merely because
     * other system rows reference it. Pending registrations can accumulate
     * administrative/supporting rows before verification, and their foreign-key
     * delete rules (CASCADE / SET NULL) are responsible for cleanup or history
     * preservation. Established users are still protected by verification and
     * last_login_at checks above.
     */

    /** ICTU recovery action if the previous OTP expired, was exhausted, or could not be delivered. */
    public function resendEmailVerification(
        Request $request,
        User $user,
        EmailVerificationService $emailVerification,
        AuditService $audit,
    ): RedirectResponse {
        $this->authorizeIctu($request);
        abort_unless($user->access_classification?->isPortalEnabled(), 404);

        if ($user->hasVerifiedInstitutionalEmail()) {
            return redirect()->route('administration.users.edit', $user)
                ->with('status', 'The registered institutional email is already verified.');
        }

        $sent = $emailVerification->send($user);
        $audit->record(
            $sent ? 'USER_EMAIL_VERIFICATION_RESENT' : 'USER_EMAIL_VERIFICATION_DELIVERY_FAILED',
            $user,
            after: ['email' => $user->email, 'verified' => false],
        );

        return $sent
            ? redirect()->route('administration.users.edit', $user)
                ->with('status', "A new 6-digit verification OTP was sent to {$user->email}.")
            : redirect()->route('administration.users.edit', $user)
                ->withErrors(['email_verification' => 'Verification OTP could not be sent. Confirm the institutional mail transport in ICTU System Configuration, then try again.']);
    }

    private function form(User $user): View
    {
        $registration = $user->exists ? $user->borrowerRegistration()->first() : null;
        $deletionBlocker = $this->pendingRegistrationDeletionBlocker($user);
        $units = OrganizationalUnit::query()
            ->activeSelectable()
            ->orderBy('unit_name')
            ->get();

        $historicalPrimaryUnit = $user->exists
            && $user->organizationalUnit
            && ! $user->organizationalUnit->isSelectable()
                ? $user->organizationalUnit
                : null;

        $additionalUnits = $user->exists
            ? $user->authorizedOrganizationalUnits()
                ->where('organizational_units.id', '!=', $user->organizational_unit_id)
                ->get()
            : collect();

        $additionalUnitIds = $additionalUnits
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $historicalAdditionalUnits = $additionalUnits
            ->reject(fn (OrganizationalUnit $unit) => $unit->isSelectable())
            ->values();

        return view('administration.users.form', [
            'user' => $user,
            'isExternalAccount' => $registration?->registration_type === RegistrationType::External,
            'externalRegistration' => $registration,
            'pendingRegistrationDeletionBlocker' => $deletionBlocker,
            'pendingRegistrationDeletionMessage' => $deletionBlocker === null ? null : $this->pendingRegistrationDeletionBlockerMessage($deletionBlocker),
            'units' => $units,
            'additionalUnitIds' => $additionalUnitIds,
            'historicalPrimaryUnit' => $historicalPrimaryUnit,
            'historicalAdditionalUnits' => $historicalAdditionalUnits,
            'divisionOptions' => $this->divisionOptions(),
            'selectedDivisionCode' => old(
                'division_code',
                $user->organizationalUnit?->divisionCode() ?? ''
            ),
            'classifications' => app(UserPolicy::class)->assignableClassifications(auth()->user()),
            'employmentTypes' => EmploymentType::cases(),
            'accountStatuses' => AccountStatus::cases(),
        ]);
    }

    private function validated(Request $request, ?User $user = null, ?EmailVerificationService $emailVerification = null, ?User $actor = null): array
    {
        $emailVerification ??= app(EmailVerificationService::class);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        if ($user?->exists && app(BorrowerRequestAffiliationService::class)->isExternal($user)) {
            return $this->validatedExternalAccount($request, $user);
        }
        $allowedClassifications = array_map(
            fn (AccessClassification $classification) => $classification->value,
            app(UserPolicy::class)->assignableClassifications($actor ?? $request->user()),
        );

        $retainsHistoricalPrimary = $this->retainsHistoricalPrimary(
            $user,
            $request->input('organizational_unit_id'),
            $request->input('new_organizational_unit_name'),
            $request->input('access_classification'),
        );
        $historicalAdditionalUnitIds = $this->historicalAdditionalUnitIds($user);

        $data = $request->validate([
            'division_code' => [
                $retainsHistoricalPrimary ? 'nullable' : 'required',
                Rule::in(array_keys($this->divisionOptions())),
            ],
            'organizational_unit_id' => ['nullable'],
            'new_organizational_unit_name' => ['nullable', 'string', 'max:255'],
            'additional_organizational_unit_ids' => ['nullable', 'array'],
            'additional_organizational_unit_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('organizational_units', 'id')
                    ->where(function ($query) use ($historicalAdditionalUnitIds): void {
                        $query->where(function ($activeSelectable): void {
                            $activeSelectable
                                ->where('active', true)
                                ->whereIn('unit_type', OrganizationalUnit::selectableUnitTypes());
                        });

                        if ($historicalAdditionalUnitIds !== []) {
                            $query->orWhereIn('id', $historicalAdditionalUnitIds);
                        }
                    }),
            ],
            'employee_no' => [
                'required',
                'string',
                'max:80',
                Rule::unique('users')->ignore($user?->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'employment_status' => [
                'required',
                Rule::in(['FULL_TIME', 'PART_TIME']),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user?->id),
                function (string $attribute, mixed $value, $fail) use ($emailVerification): void {
                    if (! $emailVerification->domainAllowed((string) $value)) {
                        $domains = implode(', ', $emailVerification->allowedDomains());
                        $fail($domains === ''
                            ? 'ICTU registration email policy is not configured. Contact the system administrator.'
                            : "Use an approved CSPC institutional email domain ({$domains}).");
                    }
                },
            ],
            'mobile_no' => ['nullable', 'string', 'max:30'],
            'account_status' => ['required', Rule::enum(AccountStatus::class)],
            'access_classification' => [
                'required',
                Rule::in($allowedClassifications),
            ],
            'password' => [
                $user?->exists ? 'nullable' : 'required',
                'confirmed',
                Password::min(12)->letters()->numbers()->symbols(),
            ],
        ]);

        $classification = AccessClassification::from($data['access_classification']);

        if ($classification !== AccessClassification::BorrowerOnly
            && filled($data['new_organizational_unit_name'] ?? null)) {
            throw ValidationException::withMessages([
                'new_organizational_unit_name' => 'New Office / College / Unit entries may be added only for borrower organizational assignments.',
            ]);
        }

        if (! filled($data['new_organizational_unit_name'] ?? null)
            && ! ctype_digit((string) ($data['organizational_unit_id'] ?? ''))) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'Select an Office / College / Unit or choose Other / Not listed to add one.',
            ]);
        }

        /*
         * Additional requesting-unit assignments are borrower-only.
         * Part-time status alone does not grant another Office / Unit; ICTU
         * must explicitly record every additional unit the borrower may represent.
         */
        if ($classification !== AccessClassification::BorrowerOnly) {
            $data['additional_organizational_unit_ids'] = [];
        }

        $data['additional_organizational_unit_ids'] = collect(
            $data['additional_organizational_unit_ids'] ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // “Borrower” is an access classification, not an official job designation.
        if ($classification === AccessClassification::BorrowerOnly
            && strcasecmp(trim((string) ($data['designation'] ?? '')), AccessClassification::BorrowerOnly->label()) === 0) {
            throw ValidationException::withMessages([
                'designation' => 'Enter the employee\'s official designation / position, not Borrower.',
            ]);
        }

        return $data;
    }

    private function validatedExternalAccount(Request $request, User $user): array
    {
        if ($user->access_classification !== AccessClassification::BorrowerOnly) {
            throw ValidationException::withMessages(['account' => 'The saved external registration and access classification disagree. Account-type conversion requires a separate reviewed correction.']);
        }

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'mobile_no' => ['nullable', 'string', 'max:30'],
            'account_status' => ['required', Rule::enum(AccountStatus::class)],
            'access_classification' => ['required', Rule::in([AccessClassification::BorrowerOnly->value])],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->numbers()->symbols()],
        ];

        // These are reviewed evidence/affiliation or employee-only assignments,
        // not editable account fields. Reject presence, including null overrides.
        foreach ([
            'employee_no', 'designation', 'employment_type', 'employment_status',
            'division_code', 'office_unit', 'organizational_unit_id', 'additional_organizational_unit_ids',
            'new_organizational_unit_name', 'requesting_organizational_unit_id', 'accountable_unit_id',
            'company_name', 'external_company_name', 'affiliation_type', 'registration_type',
            'registration_status', 'status', 'valid_id', 'valid_id_file_id', 'reviewed_by_user_id',
            'reviewed_at', 'review_remarks', 'email_verified_at', 'roles', 'role_ids',
        ] as $field) {
            $rules[$field] = ['missing'];
        }

        return $request->validate($rules);
    }

    private function resolveOrganizationalUnit(
        array $data,
        AccessClassification $classification,
        ?User $existingUser = null,
    ): OrganizationalUnit {
        if ($this->retainsHistoricalPrimary(
            $existingUser,
            $data['organizational_unit_id'] ?? null,
            $data['new_organizational_unit_name'] ?? null,
            $classification->value,
        )) {
            return $existingUser->organizationalUnit;
        }

        $divisionCode = strtoupper((string) $data['division_code']);
        $newName = trim((string) ($data['new_organizational_unit_name'] ?? ''));

        if ($newName !== '') {
            $existing = OrganizationalUnit::query()
                ->activeSelectable()
                ->whereRaw('LOWER(unit_name) = ?', [mb_strtolower($newName)])
                ->first();

            if ($existing) {
                if ($existing->divisionCode() !== $divisionCode) {
                    throw ValidationException::withMessages([
                        'new_organizational_unit_name' => 'That Office / College / Unit already exists under a different Division.',
                    ]);
                }

                $unit = $existing;
            } else {
                $classificationId = OrganizationalUnit::query()
                    ->where('unit_code', $divisionCode)
                    ->where('unit_type', OrganizationalUnit::TYPE_CLASSIFICATION)
                    ->where('active', true)
                    ->value('id');

                $unit = OrganizationalUnit::query()->create([
                    'parent_unit_id' => $classificationId,
                    'unit_code' => $this->uniqueUnitCode($newName),
                    'unit_name' => $newName,
                    'unit_type' => OrganizationalUnit::unitTypeForDivision($divisionCode),
                    'active' => true,
                ]);
            }
        } else {
            $unit = OrganizationalUnit::query()
                ->activeSelectable()
                ->find((int) $data['organizational_unit_id']);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'organizational_unit_id' => 'Select a valid active Office / College / Unit.',
                ]);
            }

            if ($unit->divisionCode() !== $divisionCode) {
                throw ValidationException::withMessages([
                    'organizational_unit_id' => 'The selected Office / College / Unit does not belong to the selected Division.',
                ]);
            }
        }

        $expectedUnit = match ($classification) {
            AccessClassification::SpmuHead,
            AccessClassification::SpmuOfficer => 'SPMU',
            AccessClassification::IctuMaintainer => 'ICTU',
            AccessClassification::BorrowerOnly => null,
            default => null,
        };

        if ($expectedUnit && $unit->unit_code !== $expectedUnit) {
            throw ValidationException::withMessages([
                'access_classification' => "This access classification requires the {$expectedUnit} organizational unit.",
            ]);
        }

        return $unit;
    }

    /** @return list<int> */
    private function historicalAdditionalUnitIds(?User $user): array
    {
        if (! $user?->exists) {
            return [];
        }

        return $user->authorizedOrganizationalUnits()
            ->where('organizational_units.id', '!=', $user->organizational_unit_id)
            ->get()
            ->reject(fn (OrganizationalUnit $unit) => $unit->isSelectable())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function retainsHistoricalPrimary(
        ?User $user,
        mixed $selectedUnitId,
        mixed $newUnitName,
        mixed $classification,
    ): bool {
        if (! $user?->exists || filled($newUnitName)) {
            return false;
        }

        $user->loadMissing('organizationalUnit');
        $currentUnit = $user->organizationalUnit;

        return $currentUnit !== null
            && ! $currentUnit->isSelectable()
            && ctype_digit((string) $selectedUnitId)
            && (int) $selectedUnitId === (int) $currentUnit->id
            && (string) $classification === $user->access_classification?->value;
    }

    private function authorizeIctu(Request $request): void
    {
        abort_unless(
            $request->user()?->access_classification === AccessClassification::IctuMaintainer,
            403,
            'Only ICTU may perform this account maintenance action.'
        );
    }

    /**
     * User-facing ICTU account state used for meaningful audit comparisons.
     *
     * Database IDs, timestamps, pivot assignment metadata, login timestamps,
     * verification timestamps, notification preferences, and password hashes
     * are intentionally excluded. Dedicated audit events already cover
     * verification and other technical actions.
     *
     * @return array<string, mixed>
     */
    private function accountAuditSnapshot(User $user): array
    {
        $account = $user->fresh(['organizationalUnit']) ?? $user;

        $value = static function ($item) {
            return $item instanceof \BackedEnum
                ? $item->value
                : $item;
        };

        return [
            'employee_no' => $account->employee_no,
            'full_name' => $account->full_name,
            'designation' => $account->designation,
            'employment_type' => $value($account->employment_type),
            'employment_status' => $value($account->employment_status),
            'email' => $account->email,
            'mobile_no' => $account->mobile_no,
            'account_status' => $value($account->account_status),
            'access_classification' => $value($account->access_classification),
            'organizational_unit' => $account->organizationalUnit?->unit_name,

            'roles' => $account->roles()
                ->wherePivotNull('revoked_at')
                ->orderBy('role_name')
                ->pluck('role_name')
                ->values()
                ->all(),

            'authorized_organizational_units' => $account
                ->authorizedOrganizationalUnits()
                ->orderBy('unit_name')
                ->pluck('unit_name')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param list<int|string> $additionalUnitIds
     */
    private function synchronizeOrganizationalAssignments(
        User $user,
        AccessClassification $classification,
        int $primaryUnitId,
        array $additionalUnitIds,
        int $assignedByUserId,
    ): void {
        $desired = collect(
            $classification === AccessClassification::BorrowerOnly
                ? $additionalUnitIds
                : []
        )
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $primaryUnitId)
            ->push($primaryUnitId)
            ->unique()
            ->values();

        $now = now();

        /*
         * Revoke only assignments that are no longer desired.
         */
        DB::table('user_organizational_units')
            ->where('user_id', $user->id)
            ->whereNotIn('organizational_unit_id', $desired->all())
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);

        foreach ($desired as $unitId) {
            $assignmentType = $unitId === $primaryUnitId
                ? 'PRIMARY'
                : 'ADDITIONAL';

            $existing = DB::table('user_organizational_units')
                ->where('user_id', $user->id)
                ->where('organizational_unit_id', $unitId)
                ->first();

            /*
             * New assignment: create its original assignment metadata once.
             */
            if ($existing === null) {
                DB::table('user_organizational_units')->insert([
                    'user_id' => $user->id,
                    'organizational_unit_id' => $unitId,
                    'assignment_type' => $assignmentType,
                    'assigned_by_user_id' => $assignedByUserId,
                    'assigned_at' => $now,
                    'revoked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            /*
             * Same active assignment and same type: leave it untouched.
             * In particular, do not rewrite assigned_at/created_at merely
             * because ICTU pressed Save Changes again.
             */
            $needsReassignment =
                $existing->revoked_at !== null
                || (string) $existing->assignment_type !== $assignmentType;

            if (! $needsReassignment) {
                continue;
            }

            /*
             * Reactivating an old assignment or changing PRIMARY/ADDITIONAL
             * is a real assignment action, so record a fresh assignment time
             * and actor while preserving the row's original created_at.
             */
            DB::table('user_organizational_units')
                ->where('user_id', $user->id)
                ->where('organizational_unit_id', $unitId)
                ->update([
                    'assignment_type' => $assignmentType,
                    'assigned_by_user_id' => $assignedByUserId,
                    'assigned_at' => $now,
                    'revoked_at' => null,
                    'updated_at' => $now,
                ]);
        }
    }

    /** @return array<string, string> */
    private function divisionOptions(): array
    {
        return OrganizationalStructure::divisions();
    }

    private function uniqueUnitCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = trim(substr($base !== '' ? $base : 'UNIT', 0, 34), '_');
        $candidate = $base;
        $counter = 2;

        while (OrganizationalUnit::query()->where('unit_code', $candidate)->exists()) {
            $suffix = '_'.$counter++;
            $candidate = substr($base, 0, 40 - strlen($suffix)).$suffix;
        }

        return $candidate;
    }
}
