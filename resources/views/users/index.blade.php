@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="user-management-page">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <p class="page-eyebrow">Administration</p>
            <h1 class="page-title">User Management</h1>
            <p class="page-description">
                Create, update, activate, deactivate, reset, and manage
                authorized M.A.P.S. user accounts.
            </p>
        </div>

        <div class="page-header-actions">
            <a href="{{ route('users.create') }}" class="button button-primary">
                <span class="button-icon">+</span>
                <span>Add User</span>
            </a>
        </div>
    </div>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="alert alert-success">
            <div class="alert-icon">✓</div>

            <div class="alert-content">
                <strong>Success</strong>
                <p>{{ session('success') }}</p>
            </div>

            <button
                type="button"
                class="alert-close"
                onclick="this.parentElement.remove()"
                aria-label="Close success message"
            >
                ×
            </button>
        </div>
    @endif

    {{-- Error Message --}}
    @if (session('error'))
        <div class="alert alert-error">
            <div class="alert-icon">!</div>

            <div class="alert-content">
                <strong>Action failed</strong>
                <p>{{ session('error') }}</p>
            </div>

            <button
                type="button"
                class="alert-close"
                onclick="this.parentElement.remove()"
                aria-label="Close error message"
            >
                ×
            </button>
        </div>
    @endif

    {{-- Temporary Password Message --}}
    @if (session('temporary_password'))
        <div class="temporary-password-card">
            <div class="temporary-password-header">
                <div>
                    <p class="temporary-password-label">
                        Temporary password generated
                    </p>

                    <h2>
                        {{ session('password_user_name', 'User') }}
                    </h2>
                </div>

                <button
                    type="button"
                    class="temporary-password-close"
                    onclick="this.closest('.temporary-password-card').remove()"
                    aria-label="Close temporary password message"
                >
                    ×
                </button>
            </div>

            <div class="temporary-password-body">
                <div class="password-display">
                    <code id="temporary-password">
                        {{ session('temporary_password') }}
                    </code>

                    <button
                        type="button"
                        class="button button-secondary button-small"
                        onclick="copyTemporaryPassword()"
                    >
                        Copy
                    </button>
                </div>

                <p>
                    Give this password to the user securely. For security,
                    this message will disappear after leaving or refreshing
                    this page.
                </p>

                <span id="copy-message" class="copy-message"></span>
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-error">
            <div class="alert-icon">!</div>

            <div class="alert-content">
                <strong>Please review the following:</strong>

                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-card-content">
                <p class="summary-label">Matching Users</p>
                <h2 class="summary-value">{{ number_format($users->total()) }}</h2>
                <p class="summary-detail">
                    Based on the current search and filters
                </p>
            </div>

            <div class="summary-icon">U</div>
        </div>

        <div class="summary-card">
            <div class="summary-card-content">
                <p class="summary-label">System Roles</p>
                <h2 class="summary-value">{{ number_format($roles->count()) }}</h2>
                <p class="summary-detail">
                    Available roles for account assignment
                </p>
            </div>

            <div class="summary-icon">R</div>
        </div>

        <div class="summary-card">
            <div class="summary-card-content">
                <p class="summary-label">Current Page</p>
                <h2 class="summary-value">
                    {{ number_format($users->currentPage()) }}
                </h2>
                <p class="summary-detail">
                    Page {{ $users->currentPage() }} of
                    {{ max($users->lastPage(), 1) }}
                </p>
            </div>

            <div class="summary-icon">P</div>
        </div>
    </div>

    {{-- Main Content Card --}}
    <div class="content-card">

        {{-- Search and Filters --}}
        <div class="filter-section">
            <form
                method="GET"
                action="{{ route('users.index') }}"
                class="filter-form"
            >
                <div class="filter-field filter-field-search">
                    <label for="search">Search users</label>

                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Search by name or email address"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="filter-field">
                    <label for="role_id">Role</label>

                    <select id="role_id" name="role_id">
                        <option value="">All roles</option>

                        @foreach ($roles as $role)
                            <option
                                value="{{ $role->id }}"
                                @selected(
                                    (string) ($filters['role_id'] ?? '')
                                    === (string) $role->id
                                )
                            >
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-field">
                    <label for="status">Status</label>

                    <select id="status" name="status">
                        <option value="">All statuses</option>

                        <option
                            value="active"
                            @selected(
                                ($filters['status'] ?? '') === 'active'
                            )
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            @selected(
                                ($filters['status'] ?? '') === 'inactive'
                            )
                        >
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="button button-primary">
                        Apply Filters
                    </button>

                    <a
                        href="{{ route('users.index') }}"
                        class="button button-secondary"
                    >
                        Clear
                    </a>
                </div>
            </form>
        </div>

        {{-- Table Header --}}
        <div class="table-section-header">
            <div>
                <h2>System Accounts</h2>

                <p>
                    Showing
                    <strong>{{ $users->firstItem() ?? 0 }}</strong>
                    to
                    <strong>{{ $users->lastItem() ?? 0 }}</strong>
                    of
                    <strong>{{ $users->total() }}</strong>
                    account(s)
                </p>
            </div>
        </div>

        {{-- Users Table --}}
        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Last Login</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">
                                        {{ strtoupper(
                                            mb_substr($user->name, 0, 1)
                                        ) }}
                                    </div>

                                    <div class="user-information">
                                        <div class="user-name-row">
                                            <strong>{{ $user->name }}</strong>

                                            @if (auth()->id() === $user->id)
                                                <span class="current-user-badge">
                                                    You
                                                </span>
                                            @endif
                                        </div>

                                        <span>{{ $user->email }}</span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                @php
    $assignedRole = $user->role()->first();
