<figure class="manual-shot">
    <div class="manual-shot-frame">
        <img src="{{ asset('img/manual/' . $shot . '.jpg') }}" alt="{{ $alt }}" width="1366" height="768" loading="lazy" decoding="async">
        <svg class="manual-shot-arrows" viewBox="0 0 1366 768" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <marker id="manual-arrow-{{ $shot }}" markerWidth="11" markerHeight="11" refX="9" refY="5.5" orient="auto">
                    <path d="M0 0 L11 5.5 L0 11 Z" fill="#d82f34" />
                </marker>
            </defs>
            @foreach($targets as $target)
                @php
                    $startX = $target['x'] > 650 ? $target['x'] - 115 : $target['x'] + 115;
                    $startY = $target['y'] > 600 ? $target['y'] - 85 : $target['y'] + 85;
                @endphp
                <path d="M{{ $startX }} {{ $startY }} L{{ $target['x'] }} {{ $target['y'] }}" stroke="white" stroke-width="8" stroke-linecap="round" fill="none" />
                <path d="M{{ $startX }} {{ $startY }} L{{ $target['x'] }} {{ $target['y'] }}" stroke="#d82f34" stroke-width="4" stroke-linecap="round" fill="none" marker-end="url(#manual-arrow-{{ $shot }})" />
                <circle cx="{{ $startX }}" cy="{{ $startY }}" r="17" fill="#a91520" stroke="white" stroke-width="3" />
                <text x="{{ $startX }}" y="{{ $startY + 6 }}" text-anchor="middle" fill="white" font-size="17" font-weight="800" font-family="Arial,sans-serif">{{ $loop->iteration }}</text>
            @endforeach
        </svg>
    </div>
    <figcaption>
        <span class="manual-shot-key">@foreach($targets as $target)<span><b>{{ $loop->iteration }}</b> {{ $target['label'] }}</span>@endforeach</span>
        <a href="{{ asset('img/manual/' . $shot . '.jpg') }}" target="_blank" rel="noopener noreferrer">View full size <span aria-hidden="true">↗</span></a>
    </figcaption>
</figure>
