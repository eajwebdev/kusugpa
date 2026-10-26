{{-- Collections Tracker: what planters owe today, per account kind, and in total. $withPlanters adds the per-planter list. --}}
<section class="kp-card kp-dash-coll" aria-labelledby="coll-h-{{ $withPlanters ? 'f' : 'o' }}">
    <div class="kp-card-head">
        <div>
            <h2 id="coll-h-{{ $withPlanters ? 'f' : 'o' }}" class="kp-card-title">Collections Tracker</h2>
            <p class="kp-card-sub">What planters owe the association today · all open accounts, any crop year<span x-show="data.collections"> · as of <span x-text="data.collections?.asOf"></span></span></p>
        </div>
        <span class="kp-dash-refreshing" x-show="loading.collections" role="status"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Updating…</span>
    </div>

    <div x-show="errors.collections" x-cloak class="kp-dash-error m-4" role="alert">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <span x-text="errors.collections"></span>
        <button type="button" class="kp-btn kp-btn-secondary" @click="load('collections', true)">Try again</button>
    </div>

    <template x-if="data.collections">
        <div>
            <!-- Total -->
            <div class="kp-dash-coll-total">
                <div class="kp-dash-coll-hero">
                    <p class="kp-dash-coll-eyebrow">Total to collect</p>
                    <p class="kp-dash-coll-big tabular"><small>₱</small><span x-text="fmt(data.collections.total.remaining, 2)"></span></p>
                    <p class="kp-dash-coll-meta">
                        <span x-text="data.collections.planterCount + ' planter(s) · ' + data.collections.total.accounts + ' open account(s)'"></span>
                    </p>
                    <div class="kp-dash-stack kp-dash-coll-stack mt-3" role="img"
                        :aria-label="'Still to collect by account: ' + data.collections.types.map(t => t.label + ' ₱' + fmt(t.remaining, 0)).join(', ')">
                        <template x-for="(t, i) in data.collections.types" :key="t.key">
                            <span class="kp-dash-stack-seg" :class="'kp-dash-stack-seg--' + (i + 1)" :style="'flex-grow:' + t.remaining"></span>
                        </template>
                    </div>
                    <ul class="kp-dash-legend mt-2">
                        <template x-for="(t, i) in data.collections.types" :key="t.key">
                            <li class="inline-flex items-center gap-1.5"><i class="kp-dash-swatch" :class="'kp-dash-swatch--' + (i + 1)"></i>
                                <span x-text="t.label + ' ' + pct(t.remaining, data.collections.total.remaining) + '%'"></span></li>
                        </template>
                    </ul>
                </div>
                <dl class="kp-dash-coll-figs">
                    <div><dt>Released</dt><dd class="tabular">₱<span x-text="fmt(data.collections.total.released, 2)"></span></dd>
                        <dd class="kp-dash-coll-note" x-show="data.collections.total.interest > 0">+ ₱<span x-text="fmt(data.collections.total.interest, 2)"></span> interest</dd></div>
                    <div><dt>Collected</dt><dd class="tabular">₱<span x-text="fmt(data.collections.total.collected, 2)"></span></dd>
                        <dd class="kp-dash-coll-note" x-text="data.collections.total.rate === null ? 'nothing open' : data.collections.total.rate + '% of what is open'"></dd></div>
                    <div><dt>Overdue</dt>
                        <dd class="tabular" :class="data.collections.total.overdue > 0 ? 'kp-dash-coll-danger' : ''">₱<span x-text="fmt(data.collections.total.overdue, 2)"></span></dd>
                        <dd class="kp-dash-coll-note">
                            <span class="kp-dash-status" :class="data.collections.total.overdueCount ? 'kp-dash-status--danger' : 'kp-dash-status--ok'">
                                <i :class="statusIcon(data.collections.total.overdueCount ? 'danger' : 'ok')" aria-hidden="true"></i>
                                <span x-text="data.collections.total.overdueCount ? data.collections.total.overdueCount + ' past due' : 'None past due'"></span></span></dd></div>
                    <div><dt>Due next 30 days</dt><dd class="tabular">₱<span x-text="fmt(data.collections.total.due30, 2)"></span></dd>
                        <dd class="kp-dash-coll-note">to collect soon</dd></div>
                </dl>
            </div>

            <!-- Per account kind -->
            <div class="kp-dash-coll-types">
                <template x-for="(t, i) in data.collections.types" :key="t.key">
                    <article class="kp-dash-coll-type">
                        <header class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-sm font-semibold">
                                <i class="kp-dash-swatch" :class="'kp-dash-swatch--' + (i + 1)"></i>
                                <i :class="t.icon" style="color: var(--kp-muted)" aria-hidden="true"></i>
                                <span x-text="t.label"></span>
                            </span>
                            <span class="text-xs" style="color: var(--kp-faint)" x-text="t.accounts + ' open'"></span>
                        </header>
                        <p class="mt-2 text-xs" style="color: var(--kp-muted)">To collect</p>
                        <p class="kp-dash-coll-amount tabular">₱<span x-text="fmt(t.remaining, 2)"></span></p>
                        <div class="kp-dash-bar mt-2" role="progressbar" :aria-valuenow="t.rate || 0" aria-valuemin="0" aria-valuemax="100" :aria-label="t.label + ' collected'">
                            <span :style="'width:' + (t.rate || 0) + '%'"></span>
                        </div>
                        <p class="mt-1 text-xs tabular" style="color: var(--kp-faint)" x-text="t.rate === null ? 'No open accounts' : t.rate + '% collected'"></p>
                        <dl class="kp-dash-coll-rows">
                            <div><dt>Released</dt><dd class="tabular" x-text="'₱' + fmt(t.released, 2)"></dd></div>
                            <div x-show="t.interest > 0"><dt>Interest</dt><dd class="tabular" x-text="'₱' + fmt(t.interest, 2)"></dd></div>
                            <div><dt x-text="t.basis === 'voucher' ? 'Applied on vouchers' : 'Collected'"></dt><dd class="tabular" x-text="'₱' + fmt(t.collected, 2)"></dd></div>
                            <div><dt>Overdue</dt>
                                <dd class="tabular" :class="t.overdue > 0 ? 'kp-dash-coll-danger' : ''">
                                    <i class="fas fa-circle-exclamation" x-show="t.overdue > 0" aria-hidden="true"></i>
                                    <span x-text="'₱' + fmt(t.overdue, 2)"></span></dd></div>
                            <div><dt>Due next 30 days</dt><dd class="tabular" x-text="'₱' + fmt(t.due30, 2)"></dd></div>
                        </dl>
                        <p class="kp-dash-coll-foot" x-show="t.basis === 'voucher'">Taken off the Check Voucher in the weeks the planter delivers.</p>
                        <button type="button" class="kp-dash-coll-warn" x-show="t.mismatch" @click="select('balance')">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Collected + to collect ≠ receivable. See Balance Check
                        </button>
                        <a x-show="t.link" :href="t.link" class="kp-dash-coll-link">Open <span x-text="t.label"></span> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </article>
                </template>
            </div>

            @if ($withPlanters)
                <div class="border-t" style="border-color: var(--kp-border)">
                    <div class="flex items-baseline justify-between px-4 pt-4 pb-2">
                        <h3 class="text-sm font-semibold">Who Owes What</h3>
                        <span class="text-xs" style="color: var(--kp-faint)" x-text="'Top ' + data.collections.planters.length + ' of ' + data.collections.planterCount + ' planters'"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr>
                                <th scope="col">Planter</th>
                                <template x-for="t in data.collections.types" :key="t.key"><th scope="col" class="text-right" x-text="t.label"></th></template>
                                <th scope="col" class="text-right">Total</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="p in data.collections.planters" :key="p.planter_code">
                                    <tr>
                                        <td><span class="font-medium" x-text="p.planter_name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="p.planter_code"></span></td>
                                        <template x-for="t in data.collections.types" :key="t.key">
                                            <td class="text-right tabular" :style="p[t.key] ? '' : 'color: var(--kp-faint)'" x-text="p[t.key] ? fmt(p[t.key], 2) : '—'"></td>
                                        </template>
                                        <td class="text-right tabular font-semibold" x-text="fmt(p.total, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.collections.planters.length"><td :colspan="data.collections.types.length + 2" class="text-center text-sm" style="color: var(--kp-muted)">No planter owes anything right now</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </template>
    <div x-show="loading.collections && !data.collections" class="kp-dash-loading-card"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Loading…</div>
</section>
