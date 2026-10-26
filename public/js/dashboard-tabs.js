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
            if (this.hasCollections()) this.load('collections');
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
            if (this.hasCollections()) this.load('collections');
            if (this.tab !== 'overview' && this.tab !== 'balance') this.load(this.tab);
        },

        async load(key, force = false) {
            if (!force && this.loading[key] && this.loadedFor[key] === this.scopeKey()) return;
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
        },

        render_finance(d) {
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
