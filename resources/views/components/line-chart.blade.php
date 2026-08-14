@props([
    'series' => [],
    'valueKey' => 'revenue',
    'height' => 210,
    'id' => 'chart',
])

@php
    /**
     * A hand-drawn SVG sparkline. Charting libraries are avoided so the app
     * has no build step and no CDN dependency on shared hosting.
     */
    $points = collect($series)->values();
    $count = $points->count();

    $values = $points->pluck($valueKey)->map(fn ($v) => (float) $v);
    $max = (float) ($values->max() ?: 0);
    $min = 0.0;

    // Pad the top so the peak never touches the frame.
    $ceiling = $max > 0 ? $max * 1.12 : 1;

    $w = 800;
    $h = (int) $height;
    $padX = 8;
    $padY = 14;
    $innerW = $w - ($padX * 2);
    $innerH = $h - ($padY * 2) - 18;

    $coords = [];
    foreach ($points as $i => $row) {
        $x = $count > 1 ? $padX + ($i / ($count - 1)) * $innerW : $w / 2;
        $y = $padY + $innerH - (((float) $row[$valueKey] - $min) / ($ceiling - $min)) * $innerH;
        $coords[] = [round($x, 2), round($y, 2)];
    }

    $line = '';
    foreach ($coords as $i => [$x, $y]) {
        $line .= ($i === 0 ? 'M' : 'L')." {$x} {$y} ";
    }

    $area = $line;
    if ($coords) {
        $lastX = $coords[count($coords) - 1][0];
        $firstX = $coords[0][0];
        $baseY = $padY + $innerH;
        $area .= "L {$lastX} {$baseY} L {$firstX} {$baseY} Z";
    }

    // Label every nth point so the axis never crowds.
    $step = max(1, (int) ceil($count / 8));
@endphp

<svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none"
     style="height:{{ $h }}px;width:100%" role="img" aria-label="Grafik tren">
    <defs>
        <linearGradient id="fill-{{ $id }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--brand-500)" stop-opacity=".26"/>
            <stop offset="100%" stop-color="var(--brand-500)" stop-opacity="0"/>
        </linearGradient>
    </defs>

    <g class="chart__grid">
        @for ($i = 0; $i <= 3; $i++)
            @php $gy = $padY + ($innerH / 3) * $i; @endphp
            <line x1="{{ $padX }}" y1="{{ $gy }}" x2="{{ $w - $padX }}" y2="{{ $gy }}" stroke-dasharray="3 5"/>
        @endfor
    </g>

    @if ($count > 0)
        <path d="{{ $area }}" fill="url(#fill-{{ $id }})"/>
        <path d="{{ $line }}" class="chart__line" vector-effect="non-scaling-stroke"/>

        @foreach ($coords as $i => [$x, $y])
            @if ($count <= 32)
                <circle cx="{{ $x }}" cy="{{ $y }}" r="3" class="chart__dot" vector-effect="non-scaling-stroke">
                    <title>{{ $points[$i]['label'] ?? '' }} — {{ money($points[$i][$valueKey]) }}</title>
                </circle>
            @endif
        @endforeach
    @endif
</svg>

<div class="row between" style="padding:0 8px;margin-top:-6px">
    @foreach ($points as $i => $row)
        @if ($i % $step === 0 || $i === $count - 1)
            <span class="tiny subtle">{{ $row['label'] ?? '' }}</span>
        @endif
    @endforeach
</div>
