{{-- Hauling tab: TLS hauling uploads and Hauler TLS LIQ entries --}}
<div x-show="tab === 'hauling'" x-cloak id="panel-hauling" role="tabpanel" aria-labelledby="tab-hauling" class="space-y-4">
    @include('menu.dashboard.state', ['key' => 'hauling'])

    <template x-if="data.hauling">
        <div class="space-y-4">
            <section aria-label="Hauling figures" class="kp-kpis kp-dashboard-kpis">
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--green">
                    <p class="kp-kpi-label"><i class="fas fa-ticket" aria-hidden="true"></i> TLS Tickets</p>
                    <p class="kp-kpi-value tabular" x-text="fmt(data.hauling.stats.tickets, 0)"></p>
                    <p class="kp-kpi-foot"><span x-text="fmt(data.hauling.stats.tlsTons, 2) + ' t net cane'"></span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--gold">
                    <p class="kp-kpi-label"><i class="fas fa-file-signature" aria-hidden="true"></i> Liquidations</p>
                    <p class="kp-kpi-value tabular" x-text="fmt(data.hauling.stats.entries, 0)"></p>
                    <p class="kp-kpi-foot"><span x-text="data.hauling.stats.released + ' released · ' + data.hauling.stats.activeHaulers + ' active haulers'"></span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--green">
                    <p class="kp-kpi-label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Net to Haulers</p>
                    <p class="kp-kpi-value"><small>₱</small><span class="tabular" x-text="fmt(data.hauling.stats.net, 0)"></span></p>
                    <p class="kp-kpi-foot"><span>gross ₱<span x-text="fmt(data.hauling.stats.gross, 0)"></span> · less ₱<span x-text="fmt(data.hauling.stats.less, 0)"></span></span></p>
                </div>
                <div class="kp-kpi kp-dashboard-kpi kp-dashboard-kpi--gold">
                    <p class="kp-kpi-label"><i class="fas fa-list-check" aria-hidden="true"></i> Needs Attention</p>
                    <p class="kp-kpi-value tabular" x-text="data.hauling.stats.unliquidatedTickets + data.hauling.stats.noPayee + data.hauling.stats.noRate"></p>
                    <p class="kp-kpi-foot"><span x-text="data.hauling.stats.unliquidatedTickets + ' unliquidated · ' + data.hauling.stats.noPayee + ' no payee · ' + data.hauling.stats.noRate + ' no rate'"></span></p>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                <section class="kp-card lg:col-span-3" aria-labelledby="h-wk-h">
                    <div class="kp-card-head">
                        <div>
                            <h2 id="h-wk-h" class="kp-card-title">TLS Tonnage vs Liquidated</h2>
                            <p class="kp-card-sub">Net cane tons per week, whole crop year</p>
                        </div>
                        <div class="kp-dash-legend" aria-hidden="true">
                            <span><i class="kp-dash-swatch kp-dash-swatch--1"></i>TLS uploaded</span>
                            <span><i class="kp-dash-swatch kp-dash-swatch--2"></i>Liquidated</span>
                        </div>
                    </div>
                    <div class="relative w-full p-5" style="height: 280px">
                        <canvas id="haulWeekChart" role="img" aria-label="Grouped bar chart of TLS tons uploaded and liquidated per week"></canvas>
                        <p class="kp-dashboard-donut-empty" x-show="!data.hauling.weekly.weeks.length">No TLS hauling uploaded yet</p>
                    </div>
                </section>
                <section class="kp-card lg:col-span-2" aria-labelledby="h-hl-h">
                    <div class="kp-card-head"><div>
                        <h2 id="h-hl-h" class="kp-card-title">By Hauler</h2>
                        <p class="kp-card-sub">Share of liquidated tonnage</p>
                    </div></div>
                    <ul class="divide-y">
                        <template x-for="h in data.hauling.byHauler" :key="h.hauler">
                            <li class="px-5 py-3">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-medium" x-text="h.hauler"></span>
                                    <span class="tabular text-xs" style="color: var(--kp-muted)" x-text="fmt(h.tons, 2) + ' t · ₱' + fmt(h.net, 0)"></span>
                                </div>
                                <div class="kp-dash-bar mt-2" role="img" :aria-label="pct(h.tons, data.hauling.stats.liqTons) + '% of tonnage'">
                                    <span :style="'width:' + pct(h.tons, data.hauling.stats.liqTons) + '%'"></span>
                                </div>
                                <p class="mt-1 text-xs tabular" style="color: var(--kp-faint)" x-text="h.entries + ' entries · ' + pct(h.tons, data.hauling.stats.liqTons) + '%'"></p>
                            </li>
                        </template>
                        <li x-show="!data.hauling.byHauler.length" class="px-5 py-6 text-center text-sm" style="color: var(--kp-muted)">No liquidation entries for this period</li>
                    </ul>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="kp-card" aria-labelledby="h-tr-h">
                    <div class="kp-card-head"><div>
                        <h2 id="h-tr-h" class="kp-card-title">Top Trucks</h2>
                        <p class="kp-card-sub">By liquidated tonnage</p>
                    </div></div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th scope="col">Truck</th><th scope="col">Payee</th><th scope="col" class="text-right">Tons</th><th scope="col" class="text-right">Net ₱</th></tr></thead>
                            <tbody>
                                <template x-for="t in data.hauling.topTrucks" :key="t.truck_code">
                                    <tr>
                                        <td class="font-medium" x-text="t.truck_code"></td>
                                        <td x-text="t.payee_name || '— not set —'" :style="t.payee_name ? '' : 'color: var(--kp-faint)'"></td>
                                        <td class="text-right tabular" x-text="fmt(t.tons, 2)"></td>
                                        <td class="text-right tabular font-semibold" x-text="fmt(t.net, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.hauling.topTrucks.length"><td colspan="4" class="text-center text-sm" style="color: var(--kp-muted)">No trucks for this period</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="kp-card" aria-labelledby="h-less-h">
                    <div class="kp-card-head"><div>
                        <h2 id="h-less-h" class="kp-card-title">Deducted from Haulers</h2>
                        <p class="kp-card-sub">"Less" items on liquidation slips (cash advance, fuel…)</p>
                    </div></div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr><th scope="col">Item</th><th scope="col" class="text-right">Entries</th><th scope="col" class="text-right">Amount ₱</th></tr></thead>
                            <tbody>
                                <template x-for="i in data.hauling.lessItems" :key="i.description">
                                    <tr>
                                        <td x-text="i.description"></td>
                                        <td class="text-right tabular" x-text="i.entries"></td>
                                        <td class="text-right tabular font-semibold" x-text="fmt(i.amount, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.hauling.lessItems.length"><td colspan="3" class="text-center text-sm" style="color: var(--kp-muted)">Nothing deducted in this period</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </template>
</div>
