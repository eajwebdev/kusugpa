{{-- Production tab: Quedan, Molasses, Consolidated summary --}}
<div x-show="tab === 'production'" x-cloak id="panel-production" role="tabpanel" aria-labelledby="tab-production" class="space-y-4">
    @include('menu.dashboard.state', ['key' => 'production'])

    <template x-if="data.production">
        <div class="space-y-4">
            <section aria-label="Production figures" class="kp-kpis kp-dashboard-kpis">
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--green">
                    <p class="kp-kpi-label"><i class="fas fa-cubes-stacked" aria-hidden="true"></i> Net Sugar</p>
                    <p class="kp-kpi-value tabular" x-text="fmt(data.production.stats.netLkg, 2)"></p>
                    <p class="kp-kpi-foot"><span>LKG after labor</span><span class="kp-delta" x-text="fmt(data.production.stats.sugarLkg, 2) + ' gross'"></span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--gold">
                    <p class="kp-kpi-label"><i class="fas fa-flask" aria-hidden="true"></i> Molasses</p>
                    <p class="kp-kpi-value tabular" x-text="fmt(data.production.stats.molassesTons, 2)"></p>
                    <p class="kp-kpi-foot"><span>tons · <span x-text="data.production.stats.molassesRecords"></span> records</span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--green">
                    <p class="kp-kpi-label"><i class="fas fa-seedling" aria-hidden="true"></i> Cane (TA weight)</p>
                    <p class="kp-kpi-value tabular" x-text="fmt(data.production.stats.caneTons, 2)"></p>
                    <p class="kp-kpi-foot"><span>tons from consolidated</span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--gold">
                    <p class="kp-kpi-label"><i class="fas fa-gauge-high" aria-hidden="true"></i> Sugar Yield</p>
                    <p class="kp-kpi-value tabular" x-text="fmt(data.production.stats.lkgPerTon, 3)"></p>
                    <p class="kp-kpi-foot"><span>LKG per ton cane · <span x-text="data.production.stats.planters"></span> planters</span></p>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="kp-card" aria-labelledby="p-lkg-h">
                    <div class="kp-card-head"><div>
                        <h2 id="p-lkg-h" class="kp-card-title">Weekly Sugar</h2>
                        <p class="kp-card-sub">Gross LKG per week, whole crop year<span x-show="week"> · week <span x-text="week"></span> highlighted</span></p>
                    </div></div>
                    <div class="relative w-full p-5 kp-dashboard-chart-body">
                        <canvas id="prodLkgChart" role="img" aria-label="Bar chart of sugar LKG per week"></canvas>
                        <p class="kp-dashboard-donut-empty" x-show="!data.production.weekly.weeks.length">No quedan uploaded yet</p>
                    </div>
                </section>
                <section class="kp-card" aria-labelledby="p-mol-h">
                    <div class="kp-card-head"><div>
                        <h2 id="p-mol-h" class="kp-card-title">Weekly Molasses</h2>
                        <p class="kp-card-sub">Net molasses tons per week, whole crop year</p>
                    </div></div>
                    <div class="relative w-full p-5 kp-dashboard-chart-body">
                        <canvas id="prodMolChart" role="img" aria-label="Bar chart of molasses tons per week"></canvas>
                        <p class="kp-dashboard-donut-empty" x-show="!data.production.weekly.weeks.length">No molasses uploaded yet</p>
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                <section class="kp-card lg:col-span-3" aria-labelledby="p-allow-h">
                    <div class="kp-card-head"><div>
                        <h2 id="p-allow-h" class="kp-card-title">Allowances &amp; Incentives</h2>
                        <p class="kp-card-sub">Consolidated summary amounts, ₱</p>
                    </div></div>
                    <div class="relative w-full p-5" style="height: 320px">
                        <canvas id="prodAllowChart" role="img" aria-label="Horizontal bar chart of consolidated allowance amounts"></canvas>
                    </div>
                </section>
                <section class="kp-card p-5 lg:col-span-2" aria-labelledby="p-status-h">
                    <h2 id="p-status-h" class="kp-card-title mb-1">Registry Status</h2>
                    <p class="kp-card-sub mb-4">Records processed in Buy Quedan / Buy Molasses</p>
                    <template x-for="row in [{label: 'Quedan', unit: 'LKG', s: data.production.quedanStatus}, {label: 'Molasses', unit: 't', s: data.production.molassesStatus}]" :key="row.label">
                        <div class="mb-5">
                            <div class="mb-1.5 flex items-baseline justify-between text-sm">
                                <span class="font-semibold" x-text="row.label"></span>
                                <span class="text-xs tabular" style="color: var(--kp-muted)"
                                    x-text="pct(row.s.bought.records, row.s.bought.records + row.s.pending.records + row.s.rejected.records) + '% bought'"></span>
                            </div>
                            <div class="kp-dash-stack" role="img" :aria-label="row.label + ': ' + row.s.bought.records + ' bought, ' + row.s.pending.records + ' pending, ' + row.s.rejected.records + ' rejected'">
                                <span class="kp-dash-stack-seg kp-dash-stack-seg--1" :style="'flex-grow:' + row.s.bought.records"></span>
                                <span class="kp-dash-stack-seg kp-dash-stack-seg--3" :style="'flex-grow:' + row.s.pending.records"></span>
                                <span class="kp-dash-stack-seg kp-dash-stack-seg--4" :style="'flex-grow:' + row.s.rejected.records"></span>
                            </div>
                            <dl class="mt-2 grid grid-cols-3 gap-2 text-xs">
                                <div><dt class="kp-dash-key"><i class="kp-dash-swatch kp-dash-swatch--1"></i>Bought</dt><dd class="tabular font-semibold" x-text="row.s.bought.records"></dd></div>
                                <div><dt class="kp-dash-key"><i class="kp-dash-swatch kp-dash-swatch--3"></i>Pending</dt><dd class="tabular font-semibold" x-text="row.s.pending.records"></dd></div>
                                <div><dt class="kp-dash-key"><i class="kp-dash-swatch kp-dash-swatch--4"></i>Rejected</dt><dd class="tabular font-semibold" x-text="row.s.rejected.records"></dd></div>
                            </dl>
                        </div>
                    </template>
                    <p class="text-xs" style="color: var(--kp-muted)">Liens on quedans: <strong class="tabular" x-text="fmt(data.production.stats.liens, 2)"></strong></p>
                </section>
            </div>

            <section class="kp-card" aria-labelledby="p-top-h">
                <div class="kp-card-head"><div>
                    <h2 id="p-top-h" class="kp-card-title">Top Planters by Sugar</h2>
                    <p class="kp-card-sub">Net LKG with molasses, cane and yield</p>
                </div></div>
                <div class="overflow-x-auto">
                    <table>
                        <thead><tr>
                            <th scope="col">#</th><th scope="col">Planter</th>
                            <th scope="col" class="text-right">Net LKG</th><th scope="col" class="text-right">Molasses (t)</th>
                            <th scope="col" class="text-right">Cane (t)</th><th scope="col" class="text-right">LKG / TC</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="(p, i) in data.production.topPlanters" :key="p.planter_code">
                                <tr>
                                    <td class="tabular" x-text="i + 1"></td>
                                    <td><span class="font-medium" x-text="p.planter_name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="p.planter_code"></span></td>
                                    <td class="text-right tabular font-semibold" x-text="fmt(p.net_lkg, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(p.molasses, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(p.cane, 2)"></td>
                                    <td class="text-right tabular" x-text="p.lkg_per_ton === null ? '—' : fmt(p.lkg_per_ton, 3)"></td>
                                </tr>
                            </template>
                            <tr x-show="!data.production.topPlanters.length"><td colspan="6" class="text-center text-sm" style="color: var(--kp-muted)">No quedan records for this period</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </template>
</div>
