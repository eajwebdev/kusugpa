{{-- Money Trail: released to planters → recovered on Check Vouchers / paid in cash → still to collect, with interest.
     $withDetail adds the analytics charts, the week-by-week trail and the per-planter list (Loans & Advances tab). --}}
@php $sfx = $withDetail ? 'f' : 'o'; @endphp
<section class="kp-card overflow-hidden" aria-labelledby="trail-h-{{ $sfx }}">
    <div x-show="errors.trail" x-cloak class="kp-dash-error m-4" role="alert">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <span x-text="errors.trail"></span>
        <button type="button" class="kp-btn kp-btn-secondary" @click="load('trail', true)">Try again</button>
    </div>

    <template x-if="data.trail">
        <div>
            <!-- Hero: still to collect, recovery progress, the key figures -->
            <div class="relative p-5 sm:p-6 text-white" style="background: linear-gradient(135deg, #0b5d33 0%, #087a3d 55%, #2f8f5b 100%)">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="trail-h-{{ $sfx }}" class="text-xs font-bold uppercase tracking-widest text-white/80">
                            <i class="fas fa-route mr-1" aria-hidden="true"></i> Money Trail · Crop Year <span x-text="data.trail.scope.crop_year"></span>
                        </h2>
                        <p class="mt-2 text-xs text-white/75">Still to collect from planters</p>
                        <p class="text-3xl sm:text-4xl font-bold tabular leading-tight">₱<span x-text="fmt(data.trail.total.outstanding, 2)"></span></p>
                        <p class="mt-1 text-xs text-white/75">
                            <span x-show="data.trail.throughWeek">Through Week <span x-text="data.trail.throughWeek"></span> · as of <span x-text="data.trail.asOf"></span> · </span>
                            <span x-text="data.trail.analytics.plantersOwing + ' of ' + data.trail.analytics.planters + ' planter(s) still owe'"></span>
                        </p>
                    </div>
                    <!-- Recovery ring -->
                    <div class="flex items-center gap-3">
                        <svg viewBox="0 0 36 36" class="w-20 h-20 -rotate-90" aria-hidden="true">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,0.22)" stroke-width="3.2"></circle>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#ffffff" stroke-width="3.2" stroke-linecap="round"
                                :stroke-dasharray="Math.min(100, (data.trail.groups?.accounts ? data.trail.groups.accounts.rate : data.trail.total.rate) || 0) + ' 100'"></circle>
                        </svg>
                        <div>
                            <p class="text-2xl font-bold tabular" x-text="((data.trail.groups?.accounts ? data.trail.groups.accounts.rate : data.trail.total.rate) ?? 0) + '%'"></p>
                            <p class="text-xs text-white/75" x-show="data.trail.groups?.accounts">of loans &amp; cash advances<br>paid (recorded payments)</p>
                            <p class="text-xs text-white/75" x-show="!data.trail.groups?.accounts">recovered of<br>what was owed</p>
                            <p class="mt-1 text-[11px] text-white/70" x-show="data.trail.groups?.accounts && data.trail.groups?.deductions"
                                x-text="'Fuel & Others: ' + (data.trail.groups?.deductions?.rate ?? 0) + '% off vouchers'"></p>
                        </div>
                    </div>
                </div>
                <span class="absolute top-3 right-4 text-xs text-white/80" x-show="loading.trail" role="status"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Updating…</span>

                <p class="mt-4 rounded-lg bg-white/15 px-3 py-2 text-xs" x-show="data.trail.total.unrecorded > 0">
                    <i class="fas fa-circle-info mr-1" aria-hidden="true"></i>
                    <span x-text="'₱' + fmt(data.trail.total.unrecorded, 2)"></span> of cash advances / loans is on the Check Vouchers but not yet recorded as paid.
                    It is not counted as recovered until the payment is recorded on the Cash Advances / Loans page.
                </p>

                <!-- Key figures -->
                <dl class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-lg bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                        <dt class="text-[11px] uppercase tracking-wider text-white/75">Released to planters</dt>
                        <dd class="text-lg font-bold tabular">₱<span x-text="fmt(data.trail.total.released, 2)"></span></dd>
                    </div>
                    <div class="rounded-lg bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                        <dt class="text-[11px] uppercase tracking-wider text-white/75">Recovered (recorded / deducted)</dt>
                        <dd class="text-lg font-bold tabular">₱<span x-text="fmt(data.trail.total.recovered, 2)"></span></dd>
                    </div>
                    <div class="rounded-lg bg-white/10 px-3 py-2.5 backdrop-blur-sm">
                        <dt class="text-[11px] uppercase tracking-wider text-white/75">Interest earned</dt>
                        <dd class="text-lg font-bold tabular">₱<span x-text="fmt(data.trail.total.interest_earned, 2)"></span></dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2.5" style="color: #0b5d33">
                        <dt class="text-[11px] uppercase tracking-wider font-semibold">Interest collected</dt>
                        <dd class="text-lg font-bold tabular">₱<span x-text="fmt(data.trail.total.interest_collected, 2)"></span>
                            <span class="text-xs font-semibold" x-show="data.trail.total.interest_rate !== null" x-text="'(' + data.trail.total.interest_rate + '%)'"></span></dd>
                    </div>
                </dl>
            </div>

            <!-- The flow, step by step -->
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <template x-for="(step, i) in [
                    { n: 1, icon: 'fa-hand-holding-dollar', title: 'Released', value: data.trail.total.owed, note: '₱' + fmt(data.trail.total.released, 2) + ' + ₱' + fmt(data.trail.total.interest_earned, 2) + ' interest' + (data.trail.total.late_charges ? (data.trail.total.late_charges > 0 ? ' + ₱' : ' − ₱') + fmt(Math.abs(data.trail.total.late_charges), 2) + ' late charges / adj.' : ''), tone: '' },
                    { n: 2, icon: 'fa-file-invoice-dollar', title: 'Paid via voucher', value: data.trail.total.vouchers, note: 'Loans & CA: recorded voucher payments · Fuel & others: deducted on vouchers', tone: 'in' },
                    { n: 3, icon: 'fa-receipt', title: 'Paid in cash (OTC)', value: data.trail.total.cash, note: 'With an official receipt', tone: 'in' },
                    { n: 4, icon: 'fa-scale-balanced', title: 'Still to collect', value: data.trail.total.outstanding, note: data.trail.total.behind > 0 ? '₱' + fmt(data.trail.total.behind, 2) + ' behind schedule' : 'Nothing behind schedule', tone: data.trail.total.behind > 0 ? 'due' : 'ok' },
                ]" :key="step.n">
                    <div class="relative rounded-xl border p-3.5 transition-shadow hover:shadow-md" style="border-color: var(--kp-border)">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white"
                                :style="'background:' + (step.tone === 'in' ? '#087a3d' : step.tone === 'due' ? '#b42318' : step.tone === 'ok' ? '#2f8f5b' : '#2f6fb0')" x-text="step.n"></span>
                            <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--kp-muted)" x-text="step.title"></span>
                            <i class="fas ml-auto text-sm" :class="step.icon" style="color: var(--kp-faint)" aria-hidden="true"></i>
                        </div>
                        <p class="mt-2 text-xl font-bold tabular" :style="step.tone === 'due' ? 'color:#b42318' : ''">
                            <span x-text="(step.tone === 'in' ? '−' : '') + '₱' + fmt(step.value, 2)"></span></p>
                        <p class="text-xs" style="color: var(--kp-faint)" x-text="step.note"></p>
                        <i x-show="i < 3" class="fas fa-chevron-right absolute -right-2.5 top-1/2 hidden -translate-y-1/2 text-xs lg:block" style="color: var(--kp-faint)" aria-hidden="true"></i>
                    </div>
                </template>
            </div>

            <!-- Per account: progress bars, the same measure for every kind -->
            <div class="grid grid-cols-1 gap-3 px-4 pb-4 sm:grid-cols-2 lg:grid-cols-4">
                <template x-for="(k, i) in data.trail.kinds" :key="k.key">
                    <article class="rounded-xl p-3.5" style="background: var(--kp-surface-2)">
                        <header class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-sm font-semibold"><i class="kp-dash-swatch" :class="'kp-dash-swatch--' + (i + 1)"></i><span x-text="k.label"></span></span>
                            <span class="text-xs tabular" style="color: var(--kp-faint)" x-text="k.settled + '/' + k.accounts + ' settled'"></span>
                        </header>
                        <p class="mt-2 text-lg font-bold tabular">₱<span x-text="fmt(k.outstanding, 2)"></span> <span class="text-xs font-normal" style="color: var(--kp-faint)">to collect</span></p>
                        <div class="kp-dash-bar mt-1.5" role="progressbar" :aria-valuenow="k.rate || 0" aria-valuemin="0" aria-valuemax="100" :aria-label="k.label + ' recovered'">
                            <span :style="'width:' + Math.min(100, k.rate || 0) + '%'"></span>
                        </div>
                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs tabular">
                            <dt style="color: var(--kp-muted)">Recovered</dt><dd class="text-right" x-text="(k.rate ?? 0) + '%'"></dd>
                            <dt style="color: var(--kp-muted)">Released</dt><dd class="text-right" x-text="'₱' + fmt(k.released, 2)"></dd>
                            <template x-if="k.interest || k.interest_collected">
                                <dt style="color: var(--kp-muted)">Interest collected</dt>
                            </template>
                            <template x-if="k.interest || k.interest_collected">
                                <dd class="text-right" x-text="'₱' + fmt(k.interest_collected, 2)"></dd>
                            </template>
                            <dt style="color: var(--kp-muted)">Behind schedule</dt>
                            <dd class="text-right" :class="k.behind > 0 ? 'kp-dash-coll-danger' : ''" x-text="k.behind > 0 ? '₱' + fmt(k.behind, 2) : '—'"></dd>
                            <template x-if="k.unrecorded > 0"><dt style="color: var(--kp-muted)" title="On the Check Vouchers but not yet recorded as payments">On vouchers, not recorded</dt></template>
                            <template x-if="k.unrecorded > 0"><dd class="text-right" style="color: #b54708" x-text="'₱' + fmt(k.unrecorded, 2)"></dd></template>
                        </dl>
                    </article>
                </template>
            </div>

            @if ($withDetail)
                <!-- Analytics -->
                <div class="border-t px-4 pt-4" style="border-color: var(--kp-border)">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold"><i class="fas fa-chart-line mr-1" style="color: var(--kp-forest)" aria-hidden="true"></i> Analytics</h3>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="kp-badge" x-show="data.trail.analytics.bestWeek" x-text="'Best week: Wk ' + data.trail.analytics.bestWeek?.week + ' · ₱' + fmt(data.trail.analytics.bestWeek?.amount, 0)"></span>
                            <span class="kp-badge" x-text="'Avg recovered / week: ₱' + fmt(data.trail.analytics.avgWeeklyRecovery, 0)"></span>
                            <span class="kp-badge" x-show="data.trail.analytics.largestDebt" x-text="'Largest balance: ' + data.trail.analytics.largestDebt?.planter_name + ' ₱' + fmt(data.trail.analytics.largestDebt?.amount, 0)"></span>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <figure class="rounded-xl border p-3 lg:col-span-2" style="border-color: var(--kp-border)">
                            <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)">Money out vs money in, per week · with the balance still to collect</figcaption>
                            <div class="relative mt-2 w-full" style="height: 260px"><canvas id="trailFlowChart" role="img" aria-label="Bars of money released and recovered per week, line of the balance to collect"></canvas></div>
                        </figure>
                        <figure class="rounded-xl border p-3" style="border-color: var(--kp-border)">
                            <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)">How the money came back</figcaption>
                            <div class="relative mt-2 w-full" style="height: 260px"><canvas id="trailMixChart" role="img" aria-label="Doughnut of recovered on vouchers, paid in cash and still to collect"></canvas></div>
                        </figure>
                        <figure class="rounded-xl border p-3" style="border-color: var(--kp-border)">
                            <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)">Interest earned vs collected</figcaption>
                            <div class="relative mt-2 w-full" style="height: 220px"><canvas id="trailInterestChart" role="img" aria-label="Bars of interest earned and collected per account"></canvas></div>
                        </figure>
                        <figure class="rounded-xl border p-3 lg:col-span-2" style="border-color: var(--kp-border)">
                            <figcaption class="text-xs font-semibold" style="color: var(--kp-muted)">Recovery by account</figcaption>
                            <div class="relative mt-2 w-full" style="height: 220px"><canvas id="trailKindChart" role="img" aria-label="Stacked bars of recovered and still to collect per account"></canvas></div>
                        </figure>
                    </div>
                </div>

                <!-- Week by week -->
                <div class="mt-4 border-t" style="border-color: var(--kp-border)">
                    <div class="flex items-baseline justify-between px-4 pt-4 pb-2">
                        <h3 class="text-sm font-semibold">Week by Week</h3>
                        <span class="text-xs" style="color: var(--kp-faint)">Released in the week given out (or first deducted) · recovered in the week of the voucher / payment</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr>
                                <th scope="col">Week</th>
                                <th scope="col" class="text-right">Released</th>
                                <th scope="col" class="text-right">Via voucher</th>
                                <th scope="col" class="text-right">Cash (OTC)</th>
                                <th scope="col" class="text-right">Balance to collect</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="w in data.trail.weekly" :key="w.week">
                                    <tr>
                                        <td><span class="font-medium" x-text="'Week ' + w.week"></span>
                                            <span class="text-xs" style="color: var(--kp-faint)" x-show="w.start" x-text="w.start + ' – ' + w.end"></span></td>
                                        <td class="text-right tabular" :style="w.released ? '' : 'color: var(--kp-faint)'" x-text="w.released ? fmt(w.released, 2) : '—'"></td>
                                        <td class="text-right tabular" :style="w.vouchers ? 'color: var(--kp-forest)' : 'color: var(--kp-faint)'" x-text="w.vouchers ? '−' + fmt(w.vouchers, 2) : '—'"></td>
                                        <td class="text-right tabular" :style="w.cash ? 'color: var(--kp-forest)' : 'color: var(--kp-faint)'" x-text="w.cash ? '−' + fmt(w.cash, 2) : '—'"></td>
                                        <td class="text-right tabular font-semibold" x-text="fmt(w.balance, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.trail.weekly.length"><td colspan="5" class="text-center text-sm" style="color: var(--kp-muted)">No weeks set up for this crop year yet</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Per account table -->
                <div class="border-t" style="border-color: var(--kp-border)">
                    <h3 class="px-4 pt-4 pb-2 text-sm font-semibold">By Account</h3>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr>
                                <th scope="col">Account</th>
                                <th scope="col" class="text-right">Released</th>
                                <th scope="col" class="text-right">Interest &amp; charges</th>
                                <th scope="col" class="text-right">Interest collected</th>
                                <th scope="col" class="text-right">Via voucher</th>
                                <th scope="col" class="text-right">Cash (OTC)</th>
                                <th scope="col" class="text-right">Recovered</th>
                                <th scope="col" class="text-right">Still to collect</th>
                                <th scope="col" class="text-right" title="On the Check Vouchers but not yet recorded as payments">On vouchers, not recorded</th>
                                <th scope="col" class="text-right" title="Scheduled to be repaid by now but not yet recovered">Behind schedule</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="k in data.trail.kinds" :key="k.key">
                                    <tr>
                                        <td class="font-medium" x-text="k.label"></td>
                                        <td class="text-right tabular" x-text="fmt(k.released, 2)"></td>
                                        <td class="text-right tabular" :style="k.interest ? '' : 'color: var(--kp-faint)'" x-text="k.interest ? fmt(k.interest, 2) : '—'"></td>
                                        <td class="text-right tabular" :style="k.interest_collected ? '' : 'color: var(--kp-faint)'" x-text="k.interest_collected ? fmt(k.interest_collected, 2) : '—'"></td>
                                        <td class="text-right tabular" x-text="fmt(k.vouchers, 2)"></td>
                                        <td class="text-right tabular" :style="k.cash ? '' : 'color: var(--kp-faint)'" x-text="k.cash ? fmt(k.cash, 2) : '—'"></td>
                                        <td class="text-right tabular" x-text="fmt(k.recovered, 2) + ' (' + (k.rate ?? 0) + '%)'"></td>
                                        <td class="text-right tabular font-semibold" x-text="fmt(k.outstanding, 2)"></td>
                                        <td class="text-right tabular" :style="k.unrecorded > 0 ? 'color:#b54708' : 'color: var(--kp-faint)'" x-text="k.unrecorded > 0 ? fmt(k.unrecorded, 2) : '—'"></td>
                                        <td class="text-right tabular" :class="k.behind > 0 ? 'kp-dash-coll-danger' : ''" :style="k.behind > 0 ? '' : 'color: var(--kp-faint)'" x-text="k.behind > 0 ? fmt(k.behind, 2) : '—'"></td>
                                    </tr>
                                </template>
                                <tr class="font-semibold">
                                    <td>Total</td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.released, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.interest, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.interest_collected, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.vouchers, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.cash, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.recovered, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.outstanding, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.unrecorded, 2)"></td>
                                    <td class="text-right tabular" x-text="fmt(data.trail.total.behind, 2)"></td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="px-4 py-2 text-xs" style="color: var(--kp-faint)">
                            Still to collect = released + interest &amp; charges − via voucher − cash. Loans &amp; cash advances count only RECORDED payments (the same as their pages);
                            fuel &amp; others count what the Week Bought Check Vouchers deduct. "On vouchers, not recorded" is scheduled on a voucher but not yet recorded as paid.
                            Interest collected counts each account's interest in proportion to what the planter has repaid of it.
                        </p>
                    </div>
                </div>

                <!-- Per planter -->
                <div class="border-t" style="border-color: var(--kp-border)">
                    <div class="flex items-baseline justify-between px-4 pt-4 pb-2">
                        <h3 class="text-sm font-semibold">Who Still Owes</h3>
                        <span class="text-xs" style="color: var(--kp-faint)" x-text="'Top ' + data.trail.planters.length + ' of ' + data.trail.planterCount + ' planter(s)'"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table>
                            <thead><tr>
                                <th scope="col">Planter</th>
                                <template x-for="k in data.trail.kinds" :key="k.key"><th scope="col" class="text-right" x-text="k.label"></th></template>
                                <th scope="col" class="text-right">Total</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="p in data.trail.planters" :key="p.planter_code">
                                    <tr>
                                        <td><span class="font-medium" x-text="p.planter_name"></span> <span class="text-xs" style="color: var(--kp-faint)" x-text="p.planter_code"></span></td>
                                        <template x-for="k in data.trail.kinds" :key="k.key">
                                            <td class="text-right tabular" :style="Math.abs(p[k.key]) > 0.004 ? '' : 'color: var(--kp-faint)'" x-text="Math.abs(p[k.key]) > 0.004 ? fmt(p[k.key], 2) : '—'"></td>
                                        </template>
                                        <td class="text-right tabular font-semibold" x-text="fmt(p.outstanding, 2)"></td>
                                    </tr>
                                </template>
                                <tr x-show="!data.trail.planters.length"><td :colspan="data.trail.kinds.length + 2" class="text-center text-sm" style="color: var(--kp-muted)">Nobody owes anything for this crop year</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="px-4 py-2 text-xs kp-dash-coll-danger" x-show="data.trail.overRecovered > 0"
                        x-text="data.trail.overRecovered + ' planter(s) had more deducted than they owe. Check their accounts.'"></p>
                </div>
            @endif
        </div>
    </template>
    <div x-show="loading.trail && !data.trail" class="kp-dash-loading-card"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Loading the money trail…</div>
</section>
