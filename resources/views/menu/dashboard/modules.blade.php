{{-- Overview: one figure per module the user can open, and the cross-module balance health --}}
<section class="kp-card" aria-labelledby="modules-h">
    <div class="kp-card-head">
        <div>
            <h2 id="modules-h" class="kp-card-title">Modules at a Glance</h2>
            <p class="kp-card-sub">Every module for the selected crop year and week</p>
        </div>
        <button type="button" class="kp-dash-health" @click="select('balance')" x-show="data.balance"
            :class="'kp-dash-health--' + healthStatus()">
            <i :class="statusIcon(healthStatus())" aria-hidden="true"></i>
            <span>Balance health <strong class="tabular" x-text="(data.balance?.score ?? '—') + '%'"></strong></span>
            <span class="kp-dash-health-sub" x-text="balanceIssues ? balanceIssues + ' to review →' : 'All balanced →'"></span>
        </button>
    </div>
    <div class="kp-dash-modules">
        <template x-for="card in (data.modules?.cards || [])" :key="card.key">
            <div class="kp-dash-module">
                <span class="kp-dash-module-icon"><i :class="card.icon" aria-hidden="true"></i></span>
                <div class="min-w-0">
                    <p class="kp-dash-module-label" x-text="card.label"></p>
                    <p class="kp-dash-module-value tabular">
                        <span x-text="fmt(card.value, Number.isInteger(card.value) ? 0 : 2)"></span>
                        <small x-text="card.unit"></small>
                    </p>
                    <p class="kp-dash-module-foot" x-text="card.foot"></p>
                </div>
            </div>
        </template>
        <template x-if="loading.modules && !data.modules">
            <p class="kp-dash-loading"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Loading modules…</p>
        </template>
    </div>
</section>