@endphp

@if ($assignedRole)
    <span class="role-badge">
        {{ $assignedRole->name }}
    </span>
@else
    <span class="role-badge role-unassigned">
        No role
    </span>
@endif
                            </td>

                            <td>
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
                            </td>

                            <td>
                                @if ($user->approved_at)
                                    <span class="approval-status approved">
                                        Approved
                                    </span>

                                    <small class="date-detail">
                                        {{ $user->approved_at->format(
                                            'M d, Y'
                                        ) }}
                                    </small>
                                @else
                                    <span class="approval-status pending">
                                        Pending
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if ($user->last_login_at)
                                    <strong class="last-login-date">
                                        {{ $user->last_login_at->format(
                                            'M d, Y'
                                        ) }}
                                    </strong>

                                    <small class="date-detail">
                                        {{ $user->last_login_at->format(
                                            'h:i A'
                                        ) }}
                                    </small>
                                @else
                                    <span class="never-logged-in">
                                        Never logged in
                                    </span>
                                @endif
                            </td>

                            <td class="actions-column">
                                <div class="action-menu">
                                    <a
                                        href="{{ route(
                                            'users.edit',
                                            $user
                                        ) }}"
                                        class="action-button edit-button"
                                        title="Edit account"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'users.reset-password',
                                            $user
                                        ) }}"
                                        class="inline-form"
                                        onsubmit="return confirmPasswordReset(
                                            @js($user->name)
                                        )"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="action-button reset-button"
                                            title="Reset password"
                                        >
                                            Reset
                                        </button>
                                    </form>

                                    @if (auth()->id() !== $user->id)
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'users.status',
                                                $user
                                            ) }}"
                                            class="inline-form"
                                            onsubmit="return confirmStatusChange(
                                                @js($user->name),
                                                {{ $user->is_active
                                                    ? 'false'
                                                    : 'true'
                                                }}
                                            )"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value="{{ $user->is_active
                                                    ? 0
                                                    : 1
                                                }}"
                                            >

                                            @if ($user->is_active)
                                                <button
                                                    type="submit"
                                                    class="action-button deactivate-button"
                                                    title="Deactivate account"
                                                >
                                                    Deactivate
                                                </button>
                                            @else
                                                <button
                                                    type="submit"
                                                    class="action-button activate-button"
                                                    title="Activate account"
                                                >
                                                    Activate
                                                </button>
                                            @endif
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'users.destroy',
                                                $user
                                            ) }}"
                                            class="inline-form"
                                            onsubmit="return confirmUserDeletion(
                                                @js($user->name)
                                            )"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="action-button delete-button"
                                                title="Delete account"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    @else
                                        <span
                                            class="protected-account-label"
                                            title="You cannot deactivate or delete your own account."
                                        >
                                            Protected
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon">U</div>

                                    <h3>No users found</h3>

                                    <p>
                                        No user accounts matched the current
                                        search or filter criteria.
                                    </p>

                                    <div class="empty-state-actions">
                                        <a
                                            href="{{ route('users.index') }}"
                                            class="button button-secondary"
                                        >
                                            Clear Filters
                                        </a>

                                        <a
                                            href="{{ route('users.create') }}"
                                            class="button button-primary"
                                        >
                                            Add User
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="mobile-user-list">
            @forelse ($users as $user)
                <article class="mobile-user-card">
                    <div class="mobile-user-header">
                        <div class="user-cell">
                            <div class="user-avatar">
                                {{ strtoupper(
                                    mb_substr($user->name, 0, 1)
                                ) }}
                            </div>

                            <div class="user-information">
                                <div class="user-name-row">
                                    <strong>{{ $user->name }}</strong>

                                    @if (auth()->id() === $user->id)
                                        <span class="current-user-badge">
                                            You
                                        </span>
                                    @endif
                                </div>

                                <span>{{ $user->email }}</span>
                            </div>
                        </div>

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

                    <dl class="mobile-user-details">
                        <div>
                            <dt>Role</dt>
                            <dd>
                                {{ $user->role?->name ?? 'No role' }}
                            </dd>
                        </div>

                        <div>
                            <dt>Approval</dt>
                            <dd>
                                {{ $user->approved_at
                                    ? 'Approved'
                                    : 'Pending'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Last Login</dt>
                            <dd>
                                {{ $user->last_login_at
                                    ? $user->last_login_at->format(
                                        'M d, Y h:i A'
                                    )
                                    : 'Never logged in'
                                }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mobile-actions">
                        <a
                            href="{{ route('users.edit', $user) }}"
                            class="action-button edit-button"
                        >
                            Edit
                        </a>

                        <form
                            method="POST"
                            action="{{ route(
                                'users.reset-password',
                                $user
                            ) }}"
                            class="inline-form"
                            onsubmit="return confirmPasswordReset(
                                @js($user->name)
                            )"
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                type="submit"
                                class="action-button reset-button"
                            >
                                Reset Password
                            </button>
                        </form>

                        @if (auth()->id() !== $user->id)
                            <form
                                method="POST"
                                action="{{ route(
                                    'users.status',
                                    $user
                                ) }}"
                                class="inline-form"
                                onsubmit="return confirmStatusChange(
                                    @js($user->name),
                                    {{ $user->is_active
                                        ? 'false'
                                        : 'true'
                                    }}
                                )"
                            >
                                @csrf
                                @method('PATCH')

                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="{{ $user->is_active ? 0 : 1 }}"
                                >

                                <button
                                    type="submit"
                                    class="action-button {{
                                        $user->is_active
                                            ? 'deactivate-button'
                                            : 'activate-button'
                                    }}"
                                >
                                    {{ $user->is_active
                                        ? 'Deactivate'
                                        : 'Activate'
                                    }}
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route(
                                    'users.destroy',
                                    $user
                                ) }}"
                                class="inline-form"
                                onsubmit="return confirmUserDeletion(
                                    @js($user->name)
                                )"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="action-button delete-button"
                                >
                                    Delete
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <h3>No users found</h3>
                    <p>No accounts matched the current filters.</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if ($users->hasPages())
            <div class="pagination-wrapper">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .user-management-page {
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

    .page-header-actions {
        display: flex;
        flex-shrink: 0;
        gap: 0.75rem;
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
        gap: 0.45rem;
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

    .button-small {
        min-height: 34px;
        padding: 0.45rem 0.75rem;
    }

    .button-icon {
        font-size: 1.1rem;
        line-height: 1;
    }

    .alert {
        align-items: flex-start;
        border: 1px solid;
        border-radius: 0.75rem;
        display: flex;
        gap: 0.9rem;
        padding: 1rem;
    }

    .alert-success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
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
        margin-bottom: 0.2rem;
    }

    .alert-content p {
        line-height: 1.5;
        margin: 0;
    }

    .alert-close {
        background: transparent;
        border: 0;
        color: inherit;
        cursor: pointer;
        font-size: 1.35rem;
        line-height: 1;
        opacity: 0.7;
        padding: 0;
    }

    .error-list {
        margin: 0.5rem 0 0;
        padding-left: 1.2rem;
    }

    .temporary-password-card {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 0.8rem;
        overflow: hidden;
    }

    .temporary-password-header {
        align-items: flex-start;
        border-bottom: 1px solid #fde68a;
        display: flex;
        justify-content: space-between;
        padding: 1rem 1.15rem;
    }

    .temporary-password-header h2 {
        color: #78350f;
        font-size: 1rem;
        margin: 0.2rem 0 0;
    }

    .temporary-password-label {
        color: #92400e;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        margin: 0;
        text-transform: uppercase;
    }

    .temporary-password-close {
        background: transparent;
        border: 0;
        color: #92400e;
        cursor: pointer;
        font-size: 1.35rem;
        padding: 0;
    }

    .temporary-password-body {
        padding: 1rem 1.15rem;
    }

    .temporary-password-body p {
        color: #92400e;
        font-size: 0.875rem;
        line-height: 1.5;
        margin: 0.75rem 0 0;
    }

    .password-display {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .password-display code {
        background: #ffffff;
        border: 1px dashed #d97706;
        border-radius: 0.55rem;
        color: #78350f;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        padding: 0.7rem 0.85rem;
    }

    .copy-message {
        color: #047857;
        display: inline-block;
        font-size: 0.8rem;
        font-weight: 700;
        margin-top: 0.5rem;
    }

    .summary-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .summary-card {
        align-items: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 1.15rem;
    }

    .summary-label {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 700;
        margin: 0;
        text-transform: uppercase;
    }

    .summary-value {
        color: #0f172a;
        font-size: 1.75rem;
        margin: 0.25rem 0;
    }

    .summary-detail {
        color: #94a3b8;
        font-size: 0.8rem;
        margin: 0;
    }

    .summary-icon {
        align-items: center;
        background: #eff6ff;
        border-radius: 0.75rem;
        color: #1d4ed8;
        display: flex;
        flex: 0 0 48px;
        font-size: 1rem;
        font-weight: 800;
        height: 48px;
        justify-content: center;
    }

    .content-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .filter-section {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.15rem;
    }

    .filter-form {
        align-items: flex-end;
        display: grid;
        gap: 0.85rem;
        grid-template-columns:
            minmax(260px, 2fr)
            minmax(160px, 1fr)
            minmax(160px, 1fr)
            auto;
    }

    .filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .filter-field label {
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .filter-field input,
    .filter-field select {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.6rem;
        color: #0f172a;
        font-family: inherit;
        font-size: 0.875rem;
        min-height: 42px;
        outline: none;
        padding: 0.6rem 0.75rem;
        width: 100%;
    }

    .filter-field input:focus,
    .filter-field select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .search-input-wrapper {
        position: relative;
    }

    .search-input-wrapper input {
        padding-left: 2.2rem;
    }

    .search-icon {
        color: #94a3b8;
        left: 0.75rem;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
    }

    .filter-actions {
        display: flex;
        gap: 0.55rem;
    }

    .table-section-header {
        align-items: center;
        display: flex;
        justify-content: space-between;
        padding: 1rem 1.15rem;
    }

    .table-section-header h2 {
        color: #0f172a;
        font-size: 1rem;
        margin: 0;
    }

    .table-section-header p {
        color: #64748b;
        font-size: 0.8rem;
        margin: 0.3rem 0 0;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .users-table {
        border-collapse: collapse;
        min-width: 980px;
        width: 100%;
    }

    .users-table th {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        border-top: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        padding: 0.8rem 1rem;
        text-align: left;
        text-transform: uppercase;
    }

    .users-table td {
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.84rem;
        padding: 0.95rem 1rem;
        vertical-align: middle;
    }

    .users-table tbody tr:hover {
        background: #f8fafc;
    }

    .user-cell {
        align-items: center;
        display: flex;
        gap: 0.75rem;
        min-width: 220px;
    }

    .user-avatar {
        align-items: center;
        background: #dbeafe;
        border-radius: 999px;
        color: #1d4ed8;
        display: flex;
        flex: 0 0 42px;
        font-size: 0.95rem;
        font-weight: 800;
        height: 42px;
        justify-content: center;
    }

    .user-information {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 0;
    }

    .user-information strong {
        color: #0f172a;
    }

    .user-information > span {
        color: #64748b;
        font-size: 0.78rem;
        overflow-wrap: anywhere;
    }

    .user-name-row {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .current-user-badge {
        background: #ede9fe;
        border-radius: 999px;
        color: #6d28d9;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 0.18rem 0.45rem;
        text-transform: uppercase;
    }

    .role-badge {
        background: #eef2ff;
        border-radius: 999px;
        color: #4338ca;
        display: inline-flex;
        font-size: 0.73rem;
        font-weight: 700;
        padding: 0.35rem 0.65rem;
    }

    .role-unassigned {
        background: #f1f5f9;
        color: #64748b;
    }

    .status-badge {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.72rem;
        font-weight: 700;
        gap: 0.4rem;
        padding: 0.35rem 0.65rem;
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

    .approval-status {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .approval-status.approved {
        color: #047857;
    }

    .approval-status.pending {
        color: #b45309;
    }

    .date-detail {
        color: #94a3b8;
        display: block;
        font-size: 0.7rem;
        margin-top: 0.2rem;
    }

    .last-login-date {
        color: #334155;
        display: block;
        font-size: 0.78rem;
    }

    .never-logged-in {
        color: #94a3b8;
        font-size: 0.76rem;
    }

    .actions-column {
        text-align: right !important;
    }

    .action-menu {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        justify-content: flex-end;
        min-width: 270px;
    }

    .inline-form {
        display: inline-flex;
        margin: 0;
    }

    .action-button {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.45rem;
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 0.7rem;
        font-weight: 700;
        justify-content: center;
        line-height: 1;
        min-height: 31px;
        padding: 0.55rem 0.65rem;
        text-decoration: none;
        transition: 0.15s ease;
    }

    .edit-button {
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    .edit-button:hover {
        background: #eff6ff;
    }

    .reset-button {
        border-color: #ddd6fe;
        color: #6d28d9;
    }

    .reset-button:hover {
        background: #f5f3ff;
    }

    .activate-button {
        border-color: #bbf7d0;
        color: #15803d;
    }

    .activate-button:hover {
        background: #f0fdf4;
    }

    .deactivate-button {
        border-color: #fde68a;
        color: #b45309;
    }

    .deactivate-button:hover {
        background: #fffbeb;
    }

    .delete-button {
        border-color: #fecaca;
        color: #dc2626;
    }

    .delete-button:hover {
        background: #fef2f2;
    }

    .protected-account-label {
        color: #94a3b8;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.5rem;
    }

    .empty-state {
        align-items: center;
        display: flex;
        flex-direction: column;
        padding: 3rem 1rem;
        text-align: center;
    }

    .empty-state-icon {
        align-items: center;
        background: #f1f5f9;
        border-radius: 999px;
        color: #64748b;
        display: flex;
        font-weight: 800;
        height: 54px;
        justify-content: center;
        margin-bottom: 1rem;
        width: 54px;
    }

    .empty-state h3 {
        color: #0f172a;
        margin: 0;
    }

    .empty-state p {
        color: #64748b;
        margin: 0.5rem 0 0;
    }

    .empty-state-actions {
        display: flex;
        gap: 0.6rem;
        margin-top: 1rem;
    }

    .pagination-wrapper {
        border-top: 1px solid #e2e8f0;
        padding: 1rem 1.15rem;
    }

    .mobile-user-list {
        display: none;
        gap: 0.85rem;
        padding: 0 1rem 1rem;
    }

    .mobile-user-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 1rem;
    }

    .mobile-user-header {
        align-items: flex-start;
        display: flex;
        gap: 0.75rem;
        justify-content: space-between;
    }

    .mobile-user-details {
        border-bottom: 1px solid #e2e8f0;
        border-top: 1px solid #e2e8f0;
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(3, 1fr);
        margin: 1rem 0;
        padding: 0.8rem 0;
    }

    .mobile-user-details div {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .mobile-user-details dt {
        color: #94a3b8;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .mobile-user-details dd {
        color: #334155;
        font-size: 0.78rem;
        margin: 0;
    }

    .mobile-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    @media (max-width: 1100px) {
        .filter-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .filter-actions {
            align-self: flex-end;
        }
    }

    @media (max-width: 820px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .table-responsive {
            display: none;
        }

        .mobile-user-list {
            display: grid;
        }

        .table-section-header {
            border-bottom: 1px solid #e2e8f0;
        }
    }

    @media (max-width: 640px) {
        .page-header {
            flex-direction: column;
        }

        .page-header-actions,
        .page-header-actions .button {
            width: 100%;
        }

        .filter-form {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            width: 100%;
        }

        .filter-actions .button {
            flex: 1;
        }

        .mobile-user-header {
            flex-direction: column;
        }

        .mobile-user-details {
            grid-template-columns: 1fr;
        }

        .mobile-actions,
        .mobile-actions .inline-form,
        .mobile-actions .action-button {
            width: 100%;
        }

        .password-display,
        .password-display code,
        .password-display .button {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function confirmPasswordReset(userName) {
        return window.confirm(
            'Reset the password for "' + userName + '"?\n\n' +
            'A new temporary password will be generated.'
        );
    }

    function confirmStatusChange(userName, willActivate) {
        const action = willActivate ? 'activate' : 'deactivate';

        return window.confirm(
            'Are you sure you want to ' + action +
            ' the account of "' + userName + '"?'
        );
    }

    function confirmUserDeletion(userName) {
        return window.confirm(
            'Permanently delete the account of "' + userName + '"?\n\n' +
            'This action cannot be undone.'
        );
    }

    async function copyTemporaryPassword() {
        const passwordElement = document.getElementById(
            'temporary-password'
        );

        const copyMessage = document.getElementById('copy-message');

        if (!passwordElement) {
            return;
        }

        const password = passwordElement.textContent.trim();

        try {
            await navigator.clipboard.writeText(password);

            copyMessage.textContent =
                'Temporary password copied to clipboard.';
        } catch (error) {
            const textArea = document.createElement('textarea');

            textArea.value = password;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';

            document.body.appendChild(textArea);

            textArea.select();
            document.execCommand('copy');
            textArea.remove();

            copyMessage.textContent =
                'Temporary password copied to clipboard.';
        }
    }
</script>
@endpush