@props(['class' => 'h-9 w-9'])

<svg {{ $attributes->merge(['class' => $class . ' shrink-0']) }} viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="SHOPPICK">
    {{-- red panda head --}}
    <circle cx="32" cy="32" r="30" fill="#14b8a6"/>
    {{-- ears --}}
    <path d="M14 22 L22 18 L20 28 Z" fill="#f97316"/>
    <path d="M50 22 L42 18 L44 28 Z" fill="#f97316"/>
    {{-- face --}}
    <circle cx="32" cy="34" r="19" fill="#fff7ed"/>
    {{-- ears inner --}}
    <path d="M16 24 L20 21 L19 26 Z" fill="#fff7ed"/>
    <path d="M48 24 L44 21 L45 26 Z" fill="#fff7ed"/>
    {{-- eye patches --}}
    <ellipse cx="24" cy="31" rx="6" ry="5" fill="#7c2d12"/>
    <ellipse cx="40" cy="31" rx="6" ry="5" fill="#7c2d12"/>
    {{-- eyes --}}
    <circle cx="25" cy="31" r="2.4" fill="#ffffff"/>
    <circle cx="39" cy="31" r="2.4" fill="#ffffff"/>
    {{-- nose --}}
    <path d="M29 39 L35 39 L32 44 Z" fill="#7c2d12"/>
    {{-- mouth --}}
    <path d="M28 45 Q32 49 36 45" stroke="#7c2d12" stroke-width="1.6" fill="none" stroke-linecap="round"/>
    {{-- cheeks --}}
    <circle cx="21" cy="37" r="2.2" fill="#fdba74"/>
    <circle cx="43" cy="37" r="2.2" fill="#fdba74"/>
    {{-- shopping bag --}}
    <path d="M23 50 L41 50 L44 56 L20 56 Z" fill="#f59e0b"/>
    <path d="M26 52 Q23 48 25 45 Q27 42 32 42 Q37 42 39 45 Q41 48 38 52" stroke="#b45309" stroke-width="1.8" fill="none"/>
    {{-- tag --}}
    <path d="M44 22 L52 16 L54 24 L46 30 Z" fill="#22c55e"/>
</svg>
