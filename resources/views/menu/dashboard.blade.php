<!-- resources/views/menu/dashboard.blade.php -->
@extends('layouts.main')

@section('title', 'Dashboard')

@section('content')
    <div x-data="dashboardData()" class="space-y-6 kp-fade-in">

        <!-- Page header -->
        <header>
            <div class="kp-page-header" style="margin-bottom: 14px">
                <div>
                    <p class="kp-eyebrow">Crop production overview</p>
                    <h1 class="kp-page-title mt-1">Welcome back, {{ auth()->user()->fname }}</h1>
                    <p class="kp-page-sub">Season <span class="font-semibold tabular" style="color: var(--kp-text)"
                            x-text="stats.currentCropYear"></span> at a glance.</p>
                </div>

                <!-- Live clock -->
                <div x-data="clockData()" x-init="startClock()" class="flex items-center gap-4 rounded-lg border px-4 py-2.5"
                    style="border-color: var(--kp-border); background: var(--kp-surface)" role="timer" aria-label="Current date and time">
                    <div class="text-right leading-tight">
                        <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--kp-faint)" x-text="dayName"></p>
                        <p class="text-sm font-semibold tabular" style="color: var(--kp-text)" x-text="dateFormatted"></p>
                    </div>
                    <div class="h-8 w-px" style="background: var(--kp-border)"></div>
                    <p class="font-mono text-xl font-medium tabular" style="color: var(--kp-forest)" x-text="timeFormatted"></p>
                </div>
            </div>
            <div class="kp-nodes kp-nodes--light" aria-hidden="true"></div>
        </header>

        <!-- Primary figures -->
        <section aria-label="Production and value" class="kp-kpis">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-seedling" aria-hidden="true"></i> Total Net Cane</p>
                <p class="kp-kpi-value" x-text="formatNumber(stats.totalNetCane, 0)"></p>
                <p class="kp-kpi-foot">
                    <span>Tons</span>
                    <span class="kp-delta"
                        :style="stats.caneChange < 0 ? 'background:#FCEEEE;color:#832727' : ''">
                        <span x-text="stats.caneChange >= 0 ? '↑' : '↓'" aria-hidden="true"></span>
                        <span x-text="Math.abs(stats.caneChange) + '%'"></span>
                    </span>
                </p>
            </div>

            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Net Amount</p>
                <p class="kp-kpi-value"><small>₱</small><span x-text="formatNumber(stats.totalNetAmount, 0)"></span></p>
                <p class="kp-kpi-foot"><span>Total value, Philippine Peso</span></p>
            </div>

            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-users" aria-hidden="true"></i> Total Planters</p>
                <p class="kp-kpi-value" x-text="stats.totalPlanters"></p>
                <p class="kp-kpi-foot"><span>Registered members</span></p>
            </div>

            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-tags" aria-hidden="true"></i> Quedan Price</p>
                <p class="kp-kpi-value"><small>₱</small><span x-text="formatNumber(stats.quedanPrice, 2)"></span></p>
                <p class="kp-kpi-foot">
                    <span>per lkg</span>
                    <span class="kp-delta" x-text="'Type ' + stats.quedanType"></span>
                </p>
            </div>
        </section>

        <!-- Secondary figures -->
        <section aria-label="Prices and portfolio" class="kp-kpis kp-kpis--quiet">
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-flask" aria-hidden="true"></i> Molasses Price</p>
                <p class="kp-kpi-value"><small>₱</small><span x-text="formatNumber(stats.molassesPrice, 2)"></span></p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i> Active Loans</p>
                <p class="kp-kpi-value" x-text="stats.activeLoans"></p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-chart-line" aria-hidden="true"></i> Avg per Planter</p>
                <p class="kp-kpi-value">
                    <span x-text="stats.activePlanters > 0 ? formatNumber(stats.totalNetCane / stats.activePlanters, 1) : '0'"></span>
                    <small>tons</small>
                </p>
            </div>
            <div class="kp-kpi">
                <p class="kp-kpi-label"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Crop Year</p>
                <p class="kp-kpi-value" x-text="stats.currentCropYear"></p>
            </div>
        </section>

        <!-- Charts -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="kp-card" aria-labelledby="yearly-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="yearly-h" class="kp-card-title">Yearly Production</h2>
                        <p class="kp-card-sub">Net cane production by crop year</p>
                    </div>
                    <span class="kp-delta">Total: <span x-text="formatNumber(stats.totalNetCane, 0) + ' tons'"></span></span>
                </div>
                <div class="relative w-full p-5" style="height: 340px;">
                    <canvas id="yearlyChart" role="img" aria-label="Bar chart of net cane production by crop year"></canvas>
                </div>
            </section>

            <section class="kp-card" aria-labelledby="weekly-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="weekly-h" class="kp-card-title">Weekly Trend</h2>
                        <p class="kp-card-sub">Production trend per week</p>
                    </div>
                </div>
                <div class="relative w-full p-5" style="height: 340px;">
                    <canvas id="weeklyChart" role="img" aria-label="Line chart of weekly production trend"></canvas>
                </div>
            </section>
        </div>

        <!-- Monthly -->
        <section class="kp-card" aria-labelledby="monthly-h">
            <div class="kp-card-head">
                <div>
                    <h2 id="monthly-h" class="kp-card-title">Monthly Production Total</h2>
                    <p class="kp-card-sub">Total net cane per month for <span x-text="stats.currentCropYear"></span></p>
                </div>
            </div>
            <div class="relative w-full p-5" style="height: 290px;">
                <canvas id="monthlyChart" role="img" aria-label="Bar chart of total net cane per month"></canvas>
            </div>
        </section>

        <!-- Distribution & Loan overview -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="kp-card" aria-labelledby="dist-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="dist-h" class="kp-card-title">Production Distribution</h2>
                        <p class="kp-card-sub">How top planters contribute to total production</p>
                    </div>
                </div>
                <div class="relative flex w-full items-center justify-center p-5" style="height: 320px;">
                    <canvas id="distributionChart" role="img" aria-label="Doughnut chart of production share by top planters"></canvas>
                </div>
            </section>

            <section class="kp-card" aria-labelledby="loan-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="loan-h" class="kp-card-title">Loan Portfolio Overview</h2>
                        <p class="kp-card-sub">Active loans: <span class="tabular" x-text="loanStats.active_count || 0"></span> · Total: ₱<span
                                class="tabular" x-text="formatNumber(loanStats.total_principal || 0, 0)"></span></p>
                    </div>
                </div>
                <div class="relative w-full p-5" style="height: 320px;">
                    <canvas id="loanChart" role="img" aria-label="Doughnut chart of collected versus remaining loan balance"></canvas>
                </div>
            </section>
        </div>

        <!-- Alerts, key metrics, at-risk -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="kp-card p-5" aria-labelledby="alerts-h">
                <h2 id="alerts-h" class="kp-card-title mb-3 flex items-center gap-2">
                    <i class="fas fa-bell text-amber-600" aria-hidden="true"></i> Alerts &amp; Notifications
                </h2>
                <div class="max-h-52 space-y-2 overflow-y-auto" role="list">
                    <template x-for="alert in alerts" :key="alert.title">
                        <div :class="getAlertClass(alert.type)" class="flex items-start gap-3 rounded-lg border p-3 text-sm" role="listitem">
                            <i :class="getAlertIcon(alert.type) + ' mt-0.5'" aria-hidden="true"></i>
                            <div>
                                <p class="font-semibold" x-text="alert.title"></p>
                                <p class="text-xs opacity-80" x-text="alert.message"></p>
                            </div>
                        </div>
                    </template>
                    <div x-show="alerts.length === 0" class="kp-empty" style="padding: 20px 12px">
                        <span class="kp-empty-icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <p class="text-sm font-medium" style="color: var(--kp-text)">All systems normal</p>
                        <p class="text-xs">No alerts right now.</p>
                    </div>
                </div>
            </section>

            <section class="kp-card p-5" aria-labelledby="metrics-h">
                <h2 id="metrics-h" class="kp-card-title mb-3 flex items-center gap-2">
                    <i class="fas fa-gauge-high" style="color: var(--kp-emerald)" aria-hidden="true"></i> Key Metrics
                </h2>
                <dl class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg p-3" style="background: var(--kp-surface-2)">
                        <dt class="text-xs" style="color: var(--kp-muted)">Best Week</dt>
                        <dd class="mt-1 text-xl font-semibold text-green-700 tabular"
                            x-text="stats.bestWeek > 0 ? 'Week ' + stats.bestWeek : 'N/A'"></dd>
                        <dd class="text-xs text-gray-500"
                            x-text="stats.bestWeekCane > 0 ? formatNumber(stats.bestWeekCane, 0) + ' tons' : 'No data'"></dd>
                    </div>
                    <div class="rounded-lg p-3" style="background: var(--kp-surface-2)">
                        <dt class="text-xs" style="color: var(--kp-muted)">Loan Collection</dt>
                        <dd class="mt-1 text-xl font-semibold tabular"
                            :class="stats.collectionRate >= 80 ? 'text-green-700' : stats.collectionRate >= 50 ? 'text-amber-600' : 'text-red-600'"
                            x-text="stats.collectionRate + '%'"></dd>
                        <dd class="text-xs text-gray-500">repayment rate</dd>
                    </div>
                    <div class="rounded-lg p-3" style="background: var(--kp-surface-2)">
                        <dt class="text-xs" style="color: var(--kp-muted)">Pending Approvals</dt>
                        <dd class="mt-1 text-xl font-semibold tabular"
                            :class="stats.pendingApprovals > 0 ? 'text-amber-600' : 'text-green-700'"
                            x-text="stats.pendingApprovals"></dd>
                        <dd class="text-xs text-gray-500">loans &amp; requests</dd>
                    </div>
                    <div class="rounded-lg p-3" style="background: var(--kp-surface-2)">
                        <dt class="text-xs" style="color: var(--kp-muted)">Active Loans</dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-900 tabular" x-text="loanStats.active_count || 0"></dd>
                        <dd class="text-xs text-gray-500">currently running</dd>
                    </div>
                </dl>
            </section>

            <section class="kp-card p-5" aria-labelledby="risk-h">
                <h2 id="risk-h" class="kp-card-title mb-1 flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation text-red-600" aria-hidden="true"></i> Low Production Alert
                </h2>
                <p class="mb-3 text-xs" style="color: var(--kp-muted)">Planters with less than 5 tons this season</p>
                <div class="max-h-48 space-y-2 overflow-y-auto">
                    <template x-for="planter in riskPlanters" :key="planter.planter_code">
                        <div class="flex items-center justify-between rounded-lg bg-red-50 px-3 py-2">
                            <span class="text-sm font-medium text-gray-900" x-text="planter.planter_name"></span>
                            <span class="text-xs font-semibold text-red-600 tabular"
                                x-text="formatNumber(planter.total_cane, 2) + ' tons'"></span>
                        </div>
                    </template>
                    <div x-show="riskPlanters.length === 0" class="kp-empty" style="padding: 20px 12px">
                        <span class="kp-empty-icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <p class="text-sm font-medium" style="color: var(--kp-text)">All planters performing well</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Top planters & activity -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <section class="kp-card lg:col-span-3" aria-labelledby="top-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="top-h" class="kp-card-title">Top Planters</h2>
                        <p class="kp-card-sub">Highest contributors this season</p>
                    </div>
                </div>
                <div class="divide-y divide-gray-100">
                    <template x-for="(planter, index) in topPlanters" :key="planter.planter_code">
                        <div class="px-5 py-3.5 transition-colors hover:bg-gray-50">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-4">
                                    <div :class="index === 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'"
                                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular">
                                        <span x-text="index + 1"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900" x-text="planter.planter_name"></p>
                                        <p class="text-xs text-gray-400">Planter #<span x-text="planter.planter_code"></span></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 tabular"
                                        x-text="formatNumber(planter.total_cane, 1) + ' tons'"></p>
                                    <p class="text-xs font-medium text-green-700 tabular">₱<span
                                            x-text="formatNumber(planter.total_amount, 0)"></span></p>
                                </div>
                            </div>
                            <div class="mt-2.5 h-1.5 w-full rounded-full bg-gray-100"
                                role="progressbar" aria-label="Share relative to top planter"
                                :aria-valuenow="Math.round(planter.total_cane / topPlanters[0].total_cane * 100)" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-1.5 rounded-full bg-primary-600"
                                    :style="'width: ' + (planter.total_cane / topPlanters[0].total_cane * 100) + '%'"></div>
                            </div>
                        </div>
                    </template>
                    <div x-show="topPlanters.length === 0" class="kp-empty">
                        <span class="kp-empty-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
                        <p class="text-sm font-medium" style="color: var(--kp-text)">No planter data available yet</p>
                    </div>
                </div>
            </section>

            <div class="space-y-6 lg:col-span-2">
                <section class="kp-card" aria-labelledby="act-h">
                    <div class="kp-card-head"><h2 id="act-h" class="kp-card-title">Recent Activities</h2></div>
                    <div class="divide-y divide-gray-100">
                        <template x-for="activity in activities" :key="activity.id">
                            <div class="px-5 py-3 transition-colors hover:bg-gray-50">
                                <div class="flex items-start gap-3">
                                    <div :class="'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ' + activity.bgColor">
                                        <i :class="activity.icon + ' ' + activity.iconColor + ' text-xs'" aria-hidden="true"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-sm text-gray-900" x-text="activity.description"></p>
                                        <p class="mt-0.5 text-xs text-gray-400" x-text="activity.time"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="activities.length === 0" class="kp-empty" style="padding: 28px 20px">
                            <span class="kp-empty-icon"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i></span>
                            <p class="text-sm font-medium" style="color: var(--kp-text)">No recent activity</p>
                        </div>
                    </div>
                </section>

                <section class="kp-card" aria-labelledby="price-h">
                    <div class="kp-card-head">
                        <h2 id="price-h" class="kp-card-title">Price Updates</h2>
                        <span class="text-xs tabular" style="color: var(--kp-muted)" x-text="recentPrices.length + ' records'"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <div class="overflow-y-auto" style="max-height: 260px;">
                            <table class="w-full">
                                <thead class="sticky top-0 z-10">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5 text-left">Type</th>
                                        <th scope="col" class="px-3 py-2.5 text-left">Crop Year</th>
                                        <th scope="col" class="px-3 py-2.5 text-left">Week</th>
                                        <th scope="col" class="px-3 py-2.5 text-left">Date</th>
                                        <th scope="col" class="px-3 py-2.5 text-right">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="price in sortedRecentPrices.slice(0, 5)" :key="price.id">
                                        <tr>
                                            <td class="px-3 py-2.5">
                                                <span :class="price.type === 'Quedan' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'"
                                                    class="rounded-full px-2 py-0.5 text-xs font-semibold" x-text="price.type"></span>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600"
                                                    x-text="price.crop_year"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-sm text-gray-600"><span x-text="'Week ' + price.week_no"></span></td>
                                            <td class="px-3 py-2.5 text-xs text-gray-500" x-text="price.date"></td>
                                            <td class="px-3 py-2.5 text-right text-sm font-semibold text-gray-900">
                                                ₱<span x-text="formatNumber(price.price, 2)"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="recentPrices.length === 0">
                                        <td colspan="5">
                                            <div class="kp-empty" style="padding: 28px 20px">
                                                <span class="kp-empty-icon"><i class="fas fa-tags" aria-hidden="true"></i></span>
                                                <p class="text-sm font-medium" style="color: var(--kp-text)">No price updates yet</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div x-show="recentPrices.length > 5" class="border-t px-5 py-2.5 text-center"
                        style="border-color: var(--kp-border); background: var(--kp-surface-2)">
                        <span class="text-xs" style="color: var(--kp-muted)">
                            Showing 5 of <span x-text="recentPrices.length"></span> records. Scroll for more.
                        </span>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script src="{{ asset('js/dashboard.js') }}"></script>
    @endpush
@endsection
