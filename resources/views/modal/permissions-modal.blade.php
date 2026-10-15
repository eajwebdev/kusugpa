<!-- resources/views/modal/permissions-modal.blade.php -->
<div x-show="showPermissionsModal" x-transition class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showPermissionsModal = false"></div>
        <div class="kp-modal relative inline-block w-full max-w-2xl overflow-hidden text-left align-middle shadow-2xl">
            <div class="px-6 py-4 border-b border-[#E3EBE5] dark:border-[#203A2B] flex items-center justify-between bg-[#F7FAF8] dark:bg-[#1A3324]">
                <div>
                    <h3 class="text-sm font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Access Rights & Delegation</h3>
                    <p class="text-xs text-[#52705F] dark:text-[#8AA895]">
                        Configure allowed module operations for <strong class="text-[#005B2A] dark:text-[#52C97E]" x-text="selectedUser?.fname + ' ' + selectedUser?.lname"></strong>
                    </p>
                </div>
                <button type="button" @click="showPermissionsModal = false" class="text-[#52705F] hover:text-[#172B20] dark:text-[#8AA895] p-1.5 rounded-lg">
                    <i class="fas fa-times text-sm" aria-hidden="true"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                    <span class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Module Permissions</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="selectAllPermissions()" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5">
                            <i class="fas fa-check-double mr-1" aria-hidden="true"></i> Select All
                        </button>
                        <button type="button" @click="deselectAllPermissions()" class="kp-btn kp-btn-secondary text-xs py-1 px-2.5 text-rose-600">
                            <i class="fas fa-times-circle mr-1" aria-hidden="true"></i> Clear All
                        </button>
                    </div>
                </div>

                <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                    <template x-for="perm in availablePermissions" :key="perm.slug">
                        <label class="flex items-center justify-between p-3 rounded-xl border border-[#E3EBE5] dark:border-[#203A2B] bg-[#F7FAF8] dark:bg-[#13231B] hover:border-[#005B2A] dark:hover:border-[#52C97E] transition cursor-pointer group">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm flex-shrink-0">
                                    <i :class="getPermissionIcon(perm.slug)"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED]" x-text="perm.label"></p>
                                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895] mt-0.5" x-text="perm.description"></p>
                                </div>
                            </div>
                            <div class="relative flex-shrink-0">
                                <input type="checkbox" :checked="perm.granted" @change="togglePermission(perm)" class="sr-only peer">
                                <div class="w-11 h-6 bg-[#CFDCD3] dark:bg-[#203A2B] rounded-full peer peer-checked:bg-[#005B2A] transition-all duration-200 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow-sm after:transition-all after:duration-200 peer-checked:after:translate-x-full"></div>
                            </div>
                        </label>
                    </template>
                </div>

                <div class="mt-5 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B] flex items-center justify-end gap-3">
                    <button type="button" @click="showPermissionsModal = false" class="kp-btn kp-btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="button" @click="savePermissions()" class="kp-btn kp-btn-primary text-xs disabled:opacity-50" :disabled="savingPermissions">
                        <span x-show="!savingPermissions"><i class="fas fa-save mr-1.5" aria-hidden="true"></i> Save Permissions</span>
                        <span x-show="savingPermissions"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>