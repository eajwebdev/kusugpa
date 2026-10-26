{{-- Loans & Advances tab: Loans, Cash Advances, Fuel & Others Deduction (each only when permitted) --}}
<div x-show="tab === 'finance'" x-cloak id="panel-finance" role="tabpanel" aria-labelledby="tab-finance" class="space-y-4">
    @include('menu.dashboard.state', ['key' => 'finance'])

    <template x-if="data.finance">
        <div class="space-y-4">
            @include('menu.dashboard.collections', ['withPlanters' => true])

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                <section class="kp-card lg:col-span-2" aria-labelledby="f-mix-h">
                    <div class="kp-card-head"><div>
                        <h2 id="f-mix-h" class="kp-card-title">Outstanding Balance</h2>
                        <p class="kp-card-sub">Open loans and cash advances, all crop years</p>
                    </div></div>
                    <div class="relative w-full p-5 kp-dashboard-donut-body" style="height: 260px">
                        <canvas id="finMixChart" role="img" aria-label="Doughnut chart of outstanding loan and cash advance balances"></canvas>
                        <p class="kp-dashboard-donut-empty" x-show="!((data.finance.loans?.balance || 0) + (data.finance.cashAdvances?.balance || 0))">Nothing outstanding</p>
                    </div>
                </section>
                <section class="kp-card lg:col-span-3" aria-labelledby="f-ca-h" x-show="data.finance.cashAdvances">
                    <div class="kp-card-head">
                        <div>
                            <h2 id="f-ca-h" class="kp-card-title">Cash Advance: Scheduled vs Collected</h2>
                            <p class="kp-card-sub">Weekly repayment schedule of crop year <span x-text="cropYear"></span>, ₱</p>
                        </div>
                        <span class="kp-delta" x-text="pct(data.finance.cashAdvances?.collectedTotal, data.finance.cashAdvances?.scheduledTotal) + '% collected'"></span>
                    </div>
                    <div class="relative w-full p-5" style="height: 260px">
                        <canvas id="finCaChart" role="img" aria-label="Grouped bar chart of cash advance amounts scheduled and collected per week"></canvas>
                        <p class="kp-dashboard-donut-empty" x-show="!data.finance.cashAdvances?.weekly.weeks.length">No weekly cash advance schedule this crop year</p>
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="kp-card p-5" aria-labelledby="f-ls-h" x-show="data.finance.loans">
                    <h2 id="f-ls-h" class="kp-card-title mb-3">Loans by Status</h2>
                    <ul class="space-y-2">
                        <template x-for="s in (data.finance.loans?.statuses || [])" :key="s.status">
                            <li class="flex items-center justify-between text-sm">
                                <span class="kp-badge" :class="statusBadge(s.status)" x-text="s.status"></span>
                                <span class="tabular"><strong x-text="s.count"></strong> · ₱<span x-text="fmt(s.principal, 0)"></span></span>
                            </li>
                        </template>
                    </ul>
                </section>
                <section class="kp-card p-5" aria-labelledby="f-cs-h" x-show="data.finance.cashAdvances">
                    <h2 id="f-cs-h" class="kp-card-title mb-3">Cash Advances by Status</h2>
                    <ul class="space-y-2">
                        <template x-for="s in (data.finance.cashAdvances?.statuses || [])" :key="s.status">
                            <li class="flex items-center justify-between text-sm">
                                <span class="kp-badge" :class="statusBadge(s.status)" x-text="s.status"></span>
                                <span class="tabular"><strong x-text="s.count"></strong> · ₱<span x-text="fmt(s.amount, 0)"></span></span>
                            </li>
                        </template>
                    </ul>
                </section>
                <section class="kp-card p-5" aria-labelledby="f-fd-h" x-show="data.finance.deductions">
                    <h2 id="f-fd-h" class="kp-card-title mb-3">Fuel &amp; Others Deduction</h2>
                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg p-3" style="background: var(--kp-surface-2)">
                            <dt class="text-xs" style="color: var(--kp-muted)">Fuel accounts</dt>
                            <dd class="mt-1 text-lg font-semibold tabular">₱<span x-text="fmt(data.finance.deductions?.fuel.amount, 0)"></span></dd>
                            <dd class="text-xs" style="color: var(--kp-faint)" x-text="(data.finance.deductions?.fuel.count || 0) + ' accounts'"></dd>
                        </div>
                        <div class="rounded-lg p-3" style="background: var(--kp-surface-2)">
                            <dt class="text-xs" style="color: var(--kp-muted)">Others accounts</dt>
                            <dd class="mt-1 text-lg font-semibold tabular">₱<span x-text="fmt(data.finance.deductions?.others.amount, 0)"></span></dd>
                            <dd class="text-xs" style="color: var(--kp-faint)" x-text="(data.finance.deductions?.others.count || 0) + ' accounts'"></dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs" style="color: var(--kp-muted)" x-show="week">
                        Due in week <span x-text="week"></span>: fuel ₱<strong class="tabular" x-text="fmt(data.finance.deductions?.weekFuel, 2)"></strong>,
                        others ₱<strong class="tabular" x-text="fmt(data.finance.deductions?.weekOthers, 2)"></strong></p>
                    <div class="relative mt-3 w-full" style="height: 150px" x-show="data.finance.deductions?.weekly.weeks.length">
                        <canvas id="finDedChart" role="img" aria-label="Stacked bar chart of fuel and others deductions scheduled per week"></canvas>
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <section class="kp-card" aria-labelledby="f-up-h">
                    <div class="kp-card-head"><div>
                        <h2 id="f-up-h" class="kp-card-title">Due in the Next 30 Days</h2>
                        <p class="kp-card-sub">Unpaid loan installments and cash advance weeks</p>
                    </div></div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th scope="col">Due</th><th scope="col">Account</th><th scope="col">Planter</th><th scope="col" class="text-right">Amount</th></tr></thead>
                            <tbody>
                                <template x-for="(u, i) in data.finance.upcoming" :key="i">
                                    <tr>
                                        <td class="tabular whitespace-nowrap" x-text="u.date"></td>
                                        <td><span class="kp-badge" :class="u.module === 'Loan' ? 'kp-badge--forest' : 'kp-badge--info'" x-text="u.module"></span>
                                            <span class="block text-xs" style="color: var(--kp-faint)" x-text="u.reference"></span></td>
                                        <td x-text="u.planter_name"></td>
                                        <td class="text-right tabular font-semibold" x-text="fmt(u.amount, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.finance.upcoming.length"><td colspan="4" class="text-center text-sm" style="color: var(--kp-muted)">Nothing due in the next 30 days</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </template>
</div>
