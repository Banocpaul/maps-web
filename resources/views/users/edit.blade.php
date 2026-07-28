@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="user-edit-page">

    <div class="page-header">
        <div>
            <p class="page-eyebrow">Administration</p>

            <h1 class="page-title">Edit User</h1>

            <p class="page-description">
                Update the account information, assigned role, and access
                status for {{ $user->name }}.
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
                <strong>Unable to update account</strong>
                <p>{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <div class="user-summary-card">
        <div class="user-summary-main">
            <div class="user-avatar">
                {{ strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>

            <div class="user-summary-details">
                <div class="user-name-row">
                    <h2>{{ $user->name }}</h2>

                    @if (auth()->id() === $user->id)
                        <span class="current-user-badge">Your Account</span>
                    @endif
                </div>

                <p>{{ $user->email }}</p>

                <div class="summary-badges">
                    @if ($user->role)
                        <span class="role-badge">
                            {{ $user->role->name }}
                        </span>
                    @else
                        <span class="role-badge role-unassigned">
                            No role assigned
                        </span>
                    @endif

                    @if ($user->is_active)
                        <span class="status-badge status-active">
                            <span class="status-dot"></span>
                            Active
                        </span>
                    @else
                        <span class="status-badge status-inactive">
                            <span class="status-dot"></span>
                            Inactive
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="user-summary-meta">
            <div>
                <span class="meta-label">Account ID</span>
                <strong>#{{ $user->id }}</strong>
            </div>

            <div>
                <span class="meta-label">Created</span>
                <strong>
                    {{ $user->created_at?->format('M d, Y') ?? 'Not available' }}
                </strong>
            </div>

            <div>
                <span class="meta-label">Last Login</span>
                <strong>
                    {{ $user->last_login_at
                        ? $user->last_login_at->format('M d, Y h:i A')
                        : 'Never logged in'
                    }}
                </strong>
            </div>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('users.update', $user) }}"
        class="user-edit-form"
        id="user-edit-form"
    >
        @csrf
        @method('PUT')

        <div class="form-layout">

            <section class="form-card">
                <div class="form-card-header">
                    <div>
                        <h2>Account Information</h2>

                        <p>
                            Update the user's name and email address.
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
                                value="{{ old('name', $user->name) }}"
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
                                value="{{ old('email', $user->email) }}"
                                maxlength="255"
                                required
                                placeholder="name@example.com"
                                class="@error('email') input-error @enderror"
                            >

                            <p class="field-help">
                                This email address is used when the user logs in
                                to M.A.P.S.
                            </p>

                            @error('email')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="form-group form-group-full">
                            <div class="information-box">
                                <div class="information-icon">i</div>

                                <div>
                                    <strong>Password management</strong>

                                    <p>
                                        Passwords are not changed on this page.
                                        Use the Reset Password action from the
                                        User Management page to generate a new
                                        temporary password.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div>
                        <h2>Role and Access</h2>

                        <p>
                            Control the modules and functions available to this
                            account.
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
                                            (string) old(
                                                'role_id',
                                                $user->role_id
                                            ) === (string) $role->id
                                        )
                                    >
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>

                            <p class="field-help">
                                The selected role determines the user's
                                permissions inside M.A.P.S.
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
                                        Account Status
                                    </label>

                                    <p id="status-description">
                                        Active users can sign in and access
                                        their assigned modules.
                                    </p>
                                </div>

                                <div class="status-control">
                                    <span
                                        id="status-label"
                                        class="status-text"
                                    >
                                        {{ old(
                                            'is_active',
                                            $user->is_active
                                        ) ? 'Active' : 'Inactive' }}
                                    </span>

                                    <label class="toggle-switch">
                                        <input
                                            type="checkbox"
                                            id="is_active"
                                            name="is_active"
                                            value="1"
                                            @checked(
                                                old(
                                                    'is_active',
                                                    $user->is_active
                                                )
                                            )
                                            @disabled(
                                                auth()->id() === $user->id
                                            )
                                        >

                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            @if (auth()->id() === $user->id)
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="1"
                                >

                                <p class="field-help warning-text">
                                    You cannot deactivate your own account.
                                </p>
                            @endif

                            @error('is_active')
                                <span class="field-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="form-card account-details-card">
                <div class="form-card-header">
                    <div>
                        <h2>Account Details</h2>

                        <p>
                            Review account verification, approval, and activity
                            information.
                        </p>
                    </div>
                </div>

                <div class="form-card-body">
                    <dl class="account-details-list">

                        <div class="account-detail-row">
                            <dt>Email Verification</dt>

                            <dd>
                                @if ($user->email_verified_at)
                                    <span class="detail-status verified">
                                        Verified
                                    </span>

                                    <small>
                                        {{ $user->email_verified_at->format(
                                            'M d, Y h:i A'
                                        ) }}
                                    </small>
                                @else
                                    <span class="detail-status unverified">
                                        Not verified
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div class="account-detail-row">
                            <dt>Administrative Approval</dt>

                            <dd>
                                @if ($user->approved_at)
                                    <span class="detail-status approved">
                                        Approved
                                    </span>

                                    <small>
                                        {{ $user->approved_at->format(
                                            'M d, Y h:i A'
                                        ) }}
                                    </small>
                                @else
                                    <span class="detail-status pending">
                                        Pending approval
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div class="account-detail-row">
                            <dt>Last Successful Login</dt>

                            <dd>
                                @if ($user->last_login_at)
                                    <strong>
                                        {{ $user->last_login_at->format(
                                            'M d, Y'
                                        ) }}
                                    </strong>

                                    <small>
                                        {{ $user->last_login_at->format(
                                            'h:i A'
                                        ) }}
                                    </small>
                                @else
                                    <span class="muted-text">
                                        The user has not logged in yet.
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div class="account-detail-row">
                            <dt>Account Created</dt>

                            <dd>
                                <strong>
                                    {{ $user->created_at?->format(
                                        'M d, Y'
                                    ) ?? 'Not available' }}
                                </strong>

                                @if ($user->created_at)
                                    <small>
                                        {{ $user->created_at->format(
                                            'h:i A'
                                        ) }}
                                    </small>
                                @endif
                            </dd>
                        </div>

                        <div class="account-detail-row">
                            <dt>Last Updated</dt>

                            <dd>
                                <strong>
                                    {{ $user->updated_at?->format(
                                        'M d, Y'
                                    ) ?? 'Not available' }}
                                </strong>

                                @if ($user->updated_at)
                                    <small>
                                        {{ $user->updated_at->format(
                                            'h:i A'
                                        ) }}
                                    </small>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <div class="form-actions">
            <div class="form-actions-message">
                <span class="unsaved-indicator" id="unsaved-indicator"></span>

                <span id="form-status-text">
                    Review the information before saving.
                </span>
            </div>

            <div class="form-action-buttons">
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
                    Save Changes
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .user-edit-page {
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

    .button-primary:disabled {
        cursor: not-allowed;
        opacity: 0.7;
        transform: none;
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

    .user-summary-card {
        align-items: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        display: flex;
        gap: 1.5rem;
        justify-content: space-between;
        padding: 1.15rem;
    }

    .user-summary-main {
        align-items: center;
        display: flex;
        gap: 1rem;
        min-width: 0;
    }

    .user-avatar {
        align-items: center;
        background: #dbeafe;
        border-radius: 999px;
        color: #1d4ed8;
        display: flex;
        flex: 0 0 58px;
        font-size: 1.3rem;
        font-weight: 800;
        height: 58px;
        justify-content: center;
    }

    .user-summary-details {
        min-width: 0;
    }

    .user-summary-details h2 {
        color: #0f172a;
        font-size: 1.1rem;
        margin: 0;
    }

    .user-summary-details > p {
        color: #64748b;
        font-size: 0.82rem;
        margin: 0.3rem 0 0.6rem;
        overflow-wrap: anywhere;
    }

    .user-name-row {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .current-user-badge {
        background: #ede9fe;
        border-radius: 999px;
        color: #6d28d9;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 0.2rem 0.5rem;
        text-transform: uppercase;
    }

    .summary-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .role-badge,
    .status-badge {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.72rem;
        font-weight: 700;
        gap: 0.4rem;
        padding: 0.35rem 0.65rem;
    }

    .role-badge {
        background: #eef2ff;
        color: #4338ca;
    }

    .role-unassigned {
        background: #f1f5f9;
        color: #64748b;
    }

    .status-active {
        background: #dcfce7;
        color: #166534;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-dot {
        background: currentColor;
        border-radius: 999px;
        height: 6px;
        width: 6px;
    }

    .user-summary-meta {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(3, auto);
    }

    .user-summary-meta div {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .meta-label {
        color: #94a3b8;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .user-summary-meta strong {
        color: #334155;
        font-size: 0.76rem;
        white-space: nowrap;
    }

    .user-edit-form {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .form-layout {
        display: grid;
        gap: 1.25rem;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 0.75fr);
    }

    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .account-details-card {
        grid-column: 1 / -1;
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

    .warning-text {
        color: #b45309;
        font-weight: 600;
    }

    .information-box {
        align-items: flex-start;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.7rem;
        color: #1e3a8a;
        display: flex;
        gap: 0.8rem;
        padding: 1rem;
    }

    .information-icon {
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

    .information-box strong {
        display: block;
        font-size: 0.82rem;
    }

    .information-box p {
        color: #1d4ed8;
        font-size: 0.76rem;
        line-height: 1.5;
        margin: 0.25rem 0 0;
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

    .status-control {
        align-items: center;
        display: flex;
        gap: 0.7rem;
    }

    .status-text {
        color: #334155;
        font-size: 0.75rem;
        font-weight: 700;
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

    .toggle-switch input:disabled + .toggle-slider {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .account-details-list {
        display: grid;
        gap: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin: 0;
    }

    .account-detail-row {
        align-items: flex-start;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 0.9rem;
    }

    .account-detail-row dt {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .account-detail-row dd {
        align-items: flex-end;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        margin: 0;
        text-align: right;
    }

    .account-detail-row dd strong {
        color: #334155;
        font-size: 0.78rem;
    }

    .account-detail-row dd small {
        color: #94a3b8;
        font-size: 0.7rem;
    }

    .detail-status {
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.3rem 0.6rem;
    }

    .verified,
    .approved {
        background: #dcfce7;
        color: #166534;
    }

    .unverified,
    .pending {
        background: #fef3c7;
        color: #92400e;
    }

    .muted-text {
        color: #94a3b8;
        font-size: 0.75rem;
    }

    .form-actions {
        align-items: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 1rem 1.15rem;
    }

    .form-actions-message {
        align-items: center;
        color: #64748b;
        display: flex;
        font-size: 0.78rem;
        gap: 0.5rem;
    }

    .unsaved-indicator {
        background: #cbd5e1;
        border-radius: 999px;
        height: 8px;
        width: 8px;
    }

    .unsaved-indicator.changed {
        background: #f59e0b;
    }

    .form-action-buttons {
        display: flex;
        gap: 0.75rem;
    }

    @media (max-width: 1050px) {
        .user-summary-card {
            align-items: flex-start;
            flex-direction: column;
        }

        .user-summary-meta {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            width: 100%;
        }

        .form-layout {
            grid-template-columns: 1fr;
        }

        .account-details-card {
            grid-column: auto;
        }
    }

    @media (max-width: 720px) {
        .page-header {
            flex-direction: column;
        }

        .page-header > .button {
            width: 100%;
        }

        .user-summary-meta {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group-full {
            grid-column: auto;
        }

        .account-details-list {
            grid-template-columns: 1fr;
        }

        .form-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .form-action-buttons {
            flex-direction: column-reverse;
        }

        .form-action-buttons .button {
            width: 100%;
        }
    }

    @media (max-width: 520px) {
        .user-summary-main {
            align-items: flex-start;
        }

        .status-option {
            align-items: flex-start;
            flex-direction: column;
        }

        .status-control {
            justify-content: space-between;
            width: 100%;
        }

        .account-detail-row {
            flex-direction: column;
        }

        .account-detail-row dd {
            align-items: flex-start;
            text-align: left;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('user-edit-form');
        const submitButton = document.getElementById('submit-button');
        const statusCheckbox = document.getElementById('is_active');
        const statusLabel = document.getElementById('status-label');
        const statusDescription = document.getElementById(
            'status-description'
        );
        const unsavedIndicator = document.getElementById(
            'unsaved-indicator'
        );
        const formStatusText = document.getElementById(
            'form-status-text'
        );

        if (!form) {
            return;
        }

        const initialFormData = new FormData(form);
        const initialValues = serializeFormData(initialFormData);

        function serializeFormData(formData) {
            const values = {};

            for (const [key, value] of formData.entries()) {
                values[key] = value;
            }

            if (statusCheckbox) {
                values.is_active = statusCheckbox.checked ? '1' : '0';
            }

            return JSON.stringify(values);
        }

        function updateStatusDisplay() {
            if (!statusCheckbox || !statusLabel || !statusDescription) {
                return;
            }

            if (statusCheckbox.checked) {
                statusLabel.textContent = 'Active';
                statusDescription.textContent =
                    'Active users can sign in and access their assigned modules.';
            } else {
                statusLabel.textContent = 'Inactive';
                statusDescription.textContent =
                    'Inactive users cannot sign in until the account is activated.';
            }
        }

        function checkForChanges() {
            const currentFormData = new FormData(form);
            const currentValues = serializeFormData(currentFormData);
            const hasChanges = currentValues !== initialValues;

            if (unsavedIndicator) {
                unsavedIndicator.classList.toggle(
                    'changed',
                    hasChanges
                );
            }

            if (formStatusText) {
                formStatusText.textContent = hasChanges
                    ? 'You have unsaved changes.'
                    : 'Review the information before saving.';
            }
        }

        form.addEventListener('input', function () {
            updateStatusDisplay();
            checkForChanges();
        });

        form.addEventListener('change', function () {
            updateStatusDisplay();
            checkForChanges();
        });

        form.addEventListener('submit', function () {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Saving Changes...';
            }

            if (formStatusText) {
                formStatusText.textContent =
                    'Updating the user account...';
            }
        });

        updateStatusDisplay();
    });
</script>
@endpush