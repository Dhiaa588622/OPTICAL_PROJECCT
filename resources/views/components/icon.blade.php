@props(['name' => 'grid'])

@php
    $name = (string) $name;
@endphp

<svg class="icon-svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-3H4v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            @break
        @case('patients')
            <path d="M16 19a4 4 0 0 0-8 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 7a3.5 3.5 0 0 0-2.7-3.4M5 19a3.5 3.5 0 0 1 2.7-3.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('appointments')
            <path d="M7 3v3m10-3v3M4.5 9h15M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3 9 2 2 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('orders')
            <path d="M7 7c1.2-2 2.9-3 5-3s3.8 1 5 3M6 8h12l-1 12H7L6 8Zm3 4h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('sales')
            <path d="M5 6h16l-2 9H8L6 3H3m6 16a1 1 0 1 0 0 .1m9-.1a1 1 0 1 0 0 .1M10 10h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('inventory')
            <path d="m12 3 8 4-8 4-8-4 8-4Zm-8 8 8 4 8-4M4 15l8 4 8-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('whatsapp')
            <path d="M5.5 19.5 6.8 16A7.5 7.5 0 1 1 12 19.5c-1.2 0-2.4-.3-3.4-.8l-3.1.8Zm4.2-10.3c.4 2.2 2 3.8 4.3 4.6l1.2-1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('accounting')
            <path d="M5 5h14v14H5V5Zm3 4h8M8 13h3m3 0h2m-8 3h3m3 0h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('reports')
            <path d="M6 20V4h8l4 4v12H6Zm8-16v5h4M9 15h6M9 11h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('configuration')
            <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Zm0-12v2m0 13v2M4.6 6.6 6 8m12 8 1.4 1.4M3 12h2m14 0h2M4.6 17.4 6 16m12-8 1.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('users')
            <path d="M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 3c-3 0-5 1.7-5 4h10c0-2.3-2-4-5-4Zm9-5 2 2-4 4-2-2 4-4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('search')
            <path d="m20 20-4.2-4.2M18 11a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            @break
        @case('bell')
            <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7Zm-8 11h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('user')
            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('arrow')
            <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('box')
            <path d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7m-8 4v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            @break
        @case('truck')
            <path d="M3 6h11v10H3V6Zm11 4h4l3 3v3h-7v-6ZM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            @break
        @default
            <path d="M5 5h6v6H5V5Zm8 0h6v6h-6V5ZM5 13h6v6H5v-6Zm8 0h6v6h-6v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
    @endswitch
</svg>
