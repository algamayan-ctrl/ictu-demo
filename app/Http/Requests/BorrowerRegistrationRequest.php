<?php

namespace App\Http\Requests;

use App\Enums\RegistrationType;
use App\Models\OrganizationalUnit;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\GoogleRegistrationContext;
use App\Support\OrganizationalStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use setasign\Fpdi\Fpdi;
use Throwable;

class BorrowerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    public static function uploadLimitMb(): int
    {
        // Match the existing protected-document cap and configured ICTU limit.
        return min(64, max(1, (int) SystemSetting::value('max_upload_mb', 5)));
    }

    public function rules(): array
    {
        $employee = $this->input('registration_type') === RegistrationType::Employee->value;

        return [
            'registration_source' => ['sometimes', Rule::in(['manual', 'google']), function ($attribute, $value, $fail): void {
                if ($value === 'google' && ! app(GoogleRegistrationContext::class)->current($this)) {
                    $fail('Your Google registration session expired. Continue with Google again.');
                }
            }],
            'registration_type' => ['required', Rule::enum(RegistrationType::class)],
            'full_name' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'email' => ['bail', 'required', 'email', 'max:255', Rule::unique('users', 'email'), function ($attribute, $value, $fail) use ($employee): void {
                if (User::query()->whereRaw('LOWER(email) = ?', [$value])->exists()) {
                    $fail('This email is already registered. Sign in or contact SAO.');
                }
                if ($employee && ! app(EmailVerificationService::class)->domainAllowed($value)) {
                    $fail('Use an approved CSPC institutional email for employee registration.');
                }
            }],
            'mobile_no' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ()-]{6,29}$/'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->symbols()],
            'employee_no' => [$employee ? 'required' : 'prohibited', 'nullable', 'string', 'max:80', Rule::unique('users', 'employee_no')],
            'designation' => [$employee ? 'required' : 'prohibited', 'nullable', 'string', 'max:255'],
            'division_code' => [$employee ? 'required' : 'prohibited', 'nullable', Rule::in(array_keys(OrganizationalStructure::divisions()))],
            'organizational_unit_id' => [$employee ? 'required' : 'prohibited', 'nullable', 'integer', Rule::exists('organizational_units', 'id')],
            'company_name' => [$employee ? 'prohibited' : 'required', 'nullable', 'string', 'max:255'],
            'valid_id' => [$employee ? 'prohibited' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.(self::uploadLimitMb() * 1024)],
            'access_classification' => ['prohibited'],
            'role' => ['prohibited'],
            'account_status' => ['prohibited'],
            'registration_status' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'reviewed_by_user_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'valid_id_file_id' => ['prohibited'],
            'google_subject' => ['prohibited'],
            'provider' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('registration_source', 'manual') !== 'google'
                && app(GoogleRegistrationContext::class)->current($this)) {
                $validator->errors()->add('registration_source', 'Continue with the verified Google registration, or open Sign up for manual registration.');
            }
            if ($this->input('registration_source') === 'google'
                && ($context = app(GoogleRegistrationContext::class)->current($this))) {
                if ($this->input('email') !== $context['email']) {
                    $validator->errors()->add('email', 'Use the verified Google email shown on this form.');
                }
                if ($this->input('registration_type') !== $context['registration_type']) {
                    $validator->errors()->add('registration_type', 'The verified Google email determines the registration fields.');
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('registration_type') === RegistrationType::Employee->value) {
                $unit = OrganizationalUnit::query()->activeSelectable()->find($this->input('organizational_unit_id'));
                if (! $unit || $unit->divisionCode() !== $this->input('division_code')) {
                    $validator->errors()->add('organizational_unit_id', 'Select an active Office / College / Unit within the selected division.');
                }
            } else {
                $upload = $this->file('valid_id');
                if (mb_strlen($upload->getClientOriginalName()) > 255) {
                    $validator->errors()->add('valid_id', 'Use a document filename of 255 characters or fewer.');

                    return;
                }
                try {
                    if ($upload->getMimeType() === 'application/pdf') {
                        (new Fpdi)->setSourceFile($upload->getRealPath());
                    } elseif (@getimagesize($upload->getRealPath()) === false) {
                        $validator->errors()->add('valid_id', 'Upload a readable PDF, JPG, or PNG Valid ID.');
                    }
                } catch (Throwable) {
                    $validator->errors()->add('valid_id', 'The Valid ID document could not be read. Upload a valid PDF, JPG, or PNG.');
                }
            }
        }];
    }
}
