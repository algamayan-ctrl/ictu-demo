<?php

namespace App\Services;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\EmploymentType;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Enums\UserRole;
use App\Models\BorrowerRegistration;
use App\Models\OrganizationalUnit;
use App\Models\OauthIdentity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;
use Throwable;

class BorrowerRegistrationService
{
    public function __construct(
        private readonly ProtectedFileService $files,
        private readonly AuditService $audit,
        private readonly UserRoleAssignmentService $roles,
        private readonly NotificationService $notifications,
    ) {}

    public function register(array $data, ?UploadedFile $validId = null, ?array $googleContext = null): BorrowerRegistration
    {
        $folder = 'registration-ids/'.Str::uuid();

        try {
            return DB::transaction(function () use ($data, $validId, $folder, $googleContext): BorrowerRegistration {
                if ($googleContext && ($data['email'] !== $googleContext['email'] || $data['registration_type'] !== $googleContext['registration_type'])) {
                    throw ValidationException::withMessages(['email' => 'The registration must match your verified Google identity.']);
                }
                $employee = RegistrationType::from($data['registration_type']) === RegistrationType::Employee;
                $user = User::query()->create([
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'mobile_no' => $data['mobile_no'],
                    'password' => $data['password'],
                    'employee_no' => $employee ? $data['employee_no'] : null,
                    'designation' => $employee ? $data['designation'] : null,
                    'organizational_unit_id' => $employee ? $data['organizational_unit_id'] : null,
                    'employment_type' => $employee ? EmploymentType::Employee : null,
                    'notification_preferences' => ['system' => true, 'email' => true],
                    'account_status' => AccountStatus::Active,
                    'access_classification' => AccessClassification::BorrowerOnly,
                ]);

                $file = null;
                if (! $employee) {
                    if (! $validId) {
                        throw ValidationException::withMessages(['valid_id' => 'A readable Valid ID is required for external registration.']);
                    }
                    try {
                        $file = $this->files->storeUpload($validId, $folder, 'BORROWER_REGISTRATION_ID');
                    } catch (UnableToWriteFile) {
                        throw ValidationException::withMessages(['valid_id' => 'The Valid ID could not be stored correctly. Please try again.']);
                    }
                    $file->forceFill(['uploaded_by_user_id' => $user->id])->save();
                    if (! Storage::disk($file->disk)->exists($file->storage_path)
                        || ! hash_equals($file->sha256, hash('sha256', $this->files->bytes($file)))) {
                        throw ValidationException::withMessages(['valid_id' => 'The Valid ID could not be stored correctly. Please try again.']);
                    }
                }

                $registration = BorrowerRegistration::query()->create([
                    'user_id' => $user->id,
                    'registration_type' => $data['registration_type'],
                    'company_name' => $employee ? null : $data['company_name'],
                    'valid_id_file_id' => $file?->id,
                    'status' => RegistrationStatus::Pending,
                ]);
                if ($googleContext) {
                    $user->markInstitutionalEmailAsVerified();
                    OauthIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'subject' => $googleContext['subject']]);
                    $this->audit->record('USER_EMAIL_VERIFIED', $user, after: ['verification_method' => 'GOOGLE']);
                }
                // No role or requesting-unit grant occurs before SAO approval.
                $this->audit->record('BORROWER_REGISTRATION_CREATED', $registration, after: [
                    'user_id' => $user->id,
                    'registration_type' => $registration->registration_type->value,
                    'status' => RegistrationStatus::Pending->value,
                ]);
                if ($googleContext) {
                    $this->notifySubmitted($registration);
                }

                return $registration->load('user');
            });
        } catch (Throwable $exception) {
            // This folder is a server-generated UUID owned by this attempt.
            // Remove uploaded bytes even if the database transaction rolled back.
            $disk = Storage::disk((string) config('filesystems.protected', 'local'));
            $disk->delete($disk->files($folder));
            throw $exception;
        }
    }

    public function notifySubmitted(BorrowerRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $record = BorrowerRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $user = User::query()->lockForUpdate()->findOrFail($record->user_id);
            if (! $user->hasVerifiedInstitutionalEmail() || $record->status !== RegistrationStatus::Pending
                || $record->submitted_for_review_at !== null) {
                return;
            }
            $record->forceFill(['submitted_for_review_at' => now()])->save();
            $this->audit->record('BORROWER_REGISTRATION_SUBMITTED', $record,
                after: ['user_id' => $user->id, 'registration_type' => $record->registration_type->value, 'status' => 'PENDING']);
            $officers = User::query()
                ->where('access_classification', AccessClassification::SpmuOfficer->value)
                ->where('account_status', AccountStatus::Active->value)
                ->whereNotNull('email_verified_at')
                ->get()
                ->filter(fn (User $user) => Gate::forUser($user)->allows('viewAny', BorrowerRegistration::class));

            $this->notifications->send('BORROWER_REGISTRATION_SUBMITTED', $officers,
                'A verified borrower registration is awaiting SAO review.', $record,
                requiredChannels: ['SYSTEM', 'EMAIL']);
        });
    }

    public function decide(BorrowerRegistration $registration, User $actor, RegistrationStatus $decision, ?string $remarks): BorrowerRegistration
    {
        Gate::forUser($actor)->authorize('update', $registration);
        if ($decision === RegistrationStatus::Pending) {
            throw ValidationException::withMessages(['decision' => 'Choose Approve or Reject.']);
        }
        if ($decision === RegistrationStatus::Rejected && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Provide the reason for rejecting this registration.']);
        }

        $reviewed = DB::transaction(function () use ($registration, $actor, $decision, $remarks): BorrowerRegistration {
            $record = BorrowerRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            Gate::forUser($actor->fresh())->authorize('update', $record);
            if ($record->status !== RegistrationStatus::Pending) {
                throw ValidationException::withMessages(['registration' => 'This registration was already reviewed. No second decision was recorded.']);
            }
            $user = User::query()->lockForUpdate()->findOrFail($record->user_id);
            if (! $user->hasVerifiedInstitutionalEmail()) {
                throw ValidationException::withMessages(['registration' => 'Mailbox verification is required before SAO review.']);
            }
            if ($user->access_classification !== AccessClassification::BorrowerOnly) {
                throw ValidationException::withMessages(['registration' => 'Only a Borrower account may be approved through self-registration review.']);
            }

            if ($decision === RegistrationStatus::Approved) {
                if (! Role::query()->where('role_code', UserRole::Borrower->value)->where('active', true)->exists()) {
                    throw ValidationException::withMessages(['registration' => 'The Borrower role is unavailable. Ask Admin to restore the role configuration.']);
                }
                if ($record->registration_type === RegistrationType::External) {
                    $file = $record->validIdFile;
                    try {
                        $readable = $file && Storage::disk($file->disk)->exists($file->storage_path)
                            && hash_equals($file->sha256, hash('sha256', $this->files->bytes($file)));
                    } catch (\RuntimeException) {
                        $readable = false;
                    }
                    if (! $readable) {
                        throw ValidationException::withMessages(['registration' => 'The Valid ID is missing or corrupted. Approval was not recorded.']);
                    }
                } else {
                    $unit = OrganizationalUnit::query()->activeSelectable()->find($user->organizational_unit_id);
                    if (! $unit) {
                        throw ValidationException::withMessages(['registration' => 'The employee needs an active organizational assignment before approval.']);
                    }
                    DB::table('user_organizational_units')->updateOrInsert(
                        ['user_id' => $user->id, 'organizational_unit_id' => $unit->id],
                        ['assignment_type' => 'PRIMARY', 'assigned_by_user_id' => $actor->id,
                            'assigned_at' => now(), 'revoked_at' => null, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
                $this->roles->synchronize($user, AccessClassification::BorrowerOnly, $actor->id);
            } else {
                DB::table('user_roles')->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }

            $record->forceFill([
                'status' => $decision, 'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(), 'review_remarks' => $remarks,
            ])->save();
            $this->audit->record('BORROWER_REGISTRATION_'.$decision->value, $record, reason: $remarks,
                before: ['status' => RegistrationStatus::Pending->value],
                after: ['status' => $decision->value, 'reviewed_by_user_id' => $actor->id]);

            return $record->load('user');
        });

        // Approval stays committed if mail delivery fails; delivery evidence is retained.
        $this->notifications->send('BORROWER_REGISTRATION_'.$decision->value, [$reviewed->user],
            $decision === RegistrationStatus::Approved
                ? 'SAO approved your borrower registration. Mailbox verification and an active account are still required before sign-in.'
                : 'SAO did not approve your borrower registration. Contact SAO for clarification. '.($remarks ?? ''),
            $reviewed, requiredChannels: ['SYSTEM', 'EMAIL']);

        return $reviewed;
    }
}
