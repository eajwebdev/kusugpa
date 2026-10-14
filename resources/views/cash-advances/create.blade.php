<!-- resources/views/cash-advances/create.blade.php -->
@extends('layouts.main')

@section('title', 'New Cash Advance')

@section('content')
<div class="max-w-3xl mx-auto space-y-6 kp-fade-in">
    <!-- Page Header -->
    <header>
        <div class="kp-page-header">
            <div>
                <p class="kp-eyebrow">Financial Services</p>
                <h1 class="kp-page-title mt-1">New Cash Advance</h1>
                <p class="kp-page-sub">File a short-term cash advance application for an enrolled sugarcane planter.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cash-advances.index') }}" class="kp-btn kp-btn-secondary text-xs">
                    <i class="fas fa-arrow-left mr-1.5" aria-hidden="true"></i> Back to Cash Advances
                </a>
            </div>
        </div>
        <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
    </header>

    <div class="kp-card p-6 sm:p-8">
        <form action="{{ route('cash-advances.store') }}" method="POST" class="space-y-6">
            @csrf
            
            <div class="flex items-center gap-2.5 pb-3 border-b border-[#E3EBE5] dark:border-[#203A2B]">
                <div class="w-8 h-8 rounded-lg bg-[#EAF5ED] dark:bg-[#1A3324] text-[#005B2A] dark:text-[#52C97E] flex items-center justify-center text-sm font-semibold">
                    <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-xs font-bold text-[#172B20] dark:text-[#EAF5ED] uppercase tracking-wider">Cash Advance Terms & Planter Roster</h2>
                    <p class="text-[11px] text-[#52705F] dark:text-[#8AA895]">Emergency and seasonal crop sustenance disbursement parameters.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <!-- Planter -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-user-check text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Select Planter <span class="text-rose-600">*</span>
                    </label>
                    <select name="planter_code" id="planter_select" required
                        class="kp-input w-full" onchange="updatePlanterName()">
                        <option value="">Select Planter</option>
                        @foreach($planters as $p)
                            <option value="{{ $p->planter_code }}" data-name="{{ $p->planter_name }}">{{ $p->planter_name }} ({{ $p->planter_code }})</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="planter_name" id="planter_name">
                </div>

                <!-- Crop Year -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-calendar-alt text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Crop Year <span class="text-rose-600">*</span>
                    </label>
                    <select name="crop_year" required class="kp-input w-full">
                        @foreach($cropYears as $cy)
                            <option value="{{ $cy }}">{{ $cy }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Principal Amount -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-coins text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Advance Amount (₱) <span class="text-rose-600">*</span>
                    </label>
                    <input type="number" name="amount" step="0.01" min="{{ $settings['min_amount'] }}" max="{{ $settings['max_amount'] }}" required
                        class="kp-input w-full" placeholder="Min: ₱{{ number_format($settings['min_amount'], 2) }}">
                </div>

                <!-- Interest Rate -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-percent text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Interest Rate (%) <span class="text-rose-600">*</span>
                    </label>
                    <input type="number" name="interest_rate" step="0.01" value="{{ $settings['default_interest'] }}" required
                        class="kp-input w-full">
                </div>

                <!-- Term Months -->
                <div>
                    <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                        <i class="fas fa-clock text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Term (Months) <span class="text-rose-600">*</span>
                    </label>
                    <input type="number" name="term_months" min="1" max="{{ $settings['max_term'] }}" value="3" required
                        class="kp-input w-full">
                </div>
            </div>

            <!-- Purpose -->
            <div>
                <label class="block text-xs font-semibold text-[#52705F] dark:text-[#8AA895] uppercase tracking-wider mb-1.5">
                    <i class="fas fa-file-lines text-[#087A3D] dark:text-[#52C97E] mr-1" aria-hidden="true"></i> Purpose / Stated Reason
                </label>
                <textarea name="purpose" rows="3" class="kp-input w-full" placeholder="e.g. Immediate harvester payroll, tractor fueling, emergency medical expense..."></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#E3EBE5] dark:border-[#203A2B]">
                <a href="{{ route('cash-advances.index') }}" class="kp-btn kp-btn-secondary">Cancel</a>
                <button type="submit" class="kp-btn kp-btn-primary">
                    <i class="fas fa-check mr-1.5" aria-hidden="true"></i> Submit Cash Advance
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function updatePlanterName() {
        const select = document.getElementById('planter_select');
        const selected = select.options[select.selectedIndex];
        document.getElementById('planter_name').value = selected.getAttribute('data-name') || '';
    }
</script>
@endpush
@endsection