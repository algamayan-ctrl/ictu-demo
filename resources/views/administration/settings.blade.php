@extends('layouts.app', ['title' => $isIctu ? 'System Configuration' : 'Operational Configuration'])
@section('content')
@php
    $humanizedKey = fn ($key) => ucwords(str_replace('_', ' ', (string) $key));

    $configurationSection = request()->query('section');
    $allowedConfigurationSections = [
        'late-return-fee',
    ];

    if (! in_array($configurationSection, $allowedConfigurationSections, true)) {
        $configurationSection = null;
    }

    $sectionMeta = [
        'late-return-fee' => ['Financial assessment', 'Late Return Fee', 'Define the daily late return rate and manage how the fee is applied.'],
    ];

    $activeSectionMeta = $configurationSection ? $sectionMeta[$configurationSection] : null;
    $lateReturnFeeSetting = $settings->firstWhere('setting_key', 'daily_overdue_tariff');

    $ictuSettings = $isIctu ? $settings->keyBy('setting_key') : collect();

    $googleConfigured = $isIctu
        && filled(config('services.google.client_id'))
        && filled(config('services.google.client_secret'))
        && filled(config('services.google.redirect'));

    $mailTransport = $isIctu
        ? trim((string) config('mail.default'))
        : '';

    $emailConfigured = $isIctu && $mailTransport !== '';

    $maxUploadSetting = $isIctu
        ? $ictuSettings->get('max_upload_mb')
        : null;

    $backupScheduleSetting = $isIctu
        ? $ictuSettings->get('backup_schedule')
        : null;

    $backupTime = '';
    $backupRecurrence = 'daily';
    $backupFinalized = false;

    if ($backupScheduleSetting) {
        $backupValue = $backupScheduleSetting->value_json;
        $normalizedBackup = $backupValue;

        if (is_string($backupValue)) {
            $trimmedBackup = trim($backupValue);
            $decodedBackup = json_decode($trimmedBackup, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBackup)) {
                $normalizedBackup = $decodedBackup;
            } elseif ($trimmedBackup !== '' && strtoupper($trimmedBackup) !== 'NOT_FINALIZED') {
                // Backward compatibility for older documentary text such as
                // "Daily at 2:00 AM" or "2026-01-12|00:59|Daily".
                if (preg_match('/^(?<date>\d{4}-\d{2}-\d{2})\|(?<time>\d{2}:\d{2})\|(?<recurrence>daily|weekly|monthly)$/i', $trimmedBackup, $matches)) {
                    $normalizedBackup = [
                        'date' => $matches['date'],
                        'time' => $matches['time'],
                        'recurrence' => strtolower($matches['recurrence']),
                    ];
                } elseif (preg_match('/^(?<recurrence>daily|weekly|monthly)\s+at\s+(?<time>\d{1,2}:\d{2}\s*(?:AM|PM))$/i', $trimmedBackup, $matches)) {
                    $parsedTime = \Carbon\Carbon::createFromFormat('g:i A', strtoupper(trim($matches['time'])));
                    $normalizedBackup = [
                        'time' => $parsedTime ? $parsedTime->format('H:i') : '',
                        'recurrence' => strtolower($matches['recurrence']),
                    ];
                }
            }
        }

        if (is_array($normalizedBackup)) {
            $backupTime = isset($normalizedBackup['time']) ? substr((string) $normalizedBackup['time'], 0, 5) : '';
            $backupRecurrence = strtolower((string) ($normalizedBackup['recurrence'] ?? 'daily'));

            if (! in_array($backupRecurrence, ['daily', 'weekly', 'monthly'], true)) {
                $backupRecurrence = 'daily';
            }

            $backupFinalized = $backupTime !== '';
        } elseif (filled($backupValue) && strtoupper((string) $backupValue) !== 'NOT_FINALIZED') {
            $backupFinalized = true;
        }
    }
@endphp

@if(!$isIctu)
<section class="page-heading settings-detail-heading">
    <div>
        <p class="eyebrow">{{ $activeSectionMeta[0] ?? 'Effective operational configuration' }}</p>
        <h1>{{ $activeSectionMeta[1] ?? 'Operational Configuration' }}</h1>
        <p>{{ $activeSectionMeta[2] ?? 'Manage approved policy values. Every change is preserved in the audit trail.' }}</p>
    </div>

    @if($configurationSection)
        <a class="button secondary ui-pressable config-back-button" href="{{ route('policies.index') }}">
            Back
        </a>
    @endif
