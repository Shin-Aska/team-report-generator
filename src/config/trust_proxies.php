<?php

use Illuminate\Http\Request;

return [
    // Put the env() here (this is where Laravel expects env() to be used)
    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,
];
