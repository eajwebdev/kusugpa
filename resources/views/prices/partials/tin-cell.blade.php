{{-- TIN cell of the registry tables: shows the TIN, or lets a missing one be typed in. Expects `row` in Alpine scope. --}}
<template x-if="!isBlankTin(row.tin_no)">
    <span x-text="row.tin_no"></span>
</template>
<template x-if="isBlankTin(row.tin_no)">
    <div>
        <template x-if="!isEditingTin('{{ $type }}', row.id)">
            <button type="button" @click="startTinEdit('{{ $type }}', row)"
                class="kp-badge kp-badge--amber text-[10px] hover:opacity-80" title="No TIN on file. Click to add.">
                <i class="fas fa-pen text-[9px]" aria-hidden="true"></i> Add TIN
            </button>
        </template>
        <template x-if="isEditingTin('{{ $type }}', row.id)">
            <form @submit.prevent="saveTin()" class="flex items-center gap-1">
                <input type="text" x-model.trim="tinEdit.value" x-init="$nextTick(() => $el.focus())"
                    @keydown.escape.prevent="cancelTinEdit()" maxlength="20" inputmode="numeric"
                    placeholder="000-000-000-000" class="kp-input w-36 py-1 px-2 text-xs font-mono">
                <button type="submit" :disabled="tinEdit.saving || !tinEdit.value"
                    class="p-1 rounded text-[#005B2A] dark:text-[#52C97E] hover:bg-[#EAF5ED] dark:hover:bg-[#1A3324] disabled:opacity-40" title="Save TIN">
                    <i class="fas" :class="tinEdit.saving ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                </button>
                <button type="button" @click="cancelTinEdit()"
                    class="p-1 rounded text-[#52705F] dark:text-[#8AA895] hover:bg-[#EAF5ED] dark:hover:bg-[#1A3324]" title="Cancel">
                    <i class="fas fa-times"></i>
                </button>
            </form>
        </template>
    </div>
</template>