</section>
@endif

@if($isIctu)
@php
    $googleHostedDomain = trim((string) (config('services.google.hosted_domain') ?? config('services.google.hd') ?? ''));
    $googleAllowedDomain = $googleHostedDomain !== ''
        ? $googleHostedDomain
        : 'No Google domain restriction configured';

    $mailTransportLabel = strtoupper($mailTransport !== '' ? $mailTransport : 'SMTP');
@endphp

<section class="content-area ictu-system-config-area ictu-service-area">
    <div class="ictu-config-section-heading">
        <p class="eyebrow">Service status</p>
        <h2>Technical services</h2>
        <p>These services are managed by ICTU. Only status information is shown here.</p>
    </div>

    <div class="ictu-service-detail-grid">
        <article class="card ictu-service-detail-card">
            <div class="ictu-service-card-topline">
                <span class="ictu-service-kicker">AUTHENTICATION</span>
                <span class="ictu-config-state {{ $googleConfigured ? 'is-ready' : 'is-pending' }}">
                    <span class="ictu-state-dot" aria-hidden="true"></span>
                    {{ $googleConfigured ? 'Configured' : 'Needs setup' }}
                </span>
            </div>

            <div class="ictu-service-card-main">
                <span class="ictu-service-icon ictu-google-icon" aria-hidden="true">G</span>
                <div>
                    <h3>Google Sign-In</h3>
                    <p>Identity provider used for institutional account sign-in.</p>
                </div>
            </div>

            <div class="ictu-service-subpanel">
                <span>ALLOWED DOMAIN</span>
                <strong>{{ $googleAllowedDomain }}</strong>
            </div>
        </article>

        <article class="card ictu-service-detail-card">
            <div class="ictu-service-card-topline">
                <span class="ictu-service-kicker">EMAIL</span>
                <span class="ictu-config-state {{ $emailConfigured ? 'is-ready' : 'is-pending' }}">
                    <span class="ictu-state-dot" aria-hidden="true"></span>
                    {{ $emailConfigured ? 'Configured' : 'Needs setup' }}
                </span>
            </div>

            <div class="ictu-service-card-main">
                <span class="ictu-service-icon ictu-email-outline" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                        <path d="m4 7 8 6 8-6"></path>
                    </svg>
                </span>
                <div>
                    <h3>Email Delivery</h3>
                    <p>Application mail transport used for institutional email notifications.</p>
                </div>
            </div>

            <div class="ictu-service-subpanel">
                <span>MAIL TRANSPORT</span>
                <strong>{{ $mailTransportLabel }}</strong>
            </div>
        </article>
    </div>
</section>

