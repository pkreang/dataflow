@extends('layouts.app')

@section('title', __('notifications.notification_settings'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.notifications')],
    ]" />
@endsection

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('notifications.notification_settings') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('notifications.notification_settings_desc') }}</p>
    </div>

    @if(session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-error mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert-error mb-4">
            <ul class="list-disc list-inside space-y-1 text-sm">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.notifications.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="space-y-6">
            {{-- Email Section --}}
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('notifications.email_notifications') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('notifications.email_notifications_desc') }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="toggle[notifications.email_enabled]" value="0">
                        <input type="checkbox" name="toggle[notifications.email_enabled]" value="1"
                               {{ ($settings['notifications.email_enabled'] ?? '1') === '1' ? 'checked' : '' }}
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('notifications.email_notifications') }}</span>
                    </label>

                    @php
                        $emailEvents = [
                            'notifications.approval_pending_email' => __('notifications.event_approval_pending'),
                            'notifications.workflow_approved_email' => __('notifications.event_workflow_approved'),
                            'notifications.workflow_rejected_email' => __('notifications.event_workflow_rejected'),
                            'notifications.stock_low_email' => __('notifications.event_stock_low'),
                        ];
                    @endphp

                    @foreach($emailEvents as $key => $label)
                        <label class="flex items-center gap-3 cursor-pointer ml-6">
                            <input type="hidden" name="toggle[{{ $key }}]" value="0">
                            <input type="checkbox" name="toggle[{{ $key }}]" value="1"
                                   {{ ($settings[$key] ?? '1') === '1' ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-600">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-1">{{ __('notifications.mail_outbound_title') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ __('notifications.mail_outbound_desc') }}</p>

                    <label class="flex items-center gap-3 cursor-pointer mb-4">
                        <input type="hidden" name="toggle[mail.use_db_settings]" value="0">
                        <input type="checkbox" name="toggle[mail.use_db_settings]" value="1"
                               {{ ($settings['mail.use_db_settings'] ?? '0') === '1' ? 'checked' : '' }}
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('notifications.mail_use_db_label') }}</span>
                    </label>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 ml-7">{{ __('notifications.mail_use_db_hint') }}</p>

                    @php
                        $mHost = old('mail_smtp_host', $settings['mail.smtp_host'] ?? '');
                        $mPort = old('mail_smtp_port', $settings['mail.smtp_port'] ?? (string) config('mail.mailers.smtp.port', 587));
                        $mUser = old('mail_smtp_username', $settings['mail.smtp_username'] ?? '');
                        $mEnc = old('mail_smtp_encryption', ($settings['mail.smtp_encryption'] ?? '') === '' ? 'none' : $settings['mail.smtp_encryption']);
                        $mMailer = old('mail_mailer', $settings['mail.mailer'] ?? config('mail.default', 'smtp'));
                        $mFrom = old('mail_from_address', $settings['mail.from_address'] ?? config('mail.from.address', ''));
                        $mFromName = old('mail_from_name', $settings['mail.from_name'] ?? config('mail.from.name', ''));
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="mail_mailer" class="form-label">{{ __('notifications.mail_mailer') }}</label>
                            <select id="mail_mailer" name="mail_mailer" class="form-input w-full">
                                <option value="smtp" @selected($mMailer === 'smtp')>SMTP</option>
                                <option value="log" @selected($mMailer === 'log')>{{ __('notifications.mail_mailer_log') }}</option>
                                <option value="sendmail" @selected($mMailer === 'sendmail')>{{ __('notifications.mail_mailer_sendmail') }}</option>
                                <option value="array" @selected($mMailer === 'array')>{{ __('notifications.mail_mailer_array') }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="mail_smtp_host" class="form-label">{{ __('notifications.mail_smtp_host') }}</label>
                            <input type="text" id="mail_smtp_host" name="mail_smtp_host" value="{{ $mHost }}"
                                   class="form-input w-full"
                                   autocomplete="off">
                        </div>
                        <div>
                            <label for="mail_smtp_port" class="form-label">{{ __('notifications.mail_smtp_port') }}</label>
                            <input type="number" id="mail_smtp_port" name="mail_smtp_port" value="{{ $mPort }}" min="1" max="65535"
                                   class="form-input w-full">
                        </div>
                        <div>
                            <label for="mail_smtp_encryption" class="form-label">{{ __('notifications.mail_smtp_encryption') }}</label>
                            <select id="mail_smtp_encryption" name="mail_smtp_encryption" class="form-input w-full">
                                <option value="none" @selected($mEnc === 'none')>{{ __('notifications.mail_encryption_none') }}</option>
                                <option value="tls" @selected($mEnc === 'tls')>{{ __('notifications.mail_encryption_tls') }}</option>
                                <option value="ssl" @selected($mEnc === 'ssl')>{{ __('notifications.mail_encryption_ssl') }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="mail_smtp_username" class="form-label">{{ __('notifications.mail_smtp_username') }}</label>
                            <input type="text" id="mail_smtp_username" name="mail_smtp_username" value="{{ $mUser }}"
                                   class="form-input w-full"
                                   autocomplete="off">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="mail_smtp_password" class="form-label">{{ __('notifications.mail_smtp_password') }}</label>
                            <input type="password" id="mail_smtp_password" name="mail_smtp_password" value=""
                                   class="form-input w-full"
                                   autocomplete="new-password" placeholder="{{ __('notifications.mail_smtp_password_placeholder') }}">
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                @if($smtpPasswordConfigured)
                                    {{ __('notifications.mail_password_stored') }}
                                @else
                                    {{ __('notifications.mail_smtp_password_hint') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <label for="mail_from_address" class="form-label">{{ __('notifications.mail_from_address') }}</label>
                            <input type="email" id="mail_from_address" name="mail_from_address" value="{{ $mFrom }}"
                                   class="form-input w-full">
                        </div>
                        <div>
                            <label for="mail_from_name" class="form-label">{{ __('notifications.mail_from_name') }}</label>
                            <input type="text" id="mail_from_name" name="mail_from_name" value="{{ $mFromName }}"
                                   class="form-input w-full">
                        </div>
                    </div>
                </div>
            </div>

            {{-- LINE Messaging API (LINE Official Account) Section — replaces LINE Notify (discontinued 2025-03-31) --}}
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.271.173-.508.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('notifications.line_notifications') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('notifications.line_notifications_desc') }}</p>
                    </div>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 ml-11">
                    {{ __('notifications.line_setup_hint') }}
                    <a href="https://developers.line.biz/console/" target="_blank" rel="noopener noreferrer"
                       class="text-blue-600 dark:text-blue-400 hover:underline">developers.line.biz/console</a>
                </p>

                <div class="space-y-4 ml-11">
                    <div>
                        <label for="line_messaging_channel_access_token" class="form-label">{{ __('notifications.line_channel_access_token') }}</label>
                        <textarea id="line_messaging_channel_access_token"
                                  name="line_messaging_channel_access_token"
                                  rows="3"
                                  class="form-input w-full font-mono text-xs"
                                  placeholder="{{ __('notifications.line_channel_access_token_placeholder') }}">{{ $settings['line_messaging.channel_access_token'] ?? '' }}</textarea>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('notifications.line_channel_access_token_hint') }}</p>
                    </div>

                    <div>
                        <label for="line_messaging_channel_id" class="form-label">{{ __('notifications.line_channel_id') }}</label>
                        <input type="text" id="line_messaging_channel_id"
                               name="line_messaging_channel_id"
                               value="{{ $settings['line_messaging.channel_id'] ?? '' }}"
                               class="form-input w-full max-w-xs"
                               placeholder="{{ __('notifications.line_channel_id_placeholder') }}">
                    </div>
                </div>

                <div class="space-y-3 mt-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="toggle[line_messaging.enabled]" value="0">
                        <input type="checkbox" name="toggle[line_messaging.enabled]" value="1"
                               {{ ($settings['line_messaging.enabled'] ?? '0') === '1' ? 'checked' : '' }}
                               class="rounded border-slate-300 text-green-600 focus:ring-green-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('notifications.line_notifications') }}</span>
                    </label>

                    @php
                        $lineEvents = [
                            'notifications.approval_pending_line' => __('notifications.event_approval_pending'),
                            'notifications.workflow_approved_line' => __('notifications.event_workflow_approved'),
                            'notifications.workflow_rejected_line' => __('notifications.event_workflow_rejected'),
                            'notifications.stock_low_line' => __('notifications.event_stock_low'),
                        ];
                    @endphp

                    @foreach($lineEvents as $key => $label)
                        <label class="flex items-center gap-3 cursor-pointer ml-6">
                            <input type="hidden" name="toggle[{{ $key }}]" value="0">
                            <input type="checkbox" name="toggle[{{ $key }}]" value="1"
                                   {{ ($settings[$key] ?? '1') === '1' ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-green-600 focus:ring-green-500 w-4 h-4">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- LINE Login (account linking — separate channel from Messaging API) --}}
                <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('notifications.line_login_section') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">{{ __('notifications.line_login_section_hint') }}</p>

                    <div class="space-y-4 ml-1">
                        <div>
                            <label for="line_login_channel_id" class="form-label">{{ __('notifications.line_login_channel_id') }}</label>
                            <input type="text" id="line_login_channel_id" name="line_login_channel_id"
                                   value="{{ $settings['line_login.channel_id'] ?? '' }}"
                                   class="form-input w-full max-w-xs">
                        </div>
                        <div>
                            <label for="line_login_channel_secret" class="form-label">{{ __('notifications.line_login_channel_secret') }}</label>
                            <input type="text" id="line_login_channel_secret" name="line_login_channel_secret"
                                   value="{{ $settings['line_login.channel_secret'] ?? '' }}"
                                   class="form-input w-full font-mono text-xs">
                        </div>
                        <div>
                            <label class="form-label">{{ __('notifications.line_login_callback_url') }}</label>
                            <input type="text" readonly value="{{ route('auth.line.callback', [], true) }}"
                                   class="form-input w-full font-mono text-xs bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 cursor-not-allowed">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn-primary">
                {{ __('notifications.save_settings') }}
            </button>
        </div>
    </form>

    {{-- Test send — must be OUTSIDE the main settings form (nested forms are invalid HTML) --}}
    <div class="mt-4 flex justify-end">
        <form method="POST" action="{{ route('settings.notifications.test-line') }}" class="inline">
            @csrf
            <button type="submit" class="btn-secondary text-sm">
                {{ __('notifications.line_test_send') }}
            </button>
        </form>
    </div>
@endsection
