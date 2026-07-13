<?php

declare(strict_types=1);

return [
    'admin_base_domains' => explode(',', (string) env('TENANT_ADMIN_BASE_DOMAINS', 'app.syifa.my')),
];
