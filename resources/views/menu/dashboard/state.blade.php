{{-- Loading / error line of one dashboard tab --}}
<div x-show="loading.{{ $key }} && !data.{{ $key }}" class="kp-card kp-dash-loading-card" role="status">
    <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Loading…
</div>
<div x-show="errors.{{ $key }}" x-cloak class="kp-dash-error" role="alert">
    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
    <span x-text="errors.{{ $key }}"></span>
    <button type="button" class="kp-btn kp-btn-secondary" @click="load('{{ $key }}', true)">Try again</button>
</div>
<p x-show="loading.{{ $key }} && data.{{ $key }}" x-cloak class="kp-dash-refreshing" role="status">
    <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Updating…
</p>