<section class="content-area ictu-system-config-area ictu-managed-settings-area">
    <div class="ictu-config-section-heading ictu-managed-settings-heading">
        <p class="eyebrow">Technical configuration</p>
        <h2>System-managed settings</h2>
        <p>Only technical values owned by ICTU are editable here. Every saved change is recorded in the audit trail.</p>
    </div>

    <div class="ictu-technical-settings-grid">
        @if($maxUploadSetting)
            <form
                id="setting-{{ $maxUploadSetting->setting_key }}"
                method="post"
                action="{{ route('administration.settings.update', $maxUploadSetting) }}"
                class="card form-grid ictu-technical-setting-card"
                data-settings-form
            >
                @csrf
                @method('PUT')

                <div class="ictu-setting-card-heading">
                    <div>
                        <span class="ictu-setting-kicker">FILE &amp; SECURITY</span>
                        <h3>Maximum Upload Size</h3>
                    </div>
                    <span class="ictu-config-state is-ready">Active</span>
                </div>

                <p class="ictu-setting-description">Maximum protected upload size used for supporting documents, evidence, and E-signature files.</p>

                <label class="ictu-field">
                    <span>Maximum size (MB)</span>
                    <input
                        type="number"
                        name="value"
                        min="1"
                        step="1"
                        required
                        value="{{ old('value', $maxUploadSetting->value_json ?? '') }}"
                        inputmode="numeric"
                    >
                </label>

                <label class="ictu-field ictu-reason-field">
                    <span>Reason for change</span>
                    <textarea name="reason" required maxlength="1000" placeholder="Describe the configuration change...">{{ old('reason') }}</textarea>
                </label>

                <div class="settings-actions ictu-settings-actions">
                    <button class="button primary ui-pressable" type="submit">Save change</button>
                    <button class="button secondary ui-pressable" type="reset" data-reset-button>Reset</button>
                </div>
            </form>
        @endif

        @if($backupScheduleSetting)
            <form
                id="setting-{{ $backupScheduleSetting->setting_key }}"
                method="post"
                action="{{ route('administration.settings.update', $backupScheduleSetting) }}"
                class="card form-grid ictu-technical-setting-card ictu-backup-setting-card"
                data-settings-form
                data-backup-form
            >
                @csrf
                @method('PUT')

                <div class="ictu-setting-card-heading">
                    <div>
                        <span class="ictu-setting-kicker">BACKUP &amp; MAINTENANCE</span>
                        <h3>Backup Schedule</h3>
                    </div>
                    <span class="ictu-config-state {{ $backupFinalized ? 'is-ready' : 'is-pending' }}">
                        {{ $backupFinalized ? 'Recorded' : 'Not finalized' }}
                    </span>
                </div>

                <p class="ictu-setting-description">Record the approved production backup recurrence and time. This value documents the schedule; infrastructure credentials are never stored here.</p>

                <div class="ictu-backup-fields">
                    <label class="ictu-field ictu-recurrence-field">
                        <span>Recurrence</span>
                        <select name="backup_recurrence" required>
                            <option value="daily" @selected(old('backup_recurrence', $backupRecurrence) === 'daily')>Daily</option>
                            <option value="weekly" @selected(old('backup_recurrence', $backupRecurrence) === 'weekly')>Weekly</option>
                            <option value="monthly" @selected(old('backup_recurrence', $backupRecurrence) === 'monthly')>Monthly</option>
                        </select>
                    </label>

                    <div class="ictu-field ictu-time-field">
                        <span>Backup Time</span>
                        <div class="ictu-time-picker" data-time-picker>
                            <input
                                type="hidden"
                                name="backup_time"
                                value="{{ old('backup_time', $backupTime !== '' ? $backupTime : '00:00') }}"
                                data-time-value
                            >
                            <button class="ictu-time-trigger" type="button" aria-haspopup="dialog" aria-expanded="false" data-time-trigger>
                                <span data-time-display>Select time</span>
                                <x-icon name="chevron-down" size="16" />
                            </button>
                            <div class="ictu-time-popover" role="dialog" aria-label="Choose backup time" hidden data-time-popover>
                                <div class="ictu-time-selection-band" aria-hidden="true"></div>

                                <div class="ictu-time-column-shell">
                                    <button class="ictu-wheel-step is-up" type="button" data-wheel="hour" data-step="-1" aria-label="Previous hour">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
                                    </button>
                                    <div class="ictu-time-wheel" data-time-wheel="hour"></div>
                                    <button class="ictu-wheel-step is-down" type="button" data-wheel="hour" data-step="1" aria-label="Next hour">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                </div>

                                <div class="ictu-time-column-shell">
                                    <button class="ictu-wheel-step is-up" type="button" data-wheel="minute" data-step="-1" aria-label="Previous minute">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
                                    </button>
                                    <div class="ictu-time-wheel" data-time-wheel="minute"></div>
                                    <button class="ictu-wheel-step is-down" type="button" data-wheel="minute" data-step="1" aria-label="Next minute">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                </div>

                                <div class="ictu-time-column-shell is-period">
                                    <button class="ictu-wheel-step is-up" type="button" data-wheel="meridiem" data-step="-1" aria-label="Previous period">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
                                    </button>
                                    <div class="ictu-time-wheel" data-time-wheel="meridiem"></div>
                                    <button class="ictu-wheel-step is-down" type="button" data-wheel="meridiem" data-step="1" aria-label="Next period">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="ictu-field ictu-reason-field ictu-backup-reason-field">
                        <span>Reason for change</span>
                        <textarea name="reason" required maxlength="1000" placeholder="Describe the configuration change...">{{ old('reason') }}</textarea>
                    </label>
                </div>

                <div class="settings-actions ictu-settings-actions">
                    <button class="button primary ui-pressable" type="submit">Save change</button>
                    <button class="button secondary ui-pressable" type="reset" data-reset-button>Reset</button>
                </div>
            </form>
        @endif
    </div>

    <p class="meta ictu-config-boundary-note">SPMU-controlled settings are intentionally excluded — return rules, late fees, workflow deadlines, and sanctions remain under SPMU Admin/Head.</p>
