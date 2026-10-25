{{--
    Last row of a "load more on scroll" table. Scrolling it into view loads the next batch.
    @include('layouts.partials.feed-footer', ['feed' => 'quedanFeed', 'colspan' => 10, 'empty' => 'No records match the filters.'])
--}}
<tr x-load-more="{{ $feed }}.more()" data-no-hover>
    <td colspan="{{ $colspan }}" class="text-center text-xs" style="color: var(--kp-muted); padding-top: 14px; padding-bottom: 14px;">
        <span x-show="{{ $feed }}.loading"><i class="fas fa-circle-notch fa-spin mr-1" aria-hidden="true"></i> Loading more…</span>
        <span x-show="!{{ $feed }}.loading && {{ $feed }}.error" class="text-red-500">
            <span x-text="{{ $feed }}.error"></span>
            <button type="button" class="underline ml-1" @click="{{ $feed }}.hasMore = true; {{ $feed }}.error = ''; {{ $feed }}.more()">Retry</button>
        </span>
        <span x-show="!{{ $feed }}.loading && !{{ $feed }}.error && !{{ $feed }}.hasMore && {{ $feed }}.rows.length > 0"
            x-text="'All ' + {{ $feed }}.rows.length.toLocaleString() + ' record(s) shown'"></span>
        <span x-show="!{{ $feed }}.loading && !{{ $feed }}.error && !{{ $feed }}.hasMore && {{ $feed }}.rows.length === 0">
            <i class="fas fa-inbox text-2xl mb-2 block opacity-40" aria-hidden="true"></i>{{ $empty ?? 'No records match the filters.' }}
        </span>
        <span x-show="!{{ $feed }}.loading && {{ $feed }}.hasMore && {{ $feed }}.rows.length > 0" class="opacity-60">Scroll for more</span>
    </td>
</tr>
