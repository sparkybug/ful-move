<?php

return [
    // Use "*" only behind a trusted ingress such as Render's load balancer.
    'proxies' => env('TRUSTED_PROXIES'),
];