</section>
@else

@if(!$configurationSection)
<section class="content-area">
    <div class="card return-policy-card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Return deadline policy</p>
                <h2>Date-based return rule</h2>
            </div>
            <span class="badge">POLICY</span>
        </div>
        <div class="return-policy-grid">
            <div><span>Expected Return Date</span><strong>Return anytime on that calendar date</strong></div>
            <div><span>Reminder</span><strong>1 day before + on the due date</strong></div>
            <div><span>Overdue begins</span><strong>After the effective return date if items remain outstanding</strong></div>
            <div><span>Grace period</span><strong>None</strong></div>
        </div>
        <p class="meta">The Expected Return Date remains the audit date. If that date is closed through the Operational Calendar, the effective return deadline automatically moves to the next open SPMU return day. Late assessment starts only after that effective deadline.</p>
    </div>
</section>
@endif

@if($configurationSection === 'late-return-fee')
    @if($lateReturnFeeSetting)
        @include('administration.partials.late-return-fee', ['setting' => $lateReturnFeeSetting])
    @else
        <section class="content-area">
            <div class="callout warning">The <code>daily_overdue_tariff</code> setting is missing. Run the system setting seeder to restore it.</div>
        </section>
    @endif
@endif

@if(!$configurationSection)
<section class="content-area">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Other configuration</p>
            <h2>Policy and system settings</h2>
        </div>
    </div>

    <div class="settings-grid admin-settings-grid">
        @foreach($settings as $setting)
            @php
                $dataType = strtoupper((string) ($setting->data_type ?: 'TEXT'));
                $value = $setting->value_json;
                $valueText = $value === null ? 'Not configured' : (
                    is_bool($value) ? ($value ? 'Enabled' : 'Disabled') : (string) $value
                );
                $displayKey = match ($setting->setting_key) {
                    'daily_overdue_tariff' => 'Late Return Daily Fee',
                    default => $humanizedKey($setting->setting_key),
                };
            @endphp

            <form id="setting-{{ $setting->setting_key }}" method="post" action="{{ route('administration.settings.update', $setting) }}" class="card form-grid settings-form" data-settings-form>
                @csrf
                @method('PUT')

                <div class="card-header settings-card-header">
                    <div>
                        <span class="badge">{{ $setting->group_code }}</span>
                        <h3>{{ $displayKey }}</h3>
                        <small class="setting-key">{{ $setting->setting_key }}</small>
                    </div>
                    <x-status-badge :status="$setting->status ?: 'NOT_CONFIGURED'" />
                </div>

                <div class="settings-summary">
                    <span>Current value</span>
                    <strong>{{ $valueText }}</strong>
                </div>

                @if(filled($setting->description))
                    <p class="settings-description">{{ $setting->description }}</p>
                @endif

                @if($dataType === 'BOOLEAN')
                    <div class="checkbox-field">
                        <label class="checkbox">
                            <input type="hidden" name="value" value="0">
                            <input type="checkbox" name="value" value="1" @checked((bool) $value)>
                            <span>Enabled</span>
                        </label>
                    </div>
                @elseif($dataType === 'INTEGER' || $dataType === 'MONEY')
                    <label>
                        Value
                        <input type="number" name="value" value="{{ $value === null ? '' : $value }}" step="{{ $dataType === 'MONEY' ? '0.01' : '1' }}" placeholder="Not configured">
                    </label>
                @else
                    <label>
                        Value
                        <input type="text" name="value" value="{{ $value === null ? '' : $value }}" placeholder="Not configured">
                    </label>
                @endif

                <label class="reason-field">
                    Reason for change
                    <textarea name="reason" required placeholder="Describe the update reason..."></textarea>
                </label>

                <div class="settings-actions">
                    <button class="button primary ui-pressable" data-save-button type="submit" disabled>Save change</button>
                    <a class="button secondary ui-pressable" href="{{ route('administration.index') }}">Back</a>
                </div>

                <small class="audit-note">Changes are recorded in the audit trail.</small>
            </form>
        @endforeach
    </div>
</section>
@endif

@endif

