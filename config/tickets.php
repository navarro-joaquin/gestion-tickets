<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de tasa de los endpoints públicos
    |--------------------------------------------------------------------------
    |
    | El quiosco y la pantalla no usan autenticación, por lo que se limitan
    | por IP. La pantalla hace polling cada 5 segundos (12 req/min); el
    | límite permite varias pantallas detrás de la misma IP o NAT.
    |
    */

    'rate_limits' => [
        'catalog_per_minute' => (int) env('TICKETS_RATE_LIMIT_CATALOG', 120),
        'tickets_per_minute' => (int) env('TICKETS_RATE_LIMIT_ISSUE', 30),
        'display_per_minute' => (int) env('TICKETS_RATE_LIMIT_DISPLAY', 240),
    ],

];
