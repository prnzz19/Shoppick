<?php

return [
    // Leaflet renders the pin; the default tiles are OpenStreetMap.
    // Set SHOPPICK_MAP_TILE_URL to an approved provider for production traffic.
    'tile_url' => env('SHOPPICK_MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('SHOPPICK_MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
];