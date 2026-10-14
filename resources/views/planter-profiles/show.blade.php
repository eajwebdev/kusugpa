<!-- resources/views/planter-profiles/show.blade.php -->
@extends('layouts.main')

@section('title', 'Planter Profile - ' . $planter->planter_name)

@section('content')
    <div class="space-y-6 kp-fade-in">
        <!-- Page Header -->
        <header>
            <div class="kp-page-header">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="{{ route('planter-profiles.index') }}"
                            class="kp-btn kp-btn-secondary py-1 px-2.5 text-xs inline-flex items-center gap-1.5">
                            <i class="fas fa-arrow-left text-[11px]"></i>
                            <span>Planters</span>
                        </a>
                        <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded border"
                            style="background: var(--kp-surface-2); border-color: var(--kp-border); color: var(--kp-muted)">
                            Code: {{ $planter->planter_code }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="kp-page-title">{{ $planter->planter_name }}</h1>

                        <!-- Status Badge -->
                        @if ($planter->status === 'active')
                            <span class="kp-badge kp-badge-emerald">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Active Member
                            </span>
                        @elseif($planter->status === 'suspended')
                            <span class="kp-badge kp-badge-rose">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                Suspended
                            </span>
                        @else
                            <span class="kp-badge kp-badge-neutral">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                Inactive
                            </span>
                        @endif
                    </div>
                    <p class="kp-page-sub mt-1">Sugarcane production history and membership profile.</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('planter-profiles.index') }}" class="kp-btn kp-btn-secondary">
                        <i class="fas fa-list mr-1.5"></i> Directory
                    </a>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Production History Table -->
            <div class="lg:col-span-2 space-y-6">
                <div class="kp-card overflow-hidden">
                    <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--kp-border)">
                        <div>
                            <h2 class="font-display text-base font-semibold" style="color: var(--kp-text)">Production History</h2>
                            <p class="text-xs" style="color: var(--kp-muted)">Recorded deliveries and net proceeds by week.</p>
                        </div>
                        <i class="fas fa-seedling text-base" style="color: var(--kp-forest)"></i>
                    </div>

                    <div class="kp-table-wrap">
                        <table class="kp-table">
                            <thead>
                                <tr>
                                    <th>Crop Year</th>
                                    <th>Week</th>
                                    <th class="text-right">Net Cane</th>
                                    <th class="text-right">Net Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productionHistory as $ph)
                                    <tr class="hover:bg-[var(--kp-hover)] transition-colors">
                                        <td class="font-medium" style="color: var(--kp-text)">{{ $ph->crop_year }}</td>
                                        <td style="color: var(--kp-muted)">Week {{ $ph->week_no }}</td>
                                        <td class="text-right font-mono font-medium tabular" style="color: var(--kp-text)">
                                            {{ number_format($ph->total_cane, 3) }} tons
                                        </td>
                                        <td class="text-right font-mono font-semibold tabular" style="color: var(--kp-emerald)">
                                            ₱{{ number_format($ph->total_amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-12 text-center" style="color: var(--kp-muted)">
                                            <i class="fas fa-tractor text-3xl mb-2 block opacity-30"></i>
                                            No production records found for this planter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Summaries & Member Info -->
            <div class="space-y-6">
                <!-- Yearly Totals Card -->
                <div class="kp-card p-5">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b" style="border-color: var(--kp-border)">
                        <h3 class="font-display text-sm font-semibold uppercase tracking-wider" style="color: var(--kp-text)">
                            Yearly Totals
                        </h3>
                        <i class="fas fa-chart-line text-xs" style="color: var(--kp-forest)"></i>
                    </div>

                    <div class="space-y-2.5">
                        @forelse ($yearlyTotals as $yt)
                            <div class="flex justify-between items-center py-1.5 border-b text-xs" style="border-color: var(--kp-border)">
                                <span class="font-medium" style="color: var(--kp-muted)">{{ $yt->crop_year }}</span>
                                <span class="font-mono font-semibold tabular" style="color: var(--kp-text)">
                                    {{ number_format($yt->total_cane, 2) }} tons
                                </span>
                            </div>
                        @empty
                            <p class="text-xs py-2" style="color: var(--kp-muted)">No annual totals available.</p>
                        @endforelse

                        <div class="flex justify-between items-center pt-3 mt-1 border-t text-sm font-bold"
                            style="border-color: var(--kp-border); color: var(--kp-forest)">
                            <span>Lifetime Total</span>
                            <span class="font-mono tabular">
                                {{ number_format($totalCane, 2) }} t <span class="opacity-40">|</span> ₱{{ number_format($totalAmount, 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Planter Info Card -->
                <div class="kp-card p-5">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b" style="border-color: var(--kp-border)">
                        <h3 class="font-display text-sm font-semibold uppercase tracking-wider" style="color: var(--kp-text)">
                            Member Information
                        </h3>
                        <i class="fas fa-id-card text-xs" style="color: var(--kp-forest)"></i>
                    </div>

                    <dl class="space-y-3 text-xs">
                        <div class="flex justify-between items-start gap-2 py-1 border-b" style="border-color: var(--kp-border)">
                            <dt class="font-medium" style="color: var(--kp-faint)">Planter Code</dt>
                            <dd class="font-mono font-semibold" style="color: var(--kp-text)">{{ $planter->planter_code }}</dd>
                        </div>
                        <div class="flex justify-between items-start gap-2 py-1 border-b" style="border-color: var(--kp-border)">
                            <dt class="font-medium" style="color: var(--kp-faint)">Contact</dt>
                            <dd class="font-medium text-right" style="color: var(--kp-text)">{{ $planter->contact_number ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between items-start gap-2 py-1 border-b" style="border-color: var(--kp-border)">
                            <dt class="font-medium" style="color: var(--kp-faint)">Area Location</dt>
                            <dd class="font-medium text-right" style="color: var(--kp-text)">{{ $planter->area_location ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between items-start gap-2 py-1 border-b" style="border-color: var(--kp-border)">
                            <dt class="font-medium" style="color: var(--kp-faint)">Address</dt>
                            <dd class="font-medium text-right" style="color: var(--kp-text)">{{ $planter->address ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between items-start gap-2 py-1 border-b" style="border-color: var(--kp-border)">
                            <dt class="font-medium" style="color: var(--kp-faint)">Total Land Area</dt>
                            <dd class="font-mono font-medium tabular" style="color: var(--kp-text)">
                                {{ $planter->total_area ? number_format($planter->total_area, 2) . ' ha' : '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between items-start gap-2 py-1">
                            <dt class="font-medium" style="color: var(--kp-faint)">Member Since</dt>
                            <dd class="font-medium" style="color: var(--kp-text)">
                                {{ $planter->membership_date ? $planter->membership_date->format('M d, Y') : '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
