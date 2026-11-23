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

            <!-- Gross cane per transloading station, by week / month / crop year, ranked -->
            <section class="kp-card" aria-labelledby="h-st-h" x-show="data.hauling.stations && data.hauling.stations.stations.length">
                <div class="kp-card-head flex-wrap gap-3">
                    <div>
                        <h2 id="h-st-h" class="kp-card-title"><i class="fas fa-ranking-star mr-1" style="color: var(--kp-forest)" aria-hidden="true"></i> Gross Cane per Transloading Station</h2>
                        <p class="kp-card-sub">Gross Cane Wt from the TLS tickets, ranked: the station that received the most gross cane performs best</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border p-0.5 text-xs" style="border-color: var(--kp-border)" role="group" aria-label="Period">
                            <template x-for="p in [{ k: 'week', l: 'Weekly' }, { k: 'month', l: 'Monthly' }, { k: 'year', l: 'Yearly' }]" :key="p.k">
                                <button type="button" class="rounded-md px-2.5 py-1 font-semibold"
                                    :style="stPeriod === p.k ? 'background: var(--kp-forest); color: #fff' : 'color: var(--kp-muted)'"
                                    :aria-pressed="stPeriod === p.k" @click="stPeriod = p.k; stPick = stDefaultPick(); $nextTick(() => render_stations())" x-text="p.l"></button>
                            </template>
                        </div>
                        <select x-model="stPick" @change="$nextTick(() => render_stations())" class="kp-input text-xs py-1" aria-label="Pick the period">
                            <template x-for="(k, i) in (data.hauling.stations?.periods[stPeriod].keys || [])" :key="k">
                                <option :value="k" :selected="k === stPick" x-text="data.hauling.stations.periods[stPeriod].labels[i]"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Ranking -->
                <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                    <template x-for="(r, i) in stRows()" :key="r.name">
                        <article class="rounded-xl border p-3.5" :style="'border-color:' + (i === 0 && r.gross > 0 ? '#087a3d' : 'var(--kp-border)') + (i === 0 && r.gross > 0 ? '; background: rgba(8,122,61,0.06)' : '')">
                            <header class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-sm font-semibold">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white"
                                        :style="'background:' + (['#b7841f', '#8a9399', '#a0643a'][i] || '#56675c')" x-text="i + 1"></span>
                                    <span x-text="r.name"></span>
                                </span>
                                <span class="kp-badge" x-text="'TLS ' + (r.code || '—')"></span>
                            </header>
                            <p class="mt-2 text-xl font-bold tabular"><span x-text="fmt(r.gross, 3)"></span> <span class="text-xs font-normal" style="color: var(--kp-faint)">t gross</span></p>
                            <p class="text-xs font-semibold tabular"
                                :style="r.change === null ? 'color: var(--kp-faint)' : r.change < 0 ? 'color: #b42318' : 'color: #087a3d'">
                                <i class="fas" :class="r.change === null ? 'fa-minus' : r.change < 0 ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up'" aria-hidden="true"></i>
                                <span x-text="r.change === null ? 'No earlier ' + stPeriodWord() + ' to compare' : (r.change > 0 ? '+' : '') + r.change + '% vs ' + stPrevLabel()"></span>
                            </p>
                            <div class="kp-dash-bar mt-2" role="progressbar" :aria-valuenow="r.share" aria-valuemin="0" aria-valuemax="100" :aria-label="r.name + ' share of gross cane'">
                                <span :style="'width:' + Math.min(100, r.share) + '%'"></span>
                            </div>
                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs tabular">
                                <dt style="color: var(--kp-muted)">Share</dt><dd class="text-right" x-text="r.share + '%'"></dd>
                                <dt style="color: var(--kp-muted)">Net Cane Wt</dt><dd class="text-right" x-text="fmt(r.net, 3) + ' t'"></dd>
                                <dt style="color: var(--kp-muted)">Loss (gross − net)</dt><dd class="text-right" x-text="r.lossPct === null ? '—' : fmt(r.gross - r.net, 3) + ' t · ' + r.lossPct + '%'"></dd>
                                <dt style="color: var(--kp-muted)">Tickets</dt><dd class="text-right" x-text="fmt(r.tickets, 0)"></dd>
                            </dl>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span x-show="i === 0 && r.gross > 0" class="kp-badge kp-badge--forest"><i class="fas fa-trophy" aria-hidden="true"></i> Most gross cane</span>
                                <span x-show="r.name === stBest('lossPct', 'min')" class="kp-badge kp-badge--forest"><i class="fas fa-leaf" aria-hidden="true"></i> Lowest loss</span>
                                <span x-show="r.name === stBest('change', 'max') && r.change > 0" class="kp-badge kp-badge--forest"><i class="fas fa-arrow-trend-up" aria-hidden="true"></i> Most improved</span>
                                <span x-show="r.name === stBest('lossPct', 'max') && stRows().length > 1" class="kp-badge" style="background:#fdecea;color:#b42318;border-color:#f2b8b5">Highest loss</span>
                            </div>
                        </article>
                    </template>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 gap-4 px-4 pb-4 lg:grid-cols-5">
                    <figure class="rounded-xl border p-3 lg:col-span-2" style="border-color: var(--kp-border)">
                        <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)" x-text="'Gross and net cane (t) · ' + stPickLabel()"></figcaption>
                        <div class="relative mt-2 w-full" style="height: 280px"><canvas id="haulStationChart" role="img" aria-label="Bars of gross and net cane weight per transloading station for the picked period"></canvas></div>
                    </figure>
                    <figure class="rounded-xl border p-3 lg:col-span-3" style="border-color: var(--kp-border)">
                        <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)" x-text="'Gross cane (t) per station, ' + ({ week: 'week by week', month: 'month by month', year: 'crop year by crop year' })[stPeriod]"></figcaption>
                        <div class="relative mt-2 w-full" style="height: 280px"><canvas id="haulStationWeekChart" role="img" aria-label="Gross cane weight per transloading station over each period"></canvas></div>
                    </figure>
                </div>

                <!-- Gross per station for every period -->
                <div class="overflow-x-auto border-t" style="border-color: var(--kp-border)">
                    <table>
                        <thead><tr>
                            <th scope="col">Transloading station</th>
                            <template x-for="(k, i) in (data.hauling.stations?.periods[stPeriod].keys || [])" :key="k">
                                <th scope="col" class="text-right" :style="k === stPick ? 'color: var(--kp-forest)' : ''" x-text="data.hauling.stations.periods[stPeriod].labels[i]"></th>
                            </template>
                            <th scope="col" class="text-right">Total</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="s in (data.hauling.stations?.stations || [])" :key="s.name">
                                <tr>
                                    <td><span class="font-medium" x-text="s.name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="'TLS ' + (s.code || '—')"></span></td>
                                    <template x-for="k in data.hauling.stations.periods[stPeriod].keys" :key="k">
                                        <td class="text-right tabular"
                                            :style="(stTopOf(k) === s.name ? 'color:#087a3d;font-weight:700;' : '') + (k === stPick ? 'background: rgba(8,122,61,0.06)' : '')"
                                            x-text="stCell(s.name, k).gross ? fmt(stCell(s.name, k).gross, 3) : '—'"></td>
                                    </template>
                                    <td class="text-right tabular font-semibold" x-text="fmt(data.hauling.stations.periods[stPeriod].keys.reduce((a, k) => a + stCell(s.name, k).gross, 0), 3)"></td>
                                </tr>
                            </template>
                            <tr class="font-semibold">
                                <td>All stations</td>
                                <template x-for="k in (data.hauling.stations?.periods[stPeriod].keys || [])" :key="k">
                                    <td class="text-right tabular" x-text="fmt(data.hauling.stations.stations.reduce((a, s) => a + stCell(s.name, k).gross, 0), 3)"></td>
                                </template>
                                <td class="text-right tabular" x-text="fmt((data.hauling.stations?.stations || []).reduce((a, s) => a + data.hauling.stations.periods[stPeriod].keys.reduce((b, k) => b + stCell(s.name, k).gross, 0), 0), 3)"></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="px-4 py-2 text-xs" style="color: var(--kp-faint)">
                        Green bold = the station with the most gross cane in that period. Weekly and monthly are for crop year <span x-text="data.hauling.stations?.cropYear"></span>
                        (month by date milled); yearly covers every crop year uploaded.
                    </p>
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
