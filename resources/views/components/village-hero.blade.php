@props(['villageName' => null])
@php
    $label = \Illuminate\Support\Str::upper($villageName ?: 'Balai Desa');
    $long = mb_strlen($label) > 14;
@endphp
<svg class="village-hero" viewBox="0 0 780 860" role="img" aria-label="Ilustrasi balai desa {{ $villageName ?: '' }} dengan pendopo, bendera, dan suasana desa">
<defs>
<clipPath id="vh-arch"><path d="M0,390 A390,390 0 0 1 780,390 L780,824 Q780,860 744,860 L36,860 Q0,860 0,824 Z"/></clipPath>
<clipPath id="vh-gnd"><ellipse cx="390" cy="870" rx="510" ry="230"/></clipPath>
<linearGradient id="vh-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f1f6f2"/><stop offset="1" stop-color="#e5eee8"/></linearGradient>
</defs>
<g clip-path="url(#vh-arch)">
    <rect width="780" height="860" fill="#e5eee8"/>
    <rect width="780" height="700" fill="url(#vh-sky)"/>

    <g class="vh-sun"><circle cx="515" cy="225" r="115" fill="#e7a33e" opacity=".18"/><circle cx="515" cy="225" r="75" fill="#e7a33e"/></g>

    <g class="vh-cloud vh-cloud-a" fill="#fffdf8"><rect x="40" y="200" width="170" height="46" rx="23"/><rect x="90" y="176" width="80" height="50" rx="25"/></g>
    <g class="vh-cloud vh-cloud-b"><rect x="560" y="290" width="120" height="36" rx="18" fill="#fffdf8" opacity=".9"/></g>

    <ellipse class="vh-hill vh-hill-a" cx="210" cy="730" rx="450" ry="260" fill="#2d7a64"/>
    <ellipse class="vh-hill vh-hill-b" cx="710" cy="710" rx="410" ry="280" fill="#216352"/>

    <g class="vh-ground">
        <ellipse cx="390" cy="870" rx="510" ry="230" fill="#174b3f"/>
        <g clip-path="url(#vh-gnd)" fill="none" stroke="#216352" stroke-width="5">
            <ellipse cx="390" cy="910" rx="590" ry="200"/><ellipse cx="390" cy="970" rx="630" ry="200"/><ellipse cx="390" cy="1030" rx="670" ry="200"/>
        </g>
    </g>

    <polygon class="vh-road" points="364,688 416,688 520,868 260,868" fill="#fbf8ef" opacity=".9"/>

    <g class="vh-tree vh-tree-a"><rect x="34" y="672" width="12" height="98" rx="3" fill="#0b2a24"/><circle cx="40" cy="630" r="70" fill="#216352"/></g>
    <g class="vh-tree vh-tree-b"><rect x="694" y="636" width="12" height="84" rx="3" fill="#0b2a24"/><circle cx="700" cy="600" r="60" fill="#2d7a64"/></g>
    <g class="vh-tree vh-tree-c"><rect x="754" y="664" width="12" height="56" rx="3" fill="#0b2a24"/><circle cx="760" cy="640" r="40" fill="#216352"/></g>

    <g class="vh-platform"><rect x="150" y="662" width="480" height="30" rx="6" fill="#fbf8ef"/><rect x="150" y="686" width="480" height="6" fill="#dfe7e1"/></g>

    <g class="vh-wall">
        <rect x="210" y="532" width="360" height="130" fill="#fffdf8"/>
        <g fill="#12372f"><rect x="220" y="532" width="14" height="130"/><rect x="300" y="532" width="14" height="130"/><rect x="380" y="532" width="14" height="130"/><rect x="460" y="532" width="14" height="130"/><rect x="540" y="532" width="14" height="130"/></g>
        <path d="M360,662 V590 Q360,582 368,582 H412 Q420,582 420,590 V662 Z" fill="#b94f31"/>
        <rect x="259.5" y="593.5" width="37" height="33" rx="3" fill="#e5eee8" stroke="#216352" stroke-width="3"/>
        <rect x="483.5" y="593.5" width="37" height="33" rx="3" fill="#e5eee8" stroke="#216352" stroke-width="3"/>
    </g>

    <polygon class="vh-roof-lower" points="236,470 544,470 630,542 150,542" fill="#b94f31"/>
    <g class="vh-roof-upper">
        <rect x="240" y="462" width="300" height="14" rx="4" fill="#fbf8ef"/>
        <polygon points="340,368 440,368 515,468 265,468" fill="#dc6b45"/>
        <rect x="335" y="362" width="110" height="12" rx="6" fill="#12372f"/>
    </g>

    <g class="vh-sign">
        <rect x="290" y="544" width="200" height="34" rx="6" fill="#12372f"/>
        <text x="390" y="566.5" text-anchor="middle" font-family="'Instrument Sans','Segoe UI',Helvetica,Arial,sans-serif" font-size="15" font-weight="700" letter-spacing="2.5" fill="#e7a33e"@if ($long) textLength="176" lengthAdjust="spacingAndGlyphs"@endif>{{ $label }}</text>
    </g>

    <rect class="vh-pole" x="86" y="360" width="6" height="332" rx="3" fill="#17231f"/>
    <circle class="vh-knob" cx="89" cy="358" r="8" fill="#e7a33e"/>
    <g class="vh-flag"><g class="vh-flag-wave"><rect x="92" y="372" width="96" height="31" fill="#c8493a"/><rect x="92" y="403" width="96" height="31" fill="#fffdf8"/></g></g>
</g>
</svg>
