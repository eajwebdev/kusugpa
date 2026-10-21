<!-- resources/views/user-management/index.blade.php -->
@extends('layouts.main')

@section('title', 'User Management')

@section('content')
    <div x-data="userManagementData()" class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <p class="kp-eyebrow">Access Control & Security</p>
                    <h1 class="kp-page-title mt-1">User Management</h1>
                    <p class="kp-page-sub">Manage system operator accounts, assigned roles, and granular module permissions.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-mono font-medium px-3 py-1.5 rounded-lg border"
                        style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)">
                        Total Users: <strong class="tabular" x-text="users.length"></strong>
                    </span>
                    <button @click="openCreateModal()" class="kp-btn kp-btn-primary">
                        <i class="fas fa-plus-circle mr-1.5" aria-hidden="true"></i> Add User
                    </button>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Search Bar Card -->
        <div class="kp-card p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="font-display text-base font-semibold" style="color: var(--kp-text)">Operator Accounts</h2>
                    <p class="text-xs" style="color: var(--kp-muted)">Active authorized profiles with assigned permissions.</p>
                </div>
                <div class="relative w-full sm:w-80">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs pointer-events-none" style="color: var(--kp-faint)"></i>
                    <input type="text" x-model="searchQuery" @input="filterUsers()"
                        class="kp-input w-full pl-9"
                        placeholder="Search name, username, or role...">
                </div>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="kp-card overflow-hidden">
            <div class="kp-table-wrap" style="max-height: 640px; overflow-y: auto;">
                <table class="kp-table">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Assigned Permissions</th>
                            <th>Created Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(user, index) in filteredUsers" :key="user.id">
                            <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                <td class="font-mono text-xs" style="color: var(--kp-muted)" x-text="index + 1"></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-lg flex items-center justify-center font-bold text-xs font-mono border"
                                            style="background: var(--kp-soft); border-color: var(--kp-border); color: var(--kp-forest)">
                                            <span x-text="getInitials(user)"></span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-sm" style="color: var(--kp-text)"
                                                x-text="user.fname + ' ' + (user.lname || '')"></p>
                                            <p class="text-xs font-mono" style="color: var(--kp-muted)" x-text="'@' + user.username"></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span :class="getRoleBadgeClass(user.role).badge" x-text="user.role"></span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <template x-if="user.role === 'Administrator' || user.role === 'super_admin'">
                                            <span class="kp-badge kp-badge-emerald font-semibold">
                                                <i class="fas fa-shield-alt text-[10px] mr-1"></i> Full System Access
                                            </span>
                                        </template>
                                        <template x-if="user.role !== 'Administrator' && user.role !== 'super_admin' && user.permissions && user.permissions.length > 0">
                                            <template x-for="perm in user.permissions" :key="perm.id">
                                                <span class="px-2 py-0.5 text-[11px] rounded font-medium border"
                                                    style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-text)"
                                                    x-text="formatPermission(perm.name)"></span>
                                            </template>
                                        </template>
                                        <template x-if="user.role !== 'Administrator' && user.role !== 'super_admin' && (!user.permissions || user.permissions.length === 0)">
                                            <span class="text-xs italic" style="color: var(--kp-faint)">No custom permissions</span>
                                        </template>
                                    </div>
                                </td>
                                <td class="text-xs font-mono" style="color: var(--kp-muted)" x-text="formatDate(user.created_at)"></td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Permissions Button -->
                                        <button @click="openPermissionsModal(user)" :disabled="!canManagePermissions(user)"
                                            class="p-1.5 rounded-md transition-colors hover:bg-[var(--kp-surface-2)] disabled:opacity-40 disabled:cursor-not-allowed"
                                            style="color: var(--kp-forest)"
                                            :title="canManagePermissions(user) ? 'Manage Permissions' : 'Inherits default administrative role'">
                                            <i class="fas fa-shield-alt text-sm"></i>
                                        </button>

                                        <!-- Edit Button -->
                                        <button @click="openEditModal(user)" :disabled="!canEditUser(user)"
                                            class="p-1.5 rounded-md transition-colors hover:bg-[var(--kp-surface-2)] disabled:opacity-40 disabled:cursor-not-allowed"
                                            style="color: var(--kp-cane)"
                                            :title="canEditUser(user) ? 'Edit User' : 'Cannot edit this user'">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button @click="deleteUser(user)" :disabled="!canDeleteUser(user)"
                                            class="p-1.5 rounded-md transition-colors hover:bg-[var(--kp-surface-2)] disabled:opacity-40 disabled:cursor-not-allowed text-red-600"
                                            :title="canDeleteUser(user) ? 'Delete User' : 'Cannot delete this user'">
                                            <i class="fas fa-trash-alt text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredUsers.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                <i class="fas fa-users text-3xl mb-2 block opacity-30"></i>
                                No operator records found matching the query.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="px-4 py-3 border-t flex items-center justify-between"
                style="border-color: var(--kp-border); background: var(--kp-surface-2)">
                <p class="text-xs font-mono" style="color: var(--kp-muted)">
                    Showing <span class="font-semibold" style="color: var(--kp-text)" x-text="filteredUsers.length"></span>
                    of <span class="font-semibold" style="color: var(--kp-text)" x-text="users.length"></span> users
                </p>
            </div>
        </div>

        @include('modal.user-modal')
        @include('modal.permissions-modal')
    </div>

    @push('scripts')
        <script>
            function userManagementData() {
                return {
                    users: @json($users),
                    allPermissions: @json($allPermissions ?? []),
                    filteredUsers: [],
                    searchQuery: '',
                    showUserModal: false,
                    showPermissionsModal: false,
                    editingUser: null,
                    selectedUser: null,
                    loading: false,
                    savingPermissions: false,
                    userForm: {
                        fname: '',
                        mname: '',
                        lname: '',
                        username: '',
                        password: '',
                        role: '',
                    },
                    availablePermissions: [],

                    init() {
                        this.filterUsers();
                        this.availablePermissions = (this.allPermissions || []).map(p => ({
                            id: p.id,
                            slug: p.slug,
                            label: p.name,
                            description: p.description,
                            granted: false
                        }));
                    },

                    getInitials(user) {
                        const first = user.fname ? user.fname.charAt(0).toUpperCase() : '';
                        const last = user.lname ? user.lname.charAt(0).toUpperCase() : '';
                        return (first + last) || '?';
                    },

                    getPermissionsByGroup(group) {
                        const groups = {
                            'reports': ['view-reports'],
                            'uploads': ['upload-summary', 'upload-trucking', 'upload-fci', 'upload-fuel', 'upload-rentals',
                                'upload-underload', 'upload-transloading', 'upload-tls'
                            ],
                            'settings': ['set-quedan-price', 'set-molasses-price', 'set-crop-year', 'set-week-number'],
                            'other': ['print-vouchers'],
                        };
                        const slugs = groups[group] || [];
                        return this.availablePermissions.filter(p => slugs.includes(p.slug));
                    },

                    getPermissionIcon(slug) {
                        const icons = {
                            'view-reports': 'fas fa-chart-bar',
                            'print-vouchers': 'fas fa-print',
                            'upload-summary': 'fas fa-file-csv',
                            'upload-trucking': 'fas fa-truck',
                            'upload-fci': 'fas fa-leaf',
                            'upload-fuel': 'fas fa-gas-pump',
                            'upload-rentals': 'fas fa-building',
                            'upload-underload': 'fas fa-weight-scale',
                            'upload-transloading': 'fas fa-exchange-alt',
                            'upload-tls': 'fas fa-truck-moving',
                            'set-quedan-price': 'fas fa-tags',
                            'set-molasses-price': 'fas fa-flask',
                            'set-crop-year': 'fas fa-calendar-alt',
                            'set-week-number': 'fas fa-calendar-week',
                            'view-loans': 'fas fa-hand-holding-usd',
                            'create-loans': 'fas fa-file-invoice',
                            'approve-loans': 'fas fa-check-double',
                            'process-loan-payments': 'fas fa-coins',
                            'manage-loan-settings': 'fas fa-cogs',
                            'access-admin-panel': 'fas fa-shield-halved',
                            'manage-users': 'fas fa-users-cog',
                        };
                        return icons[slug] || 'fas fa-lock';
                    },

                    getGrantedCount() {
                        return this.availablePermissions.filter(p => p.granted).length;
                    },

                    selectAllPermissions() {
                        this.availablePermissions.forEach(p => p.granted = true);
                    },

                    deselectAllPermissions() {
                        this.availablePermissions.forEach(p => p.granted = false);
                    },

                    getRoleBadgeClass(role) {
                        const roles = {
                            'Administrator': {
                                badge: 'kp-badge kp-badge-rose',
                                avatar: 'bg-red-50 text-red-700'
                            },
                            'super_admin': {
                                badge: 'kp-badge kp-badge-info',
                                avatar: 'bg-indigo-50 text-indigo-700'
                            },
                            'manager': {
                                badge: 'kp-badge kp-badge-emerald',
                                avatar: 'bg-emerald-50 text-emerald-700'
                            },
                            'loan_officer': {
                                badge: 'kp-badge kp-badge-amber',
                                avatar: 'bg-amber-50 text-amber-700'
                            },
                            'User': {
                                badge: 'kp-badge kp-badge-neutral',
                                avatar: 'bg-gray-100 text-gray-700'
                            },
                            'Viewer': {
                                badge: 'kp-badge kp-badge-neutral',
                                avatar: 'bg-gray-100 text-gray-700'
                            },
                        };
                        return roles[role] || {
                            badge: 'kp-badge kp-badge-neutral',
                            avatar: 'bg-gray-100 text-gray-700'
                        };
                    },

                    canEditUser(user) {
                        const currentRole = '{{ auth()->user()->role }}';
                        const currentUserId = {{ auth()->id() }};
                        if (currentRole === 'Administrator') return true;
                        if (currentRole === 'super_admin') {
                            if (user.id === currentUserId) return true;
                            return !['Administrator', 'super_admin'].includes(user.role);
                        }
                        return false;
                    },

                    canDeleteUser(user) {
                        const currentRole = '{{ auth()->user()->role }}';
                        const currentUserId = {{ auth()->id() }};
                        if (user.id === currentUserId) return false;
                        if (currentRole === 'Administrator') return true;
                        if (currentRole === 'super_admin') {
                            return !['Administrator', 'super_admin'].includes(user.role);
                        }
                        return false;
                    },

                    canManagePermissions(user) {
                        const currentRole = '{{ auth()->user()->role }}';
                        if (user.role === 'Administrator' || user.role === 'super_admin') return false;
                        if (currentRole === 'Administrator') return true;
                        if (currentRole === 'super_admin') {
                            return ['User', 'Viewer'].includes(user.role);
                        }
                        return false;
                    },

                    formatDate(date) {
                        if (!date) return '';
                        return new Date(date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        });
                    },

                    formatPermission(slug) {
                        return slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    },

                    filterUsers() {
                        const query = this.searchQuery.toLowerCase();
                        this.filteredUsers = this.users.filter(user => {
                            return (user.fname || '').toLowerCase().includes(query) ||
                                (user.lname || '').toLowerCase().includes(query) ||
                                (user.username || '').toLowerCase().includes(query) ||
                                (user.role || '').toLowerCase().includes(query);
                        });
                    },

                    openCreateModal() {
                        this.editingUser = null;
                        this.userForm = {
                            fname: '',
                            mname: '',
                            lname: '',
                            username: '',
                            password: '',
                            role: ''
                        };
                        this.showUserModal = true;
                    },

                    openEditModal(user) {
                        this.editingUser = user;
                        this.userForm = {
                            fname: user.fname || '',
                            mname: user.mname || '',
                            lname: user.lname || '',
                            username: user.username || '',
                            password: '',
                            role: user.role || '',
                        };
                        this.showUserModal = true;
                    },

                    async saveUser() {
                        this.loading = true;
                        try {
                            const url = this.editingUser ?
                                `{{ url('/user-management') }}/${this.editingUser.id}` :
                                '{{ url('/user-management') }}';

                            const method = this.editingUser ? 'PUT' : 'POST';
                            const formData = {
                                ...this.userForm
                            };
                            if (!formData.password && this.editingUser) {
                                delete formData.password;
                            }

                            const response = await fetch(url, {
                                method: method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(formData)
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                if (data.errors) {
                                    const errorMessages = Object.values(data.errors).flat().join('\n');
                                    throw new Error(errorMessages);
                                }
                                throw new Error(data.message || 'Failed to save');
                            }

                            if (this.editingUser) {
                                const index = this.users.findIndex(u => u.id === this.editingUser.id);
                                if (index !== -1) this.users[index] = data.user;
                            } else {
                                this.users.unshift(data.user);
                            }

                            this.showUserModal = false;
                            this.filterUsers();

                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Something went wrong'
                            });
                        } finally {
                            this.loading = false;
                        }
                    },

                    async loadPermissions() {
                        try {
                            const response = await fetch('{{ route('permissions.list') }}', {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            const data = await response.json();
                            this.availablePermissions = data.map(p => ({
                                ...p,
                                granted: this.selectedUser?.permissions?.some(up => up.slug === p.slug) || false
                            }));
                        } catch (error) {
                            console.error('Error loading permissions:', error);
                        }
                    },

                    async deleteUser(user) {
                        const result = await Swal.fire({
                            title: 'Delete User?',
                            text: `Are you sure you want to delete ${user.fname} ${user.lname || ''}? This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#832727',
                        });

                        if (!result.isConfirmed) return;

                        try {
                            const response = await fetch(`{{ url('/user-management') }}/${user.id}`, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });

                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message);

                            this.users = this.users.filter(u => u.id !== user.id);
                            this.filterUsers();

                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        }
                    },

                    openPermissionsModal(user) {
                        if (user.role === 'Administrator' || user.role === 'super_admin') return;

                        this.selectedUser = user;
                        this.availablePermissions = this.allPermissions.map(p => ({
                            id: p.id,
                            slug: p.slug,
                            label: p.name,
                            description: p.description,
                            granted: user.permissions?.some(up => up.id === p.id) || false
                        }));

                        this.showPermissionsModal = true;
                    },

                    togglePermission(perm) {
                        perm.granted = !perm.granted;
                    },

                    async savePermissions() {
                        if (!this.selectedUser) return;
                        this.savingPermissions = true;

                        try {
                            const response = await fetch('{{ route('assign-permissions') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    user_id: this.selectedUser.id,
                                    permissions: this.availablePermissions.map(p => ({
                                        slug: p.slug,
                                        granted: p.granted
                                    }))
                                })
                            });

                            const data = await response.json();
                            if (!response.ok) throw new Error(data.message);

                            const index = this.users.findIndex(u => u.id === this.selectedUser.id);
                            if (index !== -1) {
                                this.users[index].permissions = data.permissions;
                            }

                            this.showPermissionsModal = false;
                            this.filterUsers();

                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Permissions updated successfully',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        } finally {
                            this.savingPermissions = false;
                        }
                    }
                }
            }
        </script>
    @endpush
@endsection
