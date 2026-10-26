{{-- Balance Check tab: does what one module hands to the next add up? --}}
<div x-show="tab === 'balance'" x-cloak id="panel-balance" role="tabpanel" aria-labelledby="tab-balance" class="space-y-4">
    @include('menu.dashboard.state', ['key' => 'balance'])

    <template x-if="data.balance">
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="kp-card kp-dash-score p-5" aria-labelledby="b-score-h">
                    <h2 id="b-score-h" class="kp-card-title">Balance Health</h2>
                    <p class="kp-card-sub">Crop year <span x-text="data.balance.scope.crop_year"></span><span x-text="data.balance.scope.week_no ? ', week ' + data.balance.scope.week_no : ', all weeks'"></span></p>
                    <div class="kp-dash-score-body">
                        <div class="kp-dash-ring" :class="'kp-dash-ring--' + healthStatus()" :style="'--p:' + data.balance.score"
                            role="img" :aria-label="'Balance health ' + data.balance.score + ' percent'">
                            <strong class="tabular" x-text="data.balance.score + '%'"></strong>
                        </div>
                        <ul class="kp-dash-score-counts">
                            <li><span class="kp-dash-status kp-dash-status--danger"><i :class="statusIcon('danger')" aria-hidden="true"></i> Off balance</span><strong class="tabular" x-text="data.balance.counts.danger"></strong></li>
                            <li><span class="kp-dash-status kp-dash-status--warning"><i :class="statusIcon('warning')" aria-hidden="true"></i> Review</span><strong class="tabular" x-text="data.balance.counts.warning"></strong></li>
                            <li><span class="kp-dash-status kp-dash-status--info"><i :class="statusIcon('info')" aria-hidden="true"></i> Note</span><strong class="tabular" x-text="data.balance.counts.info"></strong></li>
                            <li><span class="kp-dash-status kp-dash-status--ok"><i :class="statusIcon('ok')" aria-hidden="true"></i> Balanced</span><strong class="tabular" x-text="data.balance.counts.ok"></strong></li>
                        </ul>
                    </div>
                </section>

                <section class="kp-card p-5 lg:col-span-2" aria-labelledby="b-flow-h">
                    <h2 id="b-flow-h" class="kp-card-title">Module Flow</h2>
                    <p class="kp-card-sub mb-4">How data moves between modules. A module turns amber or red when a check touching it is off; click it to see those checks.</p>
                    <ol class="kp-dash-flow">
                        <template x-for="node in data.balance.flow" :key="node.key">
                            <li>
                                <button type="button" class="kp-dash-flow-node" :class="'kp-dash-flow-node--' + node.status"
                                    :aria-pressed="(moduleFilter === node.key).toString()"
                                    @click="moduleFilter = moduleFilter === node.key ? '' : node.key">
                                    <i :class="node.icon" aria-hidden="true"></i>
                                    <span x-text="node.key"></span>
                                    <span class="kp-dash-flow-state">
                                        <i :class="statusIcon(node.status)" aria-hidden="true"></i>
                                        <span x-text="node.issues ? node.issues + ' issue' + (node.issues > 1 ? 's' : '') : statusLabel(node.status)"></span>
                                    </span>
                                </button>
                            </li>
                        </template>
                    </ol>
                </section>
            </div>

            <section class="kp-card" aria-labelledby="b-checks-h">
                <div class="kp-card-head">
                    <div>
                        <h2 id="b-checks-h" class="kp-card-title">Cross-Module Checks</h2>
                        <p class="kp-card-sub">
                            <span x-text="visibleChecks().length + ' of ' + data.balance.checks.length + ' checks'"></span>
                            <span x-show="moduleFilter"> touching <strong x-text="moduleFilter"></strong>
                                <button type="button" class="underline" @click="moduleFilter = ''">show all</button></span>
                        </p>
                    </div>
                    <label class="flex items-center gap-2 text-xs" style="color: var(--kp-muted)">
                        <input type="checkbox" x-model="onlyIssues" class="rounded"> Only show issues
                    </label>
                </div>
                <ul class="divide-y">
                    <template x-for="check in visibleChecks()" :key="check.key">
                        <li class="kp-dash-check" :class="'kp-dash-check--' + check.status" x-data="{ open: false }">
                            <button type="button" class="kp-dash-check-head" @click="open = !open" :aria-expanded="open.toString()"
                                :disabled="check.status === 'ok' && !check.rows.length">
                                <span class="kp-dash-status" :class="'kp-dash-status--' + check.status">
                                    <i :class="statusIcon(check.status)" aria-hidden="true"></i>
                                    <span x-text="statusLabel(check.status)"></span>
                                </span>
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="block text-sm font-semibold" x-text="check.title"></span>
                                    <span class="block text-xs" style="color: var(--kp-muted)" x-text="check.summary"></span>
                                </span>
                                <span class="kp-dash-check-modules">
                                    <template x-for="m in check.modules" :key="m"><span class="kp-badge kp-badge--neutral" x-text="m"></span></template>
                                </span>
                                <i class="fas fa-chevron-down kp-dash-check-chev" :class="open ? 'rotate-180' : ''" x-show="check.status !== 'ok' || check.rows.length" aria-hidden="true"></i>
                            </button>
                            <div x-show="open" class="kp-dash-check-body">
                                <p class="text-xs" style="color: var(--kp-muted)" x-text="check.detail"></p>
                                <div class="mt-3 overflow-x-auto rounded-lg border" style="border-color: var(--kp-border)" x-show="check.rows.length">
                                    <table>
                                        <thead><tr><template x-for="c in check.columns" :key="c"><th scope="col" x-text="c"></th></template></tr></thead>
                                        <tbody>
                                            <template x-for="(row, i) in check.rows" :key="i">
                                                <tr><template x-for="c in check.columns" :key="c"><td class="text-sm tabular" x-text="row[c]"></td></template></tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-2 text-xs" style="color: var(--kp-faint)" x-show="check.count > check.rows.length"
                                    x-text="'Showing ' + check.rows.length + ' of ' + check.count + '.'"></p>
                                <a x-show="check.link" :href="check.link" class="kp-btn kp-btn-secondary mt-3 inline-flex text-xs">
                                    <span x-text="check.link_label"></span> <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </li>
                    </template>
                    <li x-show="!visibleChecks().length" class="px-5 py-8 text-center text-sm" style="color: var(--kp-muted)">
                        <i class="fas fa-check-circle" style="color: var(--kp-emerald)" aria-hidden="true"></i> Nothing to review here.
                    </li>
                </ul>
            </section>
        </div>
    </template>
</div>
