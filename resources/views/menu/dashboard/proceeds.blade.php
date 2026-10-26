{{-- Proceeds tab: totals of every planter's Check Voucher for the period (PlanterProceeds) --}}
<div x-show="tab === 'proceeds'" x-cloak id="panel-proceeds" role="tabpanel" aria-labelledby="tab-proceeds" class="space-y-4">
    @include('menu.dashboard.state', ['key' => 'proceeds'])

    <template x-if="data.proceeds">
        <div class="space-y-4">
            <p class="kp-dash-note">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Same amounts as the Check Voucher for
                <strong x-text="data.proceeds.range ? (data.proceeds.range.from === data.proceeds.range.to ? 'week ' + data.proceeds.range.from : 'weeks ' + data.proceeds.range.from + '–' + data.proceeds.range.to) : 'no weeks'"></strong>
                of crop year <strong x-text="data.proceeds.scope.crop_year"></strong> · <span x-text="data.proceeds.planters"></span> planters
            </p>

            <section aria-label="Proceeds figures" class="kp-kpis kp-dashboard-kpis">
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--green">
                    <p class="kp-kpi-label"><i class="fas fa-sack-dollar" aria-hidden="true"></i> Gross Proceeds</p>
                    <p class="kp-kpi-value"><small>₱</small><span class="tabular" x-text="fmt(data.proceeds.totals.gross, 0)"></span></p>
                    <p class="kp-kpi-foot"><span>quedan, molasses &amp; allowances</span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--gold">
                    <p class="kp-kpi-label"><i class="fas fa-file-invoice" aria-hidden="true"></i> Deductions</p>
                    <p class="kp-kpi-value"><small>₱</small><span class="tabular" x-text="fmt(data.proceeds.totals.deductions, 0)"></span></p>
                    <p class="kp-kpi-foot"><span>liens, charges, insurance, tax, fuel</span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--green">
                    <p class="kp-kpi-label"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> Accounts Applied</p>
                    <p class="kp-kpi-value"><small>₱</small><span class="tabular" x-text="fmt(data.proceeds.totals.applied, 0)"></span></p>
                    <p class="kp-kpi-foot"><span>loan, cash advance, fuel, others</span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--gold">
                    <p class="kp-kpi-label"><i class="fas fa-money-check" aria-hidden="true"></i> Net Paid to Planters</p>
                    <p class="kp-kpi-value"><small>₱</small><span class="tabular" x-text="fmt(data.proceeds.totals.paid, 0)"></span></p>
                    <p class="kp-kpi-foot">
                        <span x-text="pct(data.proceeds.totals.paid, data.proceeds.totals.gross) + '% of gross'"></span>
                        <span class="kp-dash-status kp-dash-status--danger" x-show="data.proceeds.negativeCount > 0">
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i> <span x-text="data.proceeds.negativeCount + ' negative'"></span></span>
                    </p>
                </div>
            </section>

            <section class="kp-card" aria-labelledby="pr-wf-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="pr-wf-h" class="kp-card-title">From Gross to Net Paid</h2>
                        <p class="kp-card-sub">What each step adds or takes off, ₱</p>
                    </div>
                    <div class="kp-dash-legend" aria-hidden="true">
                        <span><i class="kp-dash-swatch kp-dash-swatch--1"></i>Adds</span>
                        <span><i class="kp-dash-swatch kp-dash-swatch--3"></i>Takes off</span>
                        <span><i class="kp-dash-swatch kp-dash-swatch--2"></i>Total</span>
                    </div>
                </div>
                <div class="relative w-full p-5" style="height: 300px">
                    <canvas id="procWaterfallChart" role="img" aria-label="Waterfall chart from gross proceeds through deductions and accounts applied to net paid"></canvas>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="kp-card" aria-labelledby="pr-ded-h">
                    <div class="kp-card-head"><div>
                        <h2 id="pr-ded-h" class="kp-card-title">Deductions Breakdown</h2>
                        <p class="kp-card-sub">Taken off the gross proceeds, ₱</p>
                    </div></div>
                    <div class="relative w-full p-5" style="height: 230px"><canvas id="procDedChart" role="img" aria-label="Bar chart of deductions by kind"></canvas></div>
                </section>
                <section class="kp-card" aria-labelledby="pr-acc-h">
                    <div class="kp-card-head"><div>
                        <h2 id="pr-acc-h" class="kp-card-title">Accounts Applied</h2>
                        <p class="kp-card-sub">Receivables collected through the vouchers, ₱</p>
                    </div></div>
                    <div class="relative w-full p-5" style="height: 230px"><canvas id="procAccChart" role="img" aria-label="Bar chart of accounts applied by kind"></canvas></div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4" :class="data.proceeds.negative.length ? 'lg:grid-cols-2' : ''">
                <section class="kp-card" aria-labelledby="pr-top-h">
                    <div class="kp-card-head"><div>
                        <h2 id="pr-top-h" class="kp-card-title">Highest Net Paid</h2>
                        <p class="kp-card-sub">Planters receiving the largest checks</p>
                    </div></div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th scope="col">Planter</th><th scope="col" class="text-right">Gross</th><th scope="col" class="text-right">Applied</th><th scope="col" class="text-right">Net Paid</th></tr></thead>
                            <tbody>
                                <template x-for="r in data.proceeds.topPaid" :key="r.planter_code">
                                    <tr>
                                        <td><span class="font-medium" x-text="r.planter_name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="r.planter_code"></span></td>
                                        <td class="text-right tabular" x-text="fmt(r.gross, 2)"></td>
                                        <td class="text-right tabular" x-text="fmt(r.applied, 2)"></td>
                                        <td class="text-right tabular font-semibold" x-text="fmt(r.paid, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.proceeds.topPaid.length"><td colspan="4" class="text-center text-sm" style="color: var(--kp-muted)">No proceeds for this period</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="kp-card" aria-labelledby="pr-neg-h" x-show="data.proceeds.negative.length">
                    <div class="kp-card-head"><div>
                        <h2 id="pr-neg-h" class="kp-card-title"><i class="fas fa-circle-exclamation" style="color: #b42318" aria-hidden="true"></i> Negative Net Paid</h2>
                        <p class="kp-card-sub">Accounts applied are larger than net proceeds</p>
                    </div></div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th scope="col">Planter</th><th scope="col" class="text-right">Applied</th><th scope="col" class="text-right">Net Paid</th></tr></thead>
                            <tbody>
                                <template x-for="r in data.proceeds.negative" :key="r.planter_code">
                                    <tr>
                                        <td><span class="font-medium" x-text="r.planter_name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="r.planter_code"></span></td>
                                        <td class="text-right tabular" x-text="fmt(r.applied, 2)"></td>
                                        <td class="text-right tabular font-semibold" style="color: #b42318" x-text="fmt(r.paid, 2)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </template>
</div>
