<?php

// TomYum game server integration (staff panels). Requests to its admin API are signed with the
// same SHARED_INTEROP_SECRET TomYum uses for InterOp (osu.legacy.shared_interop_secret).

return [
    // Where the website reaches TomYum's admin API: its compose service on the shared network.
    'admin_url' => env('TOMYUM_ADMIN_URL', 'http://server:8080'),
];
