// public/js/dashboard-tabs.js
// Dashboard tabs: each tab loads /dashboard/tab/{key} when opened, and again after the
// crop year / week filter changes. Overview keeps its own component (dashboard.js).
function dashboardTabs(tabs) {
    // Categorical slots, validated for color-blind separation on the light and dark surfaces
    const PALETTE = {
        light: ['#087a3d', '#2f6fb0', '#b7841f', '#8a5cc2'],
        dark: ['#2a9456', '#4f8fd6', '#b98a24', '#a07ad6'],
    };

    return {
        tabs,
        tab: 'overview',
        tabUrl: '',
        cropYear: '',
        week: '',
        data: {},
        loading: {},
        errors: {},
        loadedFor: {},
        requestIds: {},
        charts: {},
        onlyIssues: false,
        // TLS Deliveries by Location: compare by crop year to date, month or week
        tlsMode: 'year',
        // Gross cane per transloading station (Hauling): weekly / monthly / yearly
        stPeriod: 'week',
        stPick: '',
        tlsPick: '',
        moduleFilter: '',

        init() {
            this.tabUrl = this.$el.dataset.tabUrl;
            const fromHash = window.location.hash.replace('#', '');
            if (this.tabs.some(t => t.key === fromHash)) this.tab = fromHash;

            window.addEventListener('filter-changed', (event) => {
                this.cropYear = event.detail.cropYear || '';
                this.week = event.detail.week || '';
                this.refresh();
            });
            // Overview reports the crop year it defaulted to before any filter is picked
            window.addEventListener('dashboard-scope', (event) => {
                if (!this.cropYear) this.cropYear = event.detail.cropYear || '';
            });
            window.addEventListener('hashchange', () => {
                const key = window.location.hash.replace('#', '');
                if (key !== this.tab && this.tabs.some(t => t.key === key)) this.select(key, false);
            });
            // Charts are redrawn in the new theme colors when dark mode is switched
            let dark = this.theme().dark;
            new MutationObserver(() => {
                if (this.theme().dark === dark) return;
                dark = this.theme().dark;
                this.renderTab(this.tab);
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

            this.load('modules');
            this.load('balance');
            if (this.hasCollections()) this.load('trail');
            if (this.tab !== 'overview') this.load(this.tab);
            this.$nextTick(() => this.revealTab(this.tab));
        },

        // The Collections Tracker is for users who may see loans, cash advances or fuel & others
        hasCollections() {
            return this.tabs.some(t => t.key === 'finance');
        },

        get balanceIssues() {
            return this.data.balance ? this.data.balance.issues : 0;
        },

        // On narrow screens the tab bar scrolls sideways; keep the active tab in view
        revealTab(key) {
            const button = document.getElementById('tab-' + key);
            if (!button) return;
            const nav = button.parentElement;
            nav.scrollLeft += button.getBoundingClientRect().left - nav.getBoundingClientRect().left - 8;
        },

        select(key, pushHash = true) {
            this.tab = key;
            this.$nextTick(() => this.revealTab(key));
            if (pushHash) history.replaceState(null, '', '#' + key);
            if (key === 'overview') return;
            if (this.loadedFor[key] !== this.scopeKey()) {
                this.load(key);
            } else {
                this.$nextTick(() => this.renderTab(key));
            }
        },

        moveTab(step) {
            const index = this.tabs.findIndex(t => t.key === this.tab);
            const next = this.tabs[(index + step + this.tabs.length) % this.tabs.length];
            this.select(next.key);
            this.$nextTick(() => document.getElementById('tab-' + next.key)?.focus());
        },

        scopeKey() {
            return (this.cropYear || '') + '|' + (this.week || '');
        },

        refresh() {
            this.load('modules');
            this.load('balance');
            if (this.hasCollections()) this.load('trail');
            if (this.tab !== 'overview' && this.tab !== 'balance') this.load(this.tab);
        },

        async load(key, force = false) {
            if (!force && this.loading[key] && this.loadedFor[key] === this.scopeKey()) return;
            // The TLS location comparison sits on the Production tab
            if (key === 'production') this.load('tls', force);
            const requestId = (this.requestIds[key] || 0) + 1;
            this.requestIds[key] = requestId;
            const scope = this.scopeKey();
            this.loading[key] = true;
            this.errors[key] = '';

            try {
                const url = new URL(this.tabUrl.replace('__TAB__', key), window.location.origin);
                if (this.cropYear) url.searchParams.set('crop_year', this.cropYear);
                if (this.week) url.searchParams.set('week_no', this.week);

                const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const body = await response.json().catch(() => ({}));
                if (requestId !== this.requestIds[key]) return;
                if (!response.ok) throw new Error(body.message || 'Could not load this section.');
                if (body.empty) throw new Error(body.message);

                this.data[key] = body;
                this.loadedFor[key] = scope;
                if (!this.cropYear && body.scope) this.cropYear = body.scope.crop_year;
                if (key === this.tab) this.$nextTick(() => this.renderTab(key));
                // The Money Trail charts sit on the Loans & Advances tab
                if (key === 'trail' && this.tab === 'finance') this.$nextTick(() => this.render_trail(body));
                if (key === 'tls') {
                    if (this.week) this.tlsMode = 'week';
                    this.tlsPick = this.tlsDefaultPick();
                    if (this.tab === 'production') this.$nextTick(() => this.render_tls());
                }
            } catch (error) {
                if (requestId !== this.requestIds[key]) return;
                this.errors[key] = error.message || 'Could not load this section.';
            } finally {
                if (requestId === this.requestIds[key]) this.loading[key] = false;
            }
        },

        // ==================== CHARTS ====================

        theme() {
            const dark = document.documentElement.classList.contains('dark');
            return {
                dark,
                colors: dark ? PALETTE.dark : PALETTE.light,
                grid: dark ? 'rgba(255,255,255,0.08)' : '#eef2ef',
                ticks: dark ? '#a9bdb0' : '#56675c',
                surface: dark ? '#12221a' : '#ffffff',
                muted: dark ? 'rgba(169,189,176,0.25)' : 'rgba(86,103,92,0.18)',
            };
        },

        chart(id, config) {
            if (this.charts[id]) { this.charts[id].destroy(); delete this.charts[id]; }
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            this.charts[id] = new Chart(canvas.getContext('2d'), config);
        },

        baseOptions(extra = {}) {
            const t = this.theme();
            const money = extra.money;
            return {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: extra.horizontal ? 'y' : 'x',
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: extra.legend ? { position: 'bottom', labels: { color: t.ticks, usePointStyle: true, pointStyleWidth: 8, padding: 14, font: { size: 11 } } } : { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const raw = Array.isArray(ctx.raw) ? ctx.raw[1] - ctx.raw[0] : ctx.raw;
                                const value = (money ? '₱' : '') + this.fmt(Math.abs(raw), money ? 2 : 3);
                                return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + value;
                            },
                        },
                    },
                },
                scales: {
                    x: { stacked: !!extra.stacked, grid: { display: !!extra.horizontal, color: t.grid }, ticks: { color: t.ticks, font: { size: 11 } } },
                    y: { stacked: !!extra.stacked, beginAtZero: true, grid: { display: !extra.horizontal, color: t.grid }, ticks: { color: t.ticks, font: { size: 11 } } },
                },
            };
        },

        bar(label, data, color, extra = {}) {
            return { label, data, backgroundColor: color, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 36, ...extra };
        },

        renderTab(key) {
            if (key === this.tab && this.data[key] && typeof this['render_' + key] === 'function') {
                this['render_' + key](this.data[key]);
            }
        },

        render_production(d) {
            const t = this.theme();
            const picked = Number(this.week);
            // The picked week stays in its color; the other weeks recede
            const weekColors = (color) => d.weekly.weeks.map(w => !picked || w === picked ? color : t.muted);
            this.chart('prodLkgChart', {
                type: 'bar',
                data: { labels: d.weekly.labels, datasets: [this.bar('Sugar (LKG)', d.weekly.lkg, weekColors(t.colors[0]))] },
                options: this.baseOptions(),
            });
            this.chart('prodMolChart', {
                type: 'bar',
                data: { labels: d.weekly.labels, datasets: [this.bar('Molasses (t)', d.weekly.molasses, weekColors(t.colors[2]))] },
                options: this.baseOptions(),
            });
            this.chart('prodAllowChart', {
                type: 'bar',
                data: { labels: d.allowances.map(a => a.label), datasets: [this.bar('Amount', d.allowances.map(a => a.amount), t.colors[0], { maxBarThickness: 18 })] },
                options: this.baseOptions({ horizontal: true, money: true }),
            });
            this.render_tls();
        },

        // ==================== TLS DELIVERIES BY LOCATION ====================

        // The month / week shown first: the latest one with deliveries this crop year (or the picked week)
        tlsDefaultPick() {
            const d = this.data.tls;
            if (!d) return '';
            if (this.tlsMode === 'week') {
                const w = this.week || d.throughWeek || d.weeks[d.weeks.length - 1];
                return w ? String(w) : '';
            }
            if (this.tlsMode === 'month') {
                const withData = d.months.filter(m => Object.values(d.monthly.cur).some(byMonth => byMonth[m.key]));
                const last = withData[withData.length - 1] || d.months[d.months.length - 1];
                return last ? last.key : '';
            }
            return '';
        },

        tlsPeriodLabel() {
            const d = this.data.tls;
            if (!d) return '';
            if (this.tlsMode === 'month') return (d.months.find(m => m.key === this.tlsPick) || {}).label || '';
            if (this.tlsMode === 'week') return 'Week ' + this.tlsPick;
            return 'Weeks 1–' + (d.throughWeek || '—');
        },

        tlsPeriodNote() {
            const d = this.data.tls;
            if (!d) return '';
            if (this.tlsMode === 'month') {
                return this.tlsPeriodLabel() + ' of crop year ' + d.cropYear + ' against ' + this.tlsPeriodLabel() + ' of ' + d.prevCropYear
                    + ' (by date milled). The latest month may still be in progress.';
            }
            if (this.tlsMode === 'week') return 'Week ' + this.tlsPick + ' of crop year ' + d.cropYear + ' against week ' + this.tlsPick + ' of ' + d.prevCropYear + '.';
            return 'Weeks 1–' + (d.throughWeek || '—') + ' of crop year ' + d.cropYear + ' against the same weeks of ' + d.prevCropYear + ', so the comparison is fair mid-season.';
        },

        // One row per TLS location for the chosen period
        tlsRows() {
            const d = this.data.tls;
            if (!d) return [];
            let rows;
            if (this.tlsMode === 'year') {
                rows = d.locations.map(l => ({ ...l }));
            } else {
                const src = this.tlsMode === 'month' ? d.monthly : d.weekly;
                const k = this.tlsPick;
                rows = d.locations.map(l => {
                    const cur = (src.cur[l.key] || {})[k] || { tons: 0, trips: 0 };
                    const prev = (src.prev[l.key] || {})[k] || { tons: 0, trips: 0 };
                    return {
                        key: l.key, name: l.name, code: l.code,
                        tons: cur.tons, trips: cur.trips, prevTons: prev.tons, prevTrips: prev.trips,
                        change: prev.tons > 0 ? Math.round((cur.tons - prev.tons) / prev.tons * 1000) / 10 : null,
                        planters: null, prevPlanters: null, lostCount: 0, lostTons: 0,
                    };
                });
            }
            const total = rows.reduce((a, r) => a + r.tons, 0);
            return rows.map(r => ({
                ...r,
                share: total > 0 ? Math.round(r.tons / total * 1000) / 10 : 0,
                status: r.change !== null && r.change <= -10 ? 'down' : r.change !== null && r.change >= 10 ? 'up' : 'steady',
            }));
        },

        render_tls() {
            const d = this.data.tls;
            if (!d || !document.getElementById('tlsCompareChart')) return;
            const t = this.theme();
            const rows = this.tlsRows();
            const colorOf = (i) => t.colors[i % t.colors.length];

            this.chart('tlsCompareChart', {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.name),
                    datasets: [
                        this.bar(d.prevCropYear + ' (last year)', rows.map(r => r.prevTons), t.muted),
                        this.bar(d.cropYear + ' (this year)', rows.map(r => r.tons), rows.map(r => r.status === 'down' ? '#b42318' : t.colors[0])),
                    ],
                },
                options: this.baseOptions({ legend: true }),
            });

            const byMonth = this.tlsMode === 'month';
            const axis = byMonth ? d.months.map(m => m.key) : d.weeks;
            const src = byMonth ? d.monthly : d.weekly;
            const datasets = [];
            d.locations.forEach((l, i) => {
                datasets.push({
                    type: 'line', label: l.name, data: axis.map(k => ((src.cur[l.key] || {})[k] || {}).tons ?? null),
                    borderColor: colorOf(i), backgroundColor: colorOf(i), tension: 0.3, pointRadius: 2.5, borderWidth: 2, spanGaps: false,
                });
                datasets.push({
                    type: 'line', label: l.name + ' (last yr)', data: axis.map(k => ((src.prev[l.key] || {})[k] || {}).tons ?? null),
                    borderColor: colorOf(i), backgroundColor: colorOf(i), borderDash: [5, 4], borderWidth: 1.25, pointRadius: 0, tension: 0.3,
                });
            });
            const options = this.baseOptions({ legend: true });
            // Only this year's lines in the legend; last year's dashed lines follow their color
            options.plugins.legend.labels.filter = (item) => !item.text.endsWith('(last yr)');
            this.chart('tlsTrendChart', {
                type: 'line',
                data: { labels: byMonth ? d.months.map(m => m.label) : d.weeks.map(w => 'Wk ' + w), datasets },
                options,
            });
        },

        // Money Trail analytics: money out vs in per week, how it came back, interest, recovery by account
        render_trail(d) {
            if (!d || !document.getElementById('trailFlowChart')) return;
            const t = this.theme();
            const w = d.weekly || [];
            const recovered = w.map(x => Math.round((x.vouchers + x.cash) * 100) / 100);
            this.chart('trailFlowChart', {
                type: 'bar',
                data: {
                    labels: w.map(x => 'Wk ' + x.week),
                    datasets: [
                        this.bar('Released', w.map(x => x.released), t.colors[1]),
                        this.bar('Recovered', recovered, t.colors[0]),
                        { type: 'line', label: 'Balance to collect', data: w.map(x => x.balance), borderColor: t.colors[2], backgroundColor: t.colors[2],
                            borderWidth: 2, pointRadius: 3, tension: 0.3, yAxisID: 'y' },
                    ],
                },
                options: this.baseOptions({ legend: true, money: true }),
            });

            const tot = d.total;
            const mix = [tot.vouchers, tot.cash, Math.max(0, tot.outstanding)];
            this.chart('trailMixChart', {
                type: 'doughnut',
                data: {
                    labels: ['Paid via voucher', 'Cash (over the counter)', 'Still to collect'],
                    datasets: [{ data: mix, backgroundColor: [t.colors[0], t.colors[1], t.colors[2]], borderColor: t.surface, borderWidth: 2 }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '64%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: t.ticks, usePointStyle: true, pointStyleWidth: 8, padding: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: (ctx) => ctx.label + ': ₱' + this.fmt(ctx.raw, 2) + ' (' + this.pct(ctx.raw, mix.reduce((a, b) => a + b, 0)) + '%)' } },
                    },
                },
            });

            const withInterest = d.kinds.filter(k => k.interest || k.interest_collected);
            const interestKinds = withInterest.length ? withInterest : d.kinds;
            this.chart('trailInterestChart', {
                type: 'bar',
                data: {
                    labels: interestKinds.map(k => k.label),
                    datasets: [
                        this.bar('Interest & charges', interestKinds.map(k => k.interest), t.colors[3]),
                        this.bar('Interest collected', interestKinds.map(k => k.interest_collected), t.colors[0]),
                    ],
                },
                options: this.baseOptions({ legend: true, money: true }),
            });

            this.chart('trailKindChart', {
                type: 'bar',
                data: {
                    labels: d.kinds.map(k => k.label),
                    datasets: [
                        this.bar('Recovered', d.kinds.map(k => k.recovered), t.colors[0], { maxBarThickness: 22 }),
                        this.bar('Still to collect', d.kinds.map(k => Math.max(0, k.outstanding)), t.colors[2], { maxBarThickness: 22 }),
                    ],
                },
                options: this.baseOptions({ horizontal: true, stacked: true, legend: true, money: true }),
            });
        },

        render_finance(d) {
            if (this.data.trail) this.$nextTick(() => this.render_trail(this.data.trail));
            const t = this.theme();
            const loan = d.loans ? d.loans.balance : 0;
            const ca = d.cashAdvances ? d.cashAdvances.balance : 0;
            const labels = [];
            const values = [];
            const colors = [];
            if (d.loans) { labels.push('Loans'); values.push(loan); colors.push(t.colors[0]); }
            if (d.cashAdvances) { labels.push('Cash Advances'); values.push(ca); colors.push(t.colors[1]); }
            this.chart('finMixChart', {
                type: 'doughnut',
                data: { labels, datasets: [{ data: loan + ca > 0 ? values : values.map(() => 0), backgroundColor: colors, borderColor: t.surface, borderWidth: 2 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: t.ticks, usePointStyle: true, pointStyleWidth: 8, padding: 14, font: { size: 11 } } },
                        tooltip: { callbacks: { label: (ctx) => ctx.label + ': ₱' + this.fmt(ctx.raw, 2) + ' (' + this.pct(ctx.raw, loan + ca) + '%)' } },
                    },
                },
            });
            if (d.cashAdvances) {
                const w = d.cashAdvances.weekly;
                this.chart('finCaChart', {
                    type: 'bar',
                    data: { labels: w.labels, datasets: [this.bar('Scheduled', w.scheduled, t.colors[1]), this.bar('Collected', w.collected, t.colors[0])] },
                    options: this.baseOptions({ legend: true, money: true }),
                });
            }
            if (d.deductions && d.deductions.weekly.weeks.length) {
                const w = d.deductions.weekly;
                this.chart('finDedChart', {
                    type: 'bar',
                    data: { labels: w.labels, datasets: [this.bar('Fuel', w.fuel, t.colors[0]), this.bar('Others', w.others, t.colors[1])] },
                    options: this.baseOptions({ legend: true, money: true, stacked: true }),
                });
            }
        },

        render_proceeds(d) {
            const t = this.theme();
            const s = d.totals;
            // Floating bars: each step starts where the previous one ended
            const steps = [
                ['Quedan', [0, s.quedan_gross], 'add'],
                ['Molasses', [s.quedan_gross, s.quedan_gross + s.molasses_gross], 'add'],
                ['Allowances', [s.quedan_gross + s.molasses_gross, s.gross], 'add'],
                ['Gross', [0, s.gross], 'total'],
                ['Deductions', [s.net, s.gross], 'less'],
                ['Net Proceeds', [0, s.net], 'total'],
                ['Accounts Applied', [s.paid, s.net], 'less'],
                ['Net Paid', [0, s.paid], 'total'],
            ];
            const colorOf = { add: t.colors[0], less: t.colors[2], total: t.colors[1] };
            this.chart('procWaterfallChart', {
                type: 'bar',
                data: {
                    labels: steps.map(x => x[0]),
                    datasets: [{ label: 'Amount', data: steps.map(x => x[1]), backgroundColor: steps.map(x => colorOf[x[2]]), borderRadius: 4, borderSkipped: false, maxBarThickness: 56 }],
                },
                options: this.baseOptions({ money: true }),
            });
            this.chart('procDedChart', {
                type: 'bar',
                data: { labels: d.deductionBreakdown.map(x => x.label), datasets: [this.bar('Amount', d.deductionBreakdown.map(x => x.amount), t.colors[2], { maxBarThickness: 18 })] },
                options: this.baseOptions({ horizontal: true, money: true }),
            });
            this.chart('procAccChart', {
                type: 'bar',
                data: { labels: d.accountsBreakdown.map(x => x.label), datasets: [this.bar('Amount', d.accountsBreakdown.map(x => x.amount), t.colors[1], { maxBarThickness: 18 })] },
                options: this.baseOptions({ horizontal: true, money: true }),
            });
        },

        render_hauling(d) {
            const t = this.theme();
            this.chart('haulWeekChart', {
                type: 'bar',
                data: { labels: d.weekly.labels, datasets: [this.bar('TLS uploaded (t)', d.weekly.tls, t.colors[0]), this.bar('Liquidated (t)', d.weekly.liquidated, t.colors[1])] },
                options: this.baseOptions(),
            });

            this.stPick = this.stPick && (d.stations?.periods[this.stPeriod].keys || []).includes(this.stPick) ? this.stPick : this.stDefaultPick();
            this.render_stations();
        },

        // ==================== GROSS CANE PER TRANSLOADING STATION ====================

        stDefaultPick() {
            const st = this.data.hauling?.stations;
            if (!st) return '';
            const keys = st.periods[this.stPeriod].keys;
            if (this.stPeriod === 'week' && st.pickedWeek && keys.includes(String(st.pickedWeek))) return String(st.pickedWeek);
            if (this.stPeriod === 'year' && keys.includes(st.cropYear)) return st.cropYear;
            return keys[keys.length - 1] || '';
        },

        stCell(name, key) {
            const st = this.data.hauling?.stations;
            return ((st?.periods[this.stPeriod].data[name]) || {})[key] || { gross: 0, net: 0, tickets: 0 };
        },

        stPeriodWord() {
            return { week: 'week', month: 'month', year: 'crop year' }[this.stPeriod];
        },

        stLabelOf(key) {
            const p = this.data.hauling?.stations?.periods[this.stPeriod];
            if (!p) return '';
            return p.labels[p.keys.indexOf(key)] || key;
        },

        stPickLabel() {
            return this.stLabelOf(this.stPick);
        },

        stPrevKey() {
            const keys = this.data.hauling?.stations?.periods[this.stPeriod].keys || [];
            const i = keys.indexOf(this.stPick);
            return i > 0 ? keys[i - 1] : null;
        },

        stPrevLabel() {
            const k = this.stPrevKey();
            return k ? this.stLabelOf(k) : '';
        },

        // Stations for the picked period, the most gross cane first
        stRows() {
            const st = this.data.hauling?.stations;
            if (!st) return [];
            const prev = this.stPrevKey();
            const rows = st.stations.map(s => {
                const c = this.stCell(s.name, this.stPick);
                const p = prev ? this.stCell(s.name, prev) : null;
                return {
                    name: s.name, code: s.code, gross: c.gross, net: c.net, tickets: c.tickets,
                    lossPct: c.gross > 0 ? Math.round((c.gross - c.net) / c.gross * 10000) / 100 : null,
                    change: p && p.gross > 0 ? Math.round((c.gross - p.gross) / p.gross * 1000) / 10 : null,
                };
            });
            const total = rows.reduce((a, r) => a + r.gross, 0);
            return rows.map(r => ({ ...r, share: total > 0 ? Math.round(r.gross / total * 1000) / 10 : 0 }))
                .sort((a, b) => b.gross - a.gross);
        },

        // The station with the lowest / highest value of a field in the picked period (stations with cane only)
        stBest(field, how) {
            const rows = this.stRows().filter(r => r.gross > 0 && r[field] !== null);
            if (!rows.length) return null;
            return rows.reduce((best, r) => (how === 'min' ? r[field] < best[field] : r[field] > best[field]) ? r : best).name;
        },

        stTopOf(key) {
            const st = this.data.hauling?.stations;
            if (!st) return null;
            let top = null, max = 0;
            st.stations.forEach(s => { const g = this.stCell(s.name, key).gross; if (g > max) { max = g; top = s.name; } });
            return top;
        },

        render_stations() {
            const st = this.data.hauling?.stations;
            if (!st || !st.stations.length || !document.getElementById('haulStationChart')) return;
            const t = this.theme();
            const rows = this.stRows();
            const options = this.baseOptions({ legend: true });
            options.plugins.tooltip.callbacks.label = (ctx) => ctx.dataset.label + ': ' + this.fmt(ctx.raw, 3) + ' t';
            this.chart('haulStationChart', {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.name),
                    datasets: [
                        this.bar('Gross Cane Wt', rows.map(r => r.gross), rows.map((r, i) => i === 0 ? '#087a3d' : t.colors[1])),
                        this.bar('Net Cane Wt', rows.map(r => r.net), t.muted),
                    ],
                },
                options,
            });

            const p = st.periods[this.stPeriod];
            const trendOptions = this.baseOptions({ legend: true });
            trendOptions.plugins.tooltip.callbacks.label = (ctx) => ctx.dataset.label + ': ' + this.fmt(ctx.raw ?? 0, 3) + ' t';
            const picked = p.keys.indexOf(this.stPick);
            this.chart('haulStationWeekChart', {
                type: this.stPeriod === 'year' ? 'bar' : 'line',
                data: {
                    labels: p.labels,
                    datasets: st.stations.map((s, i) => {
                        const color = t.colors[i % t.colors.length];
                        const data = p.keys.map(k => this.stCell(s.name, k).gross || null);
                        return this.stPeriod === 'year'
                            ? this.bar(s.name, data, color)
                            : { label: s.name, data, borderColor: color, backgroundColor: color, borderWidth: 2, tension: 0.3, spanGaps: true,
                                pointRadius: p.keys.map((k, j) => j === picked ? 6 : 3) };
                    }),
                },
                options: trendOptions,
            });
        },

        // ==================== BALANCE ====================

        visibleChecks() {
            if (!this.data.balance) return [];
            return this.data.balance.checks.filter(c =>
                (!this.onlyIssues || c.status === 'warning' || c.status === 'danger')
                && (!this.moduleFilter || c.modules.includes(this.moduleFilter)));
        },

        healthStatus() {
            const b = this.data.balance;
            if (!b) return 'info';
            if (b.counts.danger) return 'danger';
            if (b.counts.warning) return 'warning';
            return 'ok';
        },

        statusIcon(status) {
            return {
                ok: 'fas fa-circle-check',
                info: 'fas fa-circle-info',
                warning: 'fas fa-triangle-exclamation',
                danger: 'fas fa-circle-xmark',
            }[status] || 'fas fa-circle';
        },

        statusLabel(status) {
            return { ok: 'Balanced', info: 'Note', warning: 'Review', danger: 'Off balance' }[status] || status;
        },

        statusBadge(status) {
            return {
                active: 'kp-badge--forest', completed: 'kp-badge--info', approved: 'kp-badge--info',
                pending: 'kp-badge--amber', rejected: 'kp-badge--rose', cancelled: 'kp-badge--neutral',
            }[status] || 'kp-badge--neutral';
        },

        // ==================== FORMAT ====================

        fmt(number, decimals = 2) {
            const n = parseFloat(number);
            if (!isFinite(n)) return '0';
            return n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        },

        pct(part, whole) {
            const p = parseFloat(part) || 0;
            const w = parseFloat(whole) || 0;
            return w > 0 ? Math.round(p / w * 1000) / 10 : 0;
        },
    };
}