<style>
.settings-detail-heading .button{flex:0 0 auto}.return-policy-grid>div{padding:13px 14px;border:1px solid var(--border,#d7e1eb);border-radius:10px;background:var(--surface-muted,#f7f9fb)}.return-policy-grid span{display:block;color:var(--muted,#62758a);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}.return-policy-grid strong{display:block;margin-top:4px}.return-policy-card{gap:14px}.return-policy-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.callout.compact{padding:11px 12px}.callout.compact strong,.callout.compact span{display:block}.callout.compact span{margin-top:3px}.callout code{font-size:.84em}@media(max-width:1100px){.return-policy-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.settings-detail-heading{align-items:flex-start}.return-policy-grid{grid-template-columns:1fr}}

/* ICTU System Configuration — approved visual layout. */
.ictu-config-flash{flex:0 0 auto!important;margin:0 0 10px!important}.ictu-config-flash .callout{margin:0}
.ictu-system-config-area{display:grid;gap:10px;flex:0 0 auto!important;margin-top:0!important}.ictu-managed-settings-area{margin-top:18px!important;padding-top:18px;border-top:1px solid var(--border,#d7e1eb)}
.ictu-config-section-heading{display:grid;gap:2px}.ictu-config-section-heading .eyebrow{margin:0!important;color:#637b96!important;font-size:10px!important;font-weight:850!important;letter-spacing:.16em!important;text-transform:uppercase}.ictu-config-section-heading h2{margin:0!important;color:var(--heading,#15304f);font-size:18px!important;line-height:1.18!important;font-weight:800}.ictu-config-section-heading p:not(.eyebrow){margin:0!important;color:var(--muted,#62758a);font-size:11px!important;line-height:1.45!important}
.ictu-service-detail-grid,.ictu-technical-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.ictu-service-detail-card,.ictu-technical-setting-card{min-width:0;border-color:#d8e3ee!important;border-radius:10px!important;box-shadow:0 6px 18px rgba(40,72,105,.05)!important;background:var(--surface,#fff)!important}
.ictu-service-detail-card{display:grid!important;gap:14px;padding:22px 24px 18px!important}.ictu-service-card-topline{display:flex;align-items:center;justify-content:space-between;gap:12px}.ictu-service-kicker,.ictu-setting-kicker{display:inline-flex;align-items:center;min-height:20px;padding:4px 10px;border-radius:999px;background:#eef3f8;color:#627a95;font-size:9px;font-weight:850;letter-spacing:.06em;text-transform:uppercase}.ictu-service-card-main{display:flex;align-items:flex-start;gap:16px}.ictu-service-icon{display:grid;place-items:center;flex:0 0 auto}.ictu-google-icon{width:52px;height:52px;font:900 40px/1 Arial,sans-serif;background:conic-gradient(from -40deg,#4285f4 0 25%,#34a853 0 43%,#fbbc05 0 65%,#ea4335 0 82%,#4285f4 0);-webkit-background-clip:text;background-clip:text;color:transparent}.ictu-email-outline{width:52px;height:52px;color:#1576f0}.ictu-email-outline svg{width:48px;height:48px}.ictu-service-card-main h3{margin:2px 0 0;color:var(--heading,#15304f);font-size:16px!important;line-height:1.15;font-weight:800}.ictu-service-card-main p{margin:7px 0 0;color:var(--muted,#6b7f94);font-size:11px!important;line-height:1.5}.ictu-service-subpanel{display:grid;gap:7px;padding:16px 18px;border:1px solid #d8e3ee;border-radius:10px;background:#f5f8fc}.ictu-service-subpanel span{color:#627a95;font-size:10px;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.ictu-service-subpanel strong{color:#142f52;font-size:13px;line-height:1.3}
.ictu-config-state{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:28px;padding:5px 13px;border:1px solid var(--border,#d7e1eb);border-radius:999px;font-size:10px!important;line-height:1;font-weight:800;white-space:nowrap}.ictu-config-state.is-ready{border-color:#b8e6c8;background:#e9f9ef;color:#12713a}.ictu-config-state.is-pending{border-color:#d6dfe8;background:#f3f6f9;color:#5b6d80}.ictu-state-dot{width:8px;height:8px;border-radius:50%;background:currentColor}
.ictu-managed-settings-heading{margin-bottom:2px}.ictu-technical-setting-card{display:flex!important;flex-direction:column;gap:0!important;padding:15px 16px 14px!important;min-height:310px}.ictu-setting-card-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.ictu-setting-card-heading h3{margin:6px 0 0;color:var(--heading,#15304f);font-size:15px!important;line-height:1.2;font-weight:800}.ictu-setting-description{margin:6px 0 10px!important;color:var(--muted,#687e95)!important;font-size:10.6px!important;line-height:1.42!important}
.ictu-field{display:grid;gap:4px;min-width:0;margin:0}.ictu-field>span{color:var(--heading,#243f5b);font-size:10px;font-weight:800;line-height:1.2}.ictu-field :is(input,select,textarea),.ictu-time-trigger{width:100%;border:1px solid #cfdbe8!important;border-radius:8px!important;background:var(--surface,#fff)!important;color:var(--text,#213850)!important;box-shadow:none!important;font-size:11px!important}.ictu-field :is(input,select),.ictu-time-trigger{height:38px!important;min-height:38px!important;padding:8px 12px!important}.ictu-field textarea{min-height:58px!important;padding:10px 12px!important;resize:vertical;line-height:1.35}.ictu-field :is(input,select,textarea):focus,.ictu-time-trigger:focus-visible{outline:none!important;border-color:#1d73e8!important;box-shadow:0 0 0 2px rgba(29,115,232,.13)!important}.ictu-reason-field{margin-top:10px}.ictu-settings-actions{display:flex;align-items:center;gap:10px;margin-top:9px}.ictu-settings-actions .button{min-height:34px!important;height:34px!important;padding:6px 14px!important;border-radius:8px!important;font-size:10.5px!important;font-weight:760!important}.ictu-settings-actions .button.secondary{background:#fff!important;color:#344d67!important;border-color:#cfdbe8!important}
.ictu-backup-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 12px}.ictu-backup-fields .ictu-reason-field{grid-column:1 / -1;margin-top:0}.ictu-time-field{position:relative}.ictu-time-picker{position:relative}.ictu-time-trigger{display:flex;align-items:center;justify-content:space-between;gap:8px;text-align:left;cursor:pointer}.ictu-time-trigger[aria-expanded="true"]{border-color:#1d73e8!important;box-shadow:0 0 0 2px rgba(29,115,232,.12)!important}.ictu-time-trigger .ui-icon{transition:transform .15s ease}.ictu-time-trigger[aria-expanded="true"] .ui-icon{transform:rotate(180deg)}
.ictu-time-popover{position:absolute;z-index:80;top:calc(100% + 8px);right:0;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));box-sizing:border-box;width:100%;min-width:286px;max-width:100%;height:230px;padding:7px 6px;background:var(--surface,#fff);border:1px solid #d7e1eb;border-radius:12px;box-shadow:0 16px 30px rgba(31,54,78,.16);overflow:hidden}.ictu-time-popover[hidden]{display:none}.ictu-time-selection-band{position:absolute;z-index:0;left:6px;right:6px;top:99px;height:32px;border-radius:9px;background:rgba(32,125,255,.16);pointer-events:none}.ictu-time-column-shell{position:relative;z-index:1;display:grid;grid-template-rows:30px 160px 30px;align-items:center;min-width:0;padding:0 4px;overflow:hidden}.ictu-time-column-shell+.ictu-time-column-shell{border-left:1px solid #e0e7ef}.ictu-time-wheel{display:grid;grid-template-rows:repeat(5,32px);align-items:center;justify-items:stretch;min-width:0;width:100%;height:160px;overflow:hidden;overscroll-behavior:contain;touch-action:none;user-select:none}.ictu-wheel-step,.ictu-wheel-option{appearance:none!important;-webkit-appearance:none!important;display:grid!important;place-items:center!important;box-sizing:border-box!important;min-width:0!important;max-width:none!important;width:100%!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;font-family:inherit!important;line-height:1!important}.ictu-wheel-step{height:30px!important;color:#27425f!important;cursor:pointer!important}.ictu-wheel-step svg{width:14px;height:14px;pointer-events:none}.ictu-wheel-step:hover,.ictu-wheel-step:focus-visible{color:#0f2f52!important;background:transparent!important;outline:none!important}.ictu-wheel-option{height:32px!important;color:#70849a!important;font-size:11px!important;font-weight:500!important;cursor:ns-resize!important;transition:color .12s ease,font-size .12s ease,font-weight .12s ease}.ictu-wheel-option:hover{color:#345777!important;background:transparent!important}.ictu-wheel-option.is-selected{color:#102f55!important;font-size:12px!important;font-weight:850!important;background:transparent!important}.ictu-wheel-option.is-dim{color:#74889e!important}.ictu-wheel-option.is-empty{color:transparent!important;cursor:default!important;pointer-events:none}.ictu-time-column-shell.is-period .ictu-wheel-option{font-weight:750!important;cursor:pointer!important}.ictu-time-column-shell.is-period .ictu-wheel-option.is-selected{font-weight:850!important}
.ictu-config-boundary-note{margin:10px 0 0!important;padding-top:0;color:#8190a1!important;font-size:9.5px!important;line-height:1.35!important}
@media(max-width:1100px){.ictu-service-detail-grid,.ictu-technical-settings-grid{grid-template-columns:1fr}.ictu-technical-setting-card{min-height:0}}
@media(max-width:680px){.ictu-service-card-topline,.ictu-setting-card-heading{flex-direction:column;align-items:flex-start}.ictu-service-detail-card{padding:18px!important}.ictu-service-card-main{gap:12px}.ictu-google-icon,.ictu-email-outline{width:42px;height:42px}.ictu-email-outline svg{width:40px;height:40px}.ictu-backup-fields{grid-template-columns:1fr}.ictu-backup-fields .ictu-reason-field{grid-column:1}.ictu-time-popover{left:0;right:auto;min-width:300px}.ictu-settings-actions{flex-wrap:wrap}}
html[data-theme="dark"] .ictu-service-kicker,html[data-theme="dark"] .ictu-setting-kicker{background:#13283c;color:#9db5cb}html[data-theme="dark"] .ictu-service-subpanel{background:#102438;border-color:#35516a}html[data-theme="dark"] .ictu-service-subpanel strong{color:#e9f4ff}html[data-theme="dark"] .ictu-settings-actions .button.secondary{background:#102438!important;color:#dce8f5!important;border-color:#35516a!important}html[data-theme="dark"] .ictu-time-popover{border-color:#35516a;box-shadow:0 14px 30px rgba(0,0,0,.35)}html[data-theme="dark"] .ictu-time-column-shell+.ictu-time-column-shell{border-left-color:#35516a}html[data-theme="dark"] .ictu-wheel-step:hover,html[data-theme="dark"] .ictu-wheel-option:hover{background:transparent!important;color:#d9e8f7!important}html[data-theme="dark"] .ictu-time-selection-band{background:rgba(69,143,255,.22)}html[data-theme="dark"] .ictu-wheel-option.is-selected{color:#eef7ff}

</style>

<script>
(() => {
    document.querySelectorAll('[data-settings-form]').forEach(function (form) {
        const submit = form.querySelector('[data-save-button]');
        if (!submit) return;
        const initialState = new FormData(form);

        const updateState = function () {
            const current = new FormData(form);
            const changed = Array.from(current.entries()).some(function ([key, value]) {
                return key !== '_token' && key !== '_method' && initialState.get(key) !== value;
            });
            submit.disabled = !changed;
        };

        form.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.addEventListener('input', updateState);
            field.addEventListener('change', updateState);
        });
    });

    const pad = (value) => String(value).padStart(2, '0');
    const wrapIndex = (value, size) => ((value % size) + size) % size;

    document.querySelectorAll('[data-time-picker]').forEach(function (picker) {
        const input = picker.querySelector('[data-time-value]');
        const trigger = picker.querySelector('[data-time-trigger]');
        const display = picker.querySelector('[data-time-display]');
        const popover = picker.querySelector('[data-time-popover]');
        const wheels = {
            hour: picker.querySelector('[data-time-wheel="hour"]'),
            minute: picker.querySelector('[data-time-wheel="minute"]'),
            meridiem: picker.querySelector('[data-time-wheel="meridiem"]'),
        };

        if (!input || !trigger || !display || !popover || !wheels.hour || !wheels.minute || !wheels.meridiem) return;

        let hour24 = 0;
        let minute = 0;
        let hasValue = false;

        const parseInput = function () {
            const match = /^(\d{2}):(\d{2})$/.exec(input.value || '');
            if (!match) {
                hour24 = 0;
                minute = 0;
                hasValue = true;
                input.value = '00:00';
                return;
            }

            hour24 = Math.min(23, Math.max(0, Number(match[1])));
            minute = Math.min(59, Math.max(0, Number(match[2])));
            hasValue = true;
        };

        const getHour12 = function () {
            const value = hour24 % 12;
            return value === 0 ? 12 : value;
        };

        const getMeridiem = function () {
            return hour24 >= 12 ? 'PM' : 'AM';
        };

        const to24Hour = function (hour, period) {
            if (period === 'AM') return hour === 12 ? 0 : hour;
            return hour === 12 ? 12 : hour + 12;
        };

        const syncValue = function () {
            hasValue = true;
            input.value = pad(hour24) + ':' + pad(minute);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const updateDisplay = function () {
            display.textContent = hasValue
                ? getHour12() + ':' + pad(minute) + ' ' + getMeridiem()
                : 'Select time';
        };

        const wheelValues = function (type) {
            if (type === 'hour') {
                const current = getHour12();
                return [-2, -1, 0, 1, 2].map(function (offset) {
                    const raw = ((current - 1 + offset + 12) % 12) + 1;
                    return { label: pad(raw), value: raw, selected: offset === 0, empty: false };
                });
            }

            if (type === 'minute') {
                return [-2, -1, 0, 1, 2].map(function (offset) {
                    const raw = wrapIndex(minute + offset, 60);
                    return { label: pad(raw), value: raw, selected: offset === 0, empty: false };
                });
            }

            const current = getMeridiem();
            return current === 'AM'
                ? [
                    { label: '', value: null, selected: false, empty: true },
                    { label: '', value: null, selected: false, empty: true },
                    { label: 'AM', value: 'AM', selected: true, empty: false },
                    { label: 'PM', value: 'PM', selected: false, empty: false },
                    { label: '', value: null, selected: false, empty: true },
                ]
                : [
                    { label: '', value: null, selected: false, empty: true },
                    { label: 'AM', value: 'AM', selected: false, empty: false },
                    { label: 'PM', value: 'PM', selected: true, empty: false },
                    { label: '', value: null, selected: false, empty: true },
                    { label: '', value: null, selected: false, empty: true },
                ];
        };

        const renderWheel = function (type) {
            const wheel = wheels[type];
            wheel.innerHTML = '';

            wheelValues(type).forEach(function (option) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ictu-wheel-option';
                button.textContent = option.label;

                if (option.selected) button.classList.add('is-selected');
                else if (!option.empty) button.classList.add('is-dim');
                if (option.empty) {
                    button.classList.add('is-empty');
                    button.disabled = true;
                } else {
                    button.dataset.value = String(option.value);
                }

                wheel.appendChild(button);
            });
        };

        const render = function () {
            updateDisplay();
            renderWheel('hour');
            renderWheel('minute');
            renderWheel('meridiem');
        };

        const adjust = function (type, step) {
            if (!hasValue) hasValue = true;

            if (type === 'hour') {
                const nextHour = ((getHour12() - 1 + step + 12) % 12) + 1;
                hour24 = to24Hour(nextHour, getMeridiem());
            } else if (type === 'minute') {
                minute = wrapIndex(minute + step, 60);
            } else {
                hour24 = (hour24 + 12) % 24;
            }

            syncValue();
            render();
        };

        const assignValue = function (type, rawValue) {
            if (!hasValue) hasValue = true;

            if (type === 'hour') {
                hour24 = to24Hour(Number(rawValue), getMeridiem());
            } else if (type === 'minute') {
                minute = Number(rawValue);
            } else if (rawValue === 'AM' || rawValue === 'PM') {
                hour24 = to24Hour(getHour12(), rawValue);
            }

            syncValue();
            render();
        };

        const close = function () {
            popover.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        };

        const open = function () {
            render();
            popover.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
        };

        trigger.addEventListener('click', function () {
            popover.hidden ? open() : close();
        });

        popover.querySelectorAll('[data-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                adjust(String(button.dataset.wheel), Number(button.dataset.step));
            });
        });

        Object.entries(wheels).forEach(function ([type, wheel]) {
            wheel.addEventListener('click', function (event) {
                const option = event.target.closest('[data-value]');
                if (!option) return;
                assignValue(type, option.dataset.value);
            });

            wheel.addEventListener('wheel', function (event) {
                event.preventDefault();
                adjust(type, event.deltaY > 0 ? 1 : -1);
            }, { passive: false });
        });

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) close();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !popover.hidden) {
                close();
                trigger.focus();
            }
        });

        const form = picker.closest('form');
        if (form) {
            form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    parseInput();
                    render();
                    close();
                }, 0);
            });
        }

        parseInput();
        render();
    });
})();
</script>
@endsection
