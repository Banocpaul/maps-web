@extends('layouts.app')

@section('title', 'Add User')

@section('content')
<div class="user-form-page">

    <div class="page-header">
        <div>
            <p class="page-eyebrow">Administration</p>

            <h1 class="page-title">
                Add User
            </h1>

            <p class="page-description">
                Create a new authorized account and assign the appropriate
                system role.
            </p>
        </div>

        <a
            href="{{ route('users.index') }}"
            class="button button-secondary"
        >
            Back to User Management
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <div class="alert-icon">!</div>

            <div class="alert-content">
                <strong>Please correct the following errors:</strong>

                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">
            <div class="alert-icon">!</div>

            <div class="alert-content">
                <strong>Unable to create account</strong>
                <p>{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('users.store') }}"
        class="user-form"
        autocomplete="off"
    >
        @csrf

        <div class="form-layout">

            <section class="form-card">
                <div class="form-card-header">
                    <div>
                        <h2>Account Information</h2>

                        <p>
                            Enter the user's basic identity and login details.
                        </p>
                    </div>
                </div>

                <div class="form-card-body">
                    <div class="form-grid">

                        <div class="form-group form-group-full">
                            <label for="name">
                                Full Name
                                <span class="required-marker">*</span>
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                maxlength="255"
                                required
                                autofocus
                                placeholder="Enter the user's complete name"
                                class="@error('name') input-error @enderror"
                            >

                            @error('name')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group form-group-full">
                            <label for="email">
                                Email Address
                                <span class="required-marker">*</span>
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                maxlength="255"
                                required
                                placeholder="name@example.com"
                                class="@error('email') input-error @enderror"
                            >

                            <p class="field-help">
                                This email address will be used when logging in
                                to M.A.P.S.
                            </p>

                            @error('email')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">
                                Password
                                <span class="required-marker">*</span>
                            </label>

                            <div class="password-input-wrapper">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Minimum of 8 characters"
                                    class="@error('password') input-error @enderror"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword(
                                        'password',
                                        this
                                    )"
                                >
                                    Show
                                </button>
                            </div>

                            <p class="field-help">
                                Use at least eight characters. A combination of
                                letters, numbers, and symbols is recommended.
                            </p>

                            @error('password')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">
                                Confirm Password
                                <span class="required-marker">*</span>
                            </label>

                            <div class="password-input-wrapper">
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Re-enter the password"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword(
                                        'password_confirmation',
                                        this
                                    )"
                                >
                                    Show
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div>
                        <h2>Role and Account Status</h2>

                        <p>
                            Select the user's access level and initial account
                            status.
                        </p>
                    </div>
                </div>

                <div class="form-card-body">
                    <div class="form-grid">

                        <div class="form-group form-group-full">
                            <label for="role_id">
                                System Role
                                <span class="required-marker">*</span>
                            </label>

                            <select
                                id="role_id"
                                name="role_id"
                                required
                                class="@error('role_id') input-error @enderror"
                            >
                                <option value="">
                                    Select a system role
                                </option>

                                @foreach ($roles as $role)
                                    <option
                                        value="{{ $role->id }}"
                                        @selected(
                                            (string) old('role_id')
                                            === (string) $role->id
                                        )
                                    >
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>

                            <p class="field-help">
                                The selected role controls which M.A.P.S.
                                modules and actions the user can access.
                            </p>

                            @error('role_id')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group form-group-full">
                            <div class="status-option">
                                <div class="status-option-content">
                                    <label for="is_active">
                                        Activate account immediately
                                    </label>

                                    <p>
                                        Active users can sign in as soon as the
                                        account is created.
                                    </p>
                                </div>

                                <label class="toggle-switch">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        name="is_active"
                                        value="1"
                                        @checked(old('is_active', true))
                                    >

                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            @error('is_active')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="account-notice form-group-full">
                            <div class="account-notice-icon">
                                i
                            </div>

                            <div>
                                <strong>
                                    Administrator-created accounts
                                </strong>

                                <p>
                                    This account will automatically be marked
                                    as approved and email-verified when saved.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="form-actions">
            <a
                href="{{ route('users.index') }}"
                class="button button-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="button button-primary"
                id="submit-button"
            >
                Create User Account
            </button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .user-form-page {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        width: 100%;
    }

    .page-header {
        align-items: flex-start;
        display: flex;
        gap: 1.5rem;
        justify-content: space-between;
    }

    .page-eyebrow {
        color: #64748b;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin: 0 0 0.35rem;
        text-transform: uppercase;
    }

    .page-title {
        color: #0f172a;
        font-size: 1.85rem;
        font-weight: 800;
        line-height: 1.2;
        margin: 0;
    }

    .page-description {
        color: #64748b;
        line-height: 1.6;
        margin: 0.5rem 0 0;
        max-width: 720px;
    }

    .button {
        align-items: center;
        border: 1px solid transparent;
        border-radius: 0.65rem;
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 700;
        justify-content: center;
        min-height: 42px;
        padding: 0.65rem 1rem;
        text-decoration: none;
        transition:
            background-color 0.15s ease,
            border-color 0.15s ease,
            box-shadow 0.15s ease,
            transform 0.15s ease;
    }

    .button:hover {
        transform: translateY(-1px);
    }

    .button-primary {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: #ffffff;
    }

    .button-primary:hover {
        background: #1e40af;
        border-color: #1e40af;
        box-shadow: 0 8px 20px rgba(29, 78, 216, 0.18);
    }

    .button-secondary {
        background: #ffffff;
        border-color: #cbd5e1;
        color: #334155;
    }

    .button-secondary:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }

    .alert {
        align-items: flex-start;
        border: 1px solid;
        border-radius: 0.75rem;
        display: flex;
        gap: 0.9rem;
        padding: 1rem;
    }

    .alert-error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }

    .alert-icon {
        align-items: center;
        border: 1px solid currentColor;
        border-radius: 999px;
        display: flex;
        flex: 0 0 24px;
        font-size: 0.8rem;
        font-weight: 800;
        height: 24px;
        justify-content: center;
    }

    .alert-content {
        flex: 1;
    }

    .alert-content strong {
        display: block;
        margin-bottom: 0.3rem;
    }

    .alert-content p {
        margin: 0;
    }

    .error-list {
        margin: 0.5rem 0 0;
        padding-left: 1.2rem;
    }

    .user-form {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .form-layout {
        display: grid;
        gap: 1.25rem;
        grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.8fr);
    }

    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .form-card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.15rem;
    }

    .form-card-header h2 {
        color: #0f172a;
        font-size: 1rem;
        margin: 0;
    }

    .form-card-header p {
        color: #64748b;
        font-size: 0.82rem;
        line-height: 1.5;
        margin: 0.3rem 0 0;
    }

    .form-card-body {
        padding: 1.15rem;
    }

    .form-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .form-group-full {
        grid-column: 1 / -1;
    }

    .form-group label {
        color: #334155;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .required-marker {
        color: #dc2626;
    }

    .form-group input,
    .form-group select {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.6rem;
        color: #0f172a;
        font-family: inherit;
        font-size: 0.875rem;
        min-height: 44px;
        outline: none;
        padding: 0.65rem 0.75rem;
        width: 100%;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .form-group .input-error {
        border-color: #ef4444;
    }

    .form-group .input-error:focus {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
    }

    .field-help {
        color: #94a3b8;
        font-size: 0.75rem;
        line-height: 1.5;
        margin: 0;
    }

    .field-error {
        color: #dc2626;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .password-input-wrapper {
        position: relative;
    }

    .password-input-wrapper input {
        padding-right: 4.4rem;
    }

    .password-toggle {
        background: transparent;
        border: 0;
        color: #2563eb;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.4rem;
        position: absolute;
        right: 0.55rem;
        top: 50%;
        transform: translateY(-50%);
    }

    .status-option {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.7rem;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 1rem;
    }

    .status-option-content label {
        color: #0f172a;
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .status-option-content p {
        color: #64748b;
        font-size: 0.76rem;
        line-height: 1.45;
        margin: 0.3rem 0 0;
    }

    .toggle-switch {
        display: inline-flex;
        flex: 0 0 48px;
        height: 26px;
        position: relative;
        width: 48px;
    }

    .toggle-switch input {
        height: 0;
        opacity: 0;
        width: 0;
    }

    .toggle-slider {
        background: #cbd5e1;
        border-radius: 999px;
        bottom: 0;
        cursor: pointer;
        left: 0;
        position: absolute;
        right: 0;
        top: 0;
        transition: 0.2s ease;
    }

    .toggle-slider::before {
        background: #ffffff;
        border-radius: 999px;
        bottom: 3px;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.22);
        content: "";
        height: 20px;
        left: 3px;
        position: absolute;
        transition: 0.2s ease;
        width: 20px;
    }

    .toggle-switch input:checked + .toggle-slider {
        background: #2563eb;
    }

    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(22px);
    }

    .account-notice {
        align-items: flex-start;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.7rem;
        color: #1e3a8a;
        display: flex;
        gap: 0.8rem;
        padding: 1rem;
    }

    .account-notice-icon {
        align-items: center;
        border: 1px solid #3b82f6;
        border-radius: 999px;
        display: flex;
        flex: 0 0 24px;
        font-size: 0.75rem;
        font-weight: 800;
        height: 24px;
        justify-content: center;
    }

    .account-notice strong {
        display: block;
        font-size: 0.82rem;
    }

    .account-notice p {
        color: #1d4ed8;
        font-size: 0.76rem;
        line-height: 1.5;
        margin: 0.25rem 0 0;
    }

    .form-actions {
        align-items: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        padding: 1rem 1.15rem;
    }

    @media (max-width: 1000px) {
        .form-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .page-header {
            flex-direction: column;
        }

        .page-header > .button {
            width: 100%;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group-full {
            grid-column: auto;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .form-actions .button {
            width: 100%;
        }

        .status-option {
            align-items: flex-start;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);

        if (!input) {
            return;
        }

        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = 'Hide';
        } else {
            input.type = 'password';
            button.textContent = 'Show';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.user-form');
        const submitButton = document.getElementById('submit-button');

        if (!form || !submitButton) {
            return;
        }

        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.textContent = 'Creating Account...';
        });
    });
</script>
@endpush