{{-- TLS Deliveries by Location: planter cane through each transloading station, this crop year vs the last,
     by crop year to date, by month or by week, to see which TLS location is delivering less. --}}
<section class="kp-card overflow-hidden" aria-labelledby="tls-h">
    <div class="kp-card-head flex-wrap gap-3">
        <div>
            <h2 id="tls-h" class="kp-card-title"><i class="fas fa-truck-ramp-box mr-1" style="color: var(--kp-forest)" aria-hidden="true"></i> TLS Deliveries by Location</h2>
            <p class="kp-card-sub">
                Net cane delivered through each transloading station (TLS trans codes only, direct-to-mill left out)
                <template x-if="data.tls"><span> · Crop year <span x-text="data.tls.cropYear"></span> vs <span x-text="data.tls.prevCropYear"></span></span></template>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2" x-show="data.tls">
            <div class="inline-flex rounded-lg border p-0.5 text-xs" style="border-color: var(--kp-border)" role="group" aria-label="Compare by">
                <template x-for="m in [{ k: 'year', l: 'Crop year to date' }, { k: 'month', l: 'Month' }, { k: 'week', l: 'Week' }]" :key="m.k">
                    <button type="button" class="rounded-md px-2.5 py-1 font-semibold"
                        :style="tlsMode === m.k ? 'background: var(--kp-forest); color: #fff' : 'color: var(--kp-muted)'"
                        :aria-pressed="tlsMode === m.k" @click="tlsMode = m.k; tlsPick = tlsDefaultPick(); $nextTick(() => render_tls())" x-text="m.l"></button>
                </template>
            </div>
            <select x-show="tlsMode === 'month'" x-model="tlsPick" @change="$nextTick(() => render_tls())" class="kp-input text-xs py-1" aria-label="Month">
                <template x-for="m in (data.tls?.months || [])" :key="m.key"><option :value="m.key" x-text="m.label" :selected="m.key === tlsPick"></option></template>
            </select>
            <select x-show="tlsMode === 'week'" x-model="tlsPick" @change="$nextTick(() => render_tls())" class="kp-input text-xs py-1" aria-label="Week">
                <template x-for="w in (data.tls?.weeks || [])" :key="w"><option :value="String(w)" x-text="'Week ' + w" :selected="String(w) === tlsPick"></option></template>
            </select>
        </div>
    </div>

    <div x-show="errors.tls" x-cloak class="kp-dash-error m-4" role="alert">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <span x-text="errors.tls"></span>
        <button type="button" class="kp-btn kp-btn-secondary" @click="load('tls', true)">Try again</button>
    </div>
    <div x-show="loading.tls && !data.tls" class="kp-dash-loading-card"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Loading TLS deliveries…</div>

    <template x-if="data.tls">
        <div>
            <p class="px-5 pt-1 text-xs" style="color: var(--kp-faint)" x-text="tlsPeriodNote()"></p>

            <!-- One card per TLS location -->
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="(r, i) in tlsRows()" :key="r.key">
                    <article class="rounded-xl border p-3.5" :style="'border-color:' + (r.status === 'down' ? '#f2b8b5' : 'var(--kp-border)')">
                        <header class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-sm font-semibold"><i class="kp-dash-swatch" :class="'kp-dash-swatch--' + ((i % 4) + 1)"></i><span x-text="r.name"></span></span>
                            <span class="kp-badge" x-text="'TLS ' + (r.code || '—')"></span>
                        </header>
                        <p class="mt-2 text-xl font-bold tabular"><span x-text="fmt(r.tons, 1)"></span> <span class="text-xs font-normal" style="color: var(--kp-faint)">t cane</span></p>
                        <p class="text-xs font-semibold tabular"
                            :style="r.change === null ? 'color: var(--kp-faint)' : r.change < 0 ? 'color: #b42318' : 'color: #087a3d'">
                            <i class="fas" :class="r.change === null ? 'fa-minus' : r.change < 0 ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up'" aria-hidden="true"></i>
                            <span x-text="r.change === null ? 'No delivery in the same period last year' : (r.change > 0 ? '+' : '') + r.change + '% vs ' + fmt(r.prevTons, 1) + ' t last year'"></span>
                        </p>
                        <div class="kp-dash-bar mt-2" role="progressbar" :aria-valuenow="r.share" aria-valuemin="0" aria-valuemax="100" :aria-label="r.name + ' share of TLS deliveries'">
                            <span :style="'width:' + Math.min(100, r.share) + '%'"></span>
                        </div>
                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs tabular">
                            <dt style="color: var(--kp-muted)">Share of TLS cane</dt><dd class="text-right" x-text="r.share + '%'"></dd>
                            <dt style="color: var(--kp-muted)">Trips</dt><dd class="text-right" x-text="fmt(r.trips, 0) + ' (' + fmt(r.prevTrips, 0) + ' last yr)'"></dd>
                            <dt style="color: var(--kp-muted)">Avg per trip</dt><dd class="text-right" x-text="r.trips ? fmt(r.tons / r.trips, 2) + ' t' : '—'"></dd>
                            <template x-if="r.planters !== null"><dt style="color: var(--kp-muted)">Planters</dt></template>
                            <template x-if="r.planters !== null"><dd class="text-right" x-text="fmt(r.planters, 0) + ' (' + fmt(r.prevPlanters, 0) + ' last yr)'"></dd></template>
                            <template x-if="r.lostCount"><dt style="color: var(--kp-muted)" title="Delivered here by this week last crop year, not to any TLS yet this year">Not delivered yet</dt></template>
                            <template x-if="r.lostCount"><dd class="text-right" style="color: #b54708" x-text="r.lostCount + ' · ' + fmt(r.lostTons, 0) + ' t'"></dd></template>
                        </dl>
                    </article>
                </template>
            </div>

            <!-- Findings -->
            <div class="px-4" x-show="data.tls.insights.length && tlsMode === 'year'">
                <ul class="space-y-1.5 rounded-xl p-3 text-xs" style="background: var(--kp-surface-2)">
                    <template x-for="(f, i) in data.tls.insights" :key="i">
                        <li class="flex gap-2">
                            <i class="fas mt-0.5" aria-hidden="true"
                                :class="f.tone === 'down' ? 'fa-arrow-trend-down' : f.tone === 'up' ? 'fa-arrow-trend-up' : f.tone === 'warn' ? 'fa-user-clock' : 'fa-circle-info'"
                                :style="'color:' + (f.tone === 'down' ? '#b42318' : f.tone === 'up' ? '#087a3d' : f.tone === 'warn' ? '#b54708' : '#2f6fb0')"></i>
                            <span x-text="f.text"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-5">
                <figure class="rounded-xl border p-3 lg:col-span-2" style="border-color: var(--kp-border)">
                    <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)" x-text="'This year vs last year · ' + tlsPeriodLabel()"></figcaption>
                    <div class="relative mt-2 w-full" style="height: 260px"><canvas id="tlsCompareChart" role="img" aria-label="Bars of net cane per TLS location, this crop year and last"></canvas></div>
                </figure>
                <figure class="rounded-xl border p-3 lg:col-span-3" style="border-color: var(--kp-border)">
                    <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)" x-text="(tlsMode === 'month' ? 'Net cane per month' : 'Net cane per week') + ' · this crop year solid, last crop year dashed'"></figcaption>
                    <div class="relative mt-2 w-full" style="height: 260px"><canvas id="tlsTrendChart" role="img" aria-label="Lines of net cane per TLS location over the season"></canvas></div>
                </figure>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto border-t" style="border-color: var(--kp-border)">
                <table>
                    <thead><tr>
                        <th scope="col">TLS location</th>
                        <th scope="col" class="text-right" x-text="'This year (t)'"></th>
                        <th scope="col" class="text-right" x-text="'Last year (t)'"></th>
                        <th scope="col" class="text-right">Change</th>
                        <th scope="col" class="text-right">Share</th>
                        <th scope="col" class="text-right">Trips</th>
                        <th scope="col" class="text-right" x-show="tlsMode === 'year'">Planters</th>
                        <th scope="col" class="text-right" x-show="tlsMode === 'year'" title="Delivered here by this week last crop year, not to any TLS yet this year">Not delivered yet</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="r in tlsRows()" :key="r.key">
                            <tr>
                                <td><span class="font-medium" x-text="r.name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="'TLS ' + (r.code || '—')"></span></td>
                                <td class="text-right tabular font-semibold" x-text="fmt(r.tons, 3)"></td>
                                <td class="text-right tabular" x-text="fmt(r.prevTons, 3)"></td>
                                <td class="text-right tabular" :style="r.change === null ? 'color: var(--kp-faint)' : r.change < 0 ? 'color: #b42318' : 'color: #087a3d'"
                                    x-text="r.change === null ? '—' : (r.change > 0 ? '+' : '') + r.change + '%'"></td>
                                <td class="text-right tabular" x-text="r.share + '%'"></td>
                                <td class="text-right tabular" x-text="fmt(r.trips, 0)"></td>
                                <td class="text-right tabular" x-show="tlsMode === 'year'" x-text="fmt(r.planters, 0)"></td>
                                <td class="text-right tabular" x-show="tlsMode === 'year'" :style="r.lostCount ? 'color:#b54708' : 'color: var(--kp-faint)'" x-text="r.lostCount ? r.lostCount + ' (' + fmt(r.lostTons, 1) + ' t)' : '—'"></td>
                            </tr>
                        </template>
                        <tr class="font-semibold" x-show="tlsRows().length">
                            <td>Total TLS</td>
                            <td class="text-right tabular" x-text="fmt(tlsRows().reduce((a, r) => a + r.tons, 0), 3)"></td>
                            <td class="text-right tabular" x-text="fmt(tlsRows().reduce((a, r) => a + r.prevTons, 0), 3)"></td>
                            <td class="text-right tabular" x-text="(() => { const c = tlsRows().reduce((a, r) => a + r.tons, 0), p = tlsRows().reduce((a, r) => a + r.prevTons, 0); return p > 0 ? ((c - p) / p * 100 > 0 ? '+' : '') + Math.round((c - p) / p * 1000) / 10 + '%' : '—'; })()"></td>
                            <td class="text-right tabular">100%</td>
                            <td class="text-right tabular" x-text="fmt(tlsRows().reduce((a, r) => a + r.trips, 0), 0)"></td>
                            <td class="text-right tabular" x-show="tlsMode === 'year'"></td>
                            <td class="text-right tabular" x-show="tlsMode === 'year'"></td>
                        </tr>
                        <tr x-show="!tlsRows().length"><td colspan="8" class="text-center text-sm" style="color: var(--kp-muted)">No TLS hauling tickets uploaded for this crop year</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Who to follow up, per location -->
            <details class="border-t px-4 py-3" style="border-color: var(--kp-border)" x-show="tlsMode === 'year' && data.tls.locations.some(l => l.lostCount)">
                <summary class="cursor-pointer text-sm font-semibold">Planters to follow up: delivered by this week last crop year, nothing yet this year</summary>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <template x-for="l in data.tls.locations.filter(l => l.lostCount)" :key="l.key">
                        <div class="rounded-xl p-3" style="background: var(--kp-surface-2)">
                            <p class="text-xs font-semibold" x-text="l.name + ' · top ' + l.lost.length + ' of ' + l.lostCount"></p>
                            <ul class="mt-1.5 space-y-0.5 text-xs tabular">
                                <template x-for="p in l.lost" :key="p.planter_code">
                                    <li class="flex justify-between gap-2"><span class="truncate" x-text="p.planter_name + ' (' + p.planter_code + ')'"></span><span x-text="fmt(p.tons, 1) + ' t'"></span></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </details>
        </div>
    </template>
</section>
