<!-- resources/views/modal/user-modal.blade.php -->
<div x-show="showUserModal" x-transition class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showUserModal = false"></div>
        <div class="kp-modal relative inline-block w-full max-w-md overflow-hidden text-left align-middle shadow-2xl">
            <div class="px-6 py-4 border-b border-[#E3EBE5] dark:border-[#203A2B] flex items-center justify-between bg-[#F7FAF8] dark:bg-[#1A3324]">
                <div class="flex items-center gap-2">
                    <i class="fas fa-user-shield text-[#005B2A] dark:text-[#52C97E]"></i>
                    <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider" x-text="editingUser ? 'Edit User Credentials' : 'Enroll New System User'"></h3>
                </div>
                <button type="button" @click="showUserModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] p-1 rounded-lg">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <form @submit.prevent="saveUser()" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">First Name *</label>
                        <input type="text" x-model="userForm.fname" required
                            class="kp-input w-full py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Middle Name</label>
                        <input type="text" x-model="userForm.mname"
                            class="kp-input w-full py-1.5 text-xs">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Last Name *</label>
                    <input type="text" x-model="userForm.lname" required
                        class="kp-input w-full py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">System Username *</label>
                    <input type="text" x-model="userForm.username" required
                        class="kp-input w-full py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">
                        Password <span x-show="!editingUser" class="text-rose-600">*</span>
                    </label>
                    <input type="password" x-model="userForm.password" :required="!editingUser"
                        class="kp-input w-full py-1.5 text-xs"
                        :placeholder="editingUser ? 'Leave blank to retain current password' : 'Enter login password'">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#52705F] dark:text-[#8AA895] uppercase mb-1">Assigned Role *</label>
                    <select x-model="userForm.role" required
                        class="kp-input w-full py-1.5 text-xs">
                        <option value="">Select Role</option>
                        @if (auth()->user()->role === 'Administrator')
                            <option value="Administrator">Administrator (Master Control)</option>
                        @endif
                        @if (in_array(auth()->user()->role, ['Administrator', 'super_admin']))
                            <option value="super_admin">Super Admin</option>
                        @endif
                        @if (auth()->user()->role === 'Administrator')
                            <option value="manager">Manager</option>
                        @endif
                        <option value="loan_officer">Loan Officer</option>
                        <option value="User">Standard User</option>
                        <option value="Viewer">Read-Only Viewer</option>
                    </select>
                </div>
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                    <button type="button" @click="showUserModal = false"
                        class="kp-btn kp-btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit"
                        class="kp-btn kp-btn-primary text-xs disabled:opacity-50"
                        :disabled="loading">
                        <span x-show="!loading" x-text="editingUser ? 'Update Credentials' : 'Create Account'"></span>
                        <span x-show="loading"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>