<?php

return [
    'api_base_url' => env('YOYOTRANSLATE_API_URL', 'https://api.yoyotranslate.app'),
    'api_key' => env('YOYOTRANSLATE_API_KEY'),
    'ws_base_url' => env('YOYOTRANSLATE_WS_URL', 'wss://api.yoyotranslate.app/events'),
    'ws_lang' => env('YOYOTRANSLATE_WS_LANG', 'all'),
];
