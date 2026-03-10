<?php
if (!defined('PV_APP')) {
    http_response_code(403);
    echo 'Direct access denied';
    exit;
}

function pv_internal_service_token() {
    static $token = null;

    if (is_string($token)) {
        return $token;
    }

    $envToken = getenv('PV_INTERNAL_SERVICE_TOKEN');
    if ($envToken !== false && trim((string)$envToken) !== '') {
        $token = trim((string)$envToken);
        return $token;
    }

    $tokenPath = pv_storage_root() . '/secret_data/protected/internal_service.token';
    $fromFile = @file_get_contents($tokenPath);
    if (is_string($fromFile) && trim($fromFile) !== '') {
        $token = trim($fromFile);
        return $token;
    }

    // Fallback for first bootstrap before token file is created.
    $token = 'jv-internal-preview-token';
    return $token;
}

function pv_is_internal_service_request($providedToken) {
    $providedToken = trim((string)$providedToken);
    $expectedToken = pv_internal_service_token();

    if ($providedToken === '' || $expectedToken === '') {
        return false;
    }

    return hash_equals($expectedToken, $providedToken);
}

function pv_admin_service_response($action, $remoteAddr, $internalRequest = false) {
    $trustedLocals = ['127.0.0.1', '::1'];

    if (!in_array($remoteAddr, $trustedLocals, true)) {
        return [
            403,
            [
                'ok' => false,
                'error' => 'Access denied: localhost only.',
                'remote_addr' => $remoteAddr,
            ],
        ];
    }

    if ($internalRequest !== true) {
        return [
            403,
            [
                'ok' => false,
                'error' => 'Access denied: internal service token required.',
            ],
        ];
    }

    if ($action === 'flag') {
        $flagPath = pv_storage_root() . '/secret_data/protected/internal_service.flag';
        $flag = trim((string)@file_get_contents($flagPath));

        return [
            200,
            [
                'ok' => true,
                'service' => 'Club internal admin service',
                'flag' => $flag,
                'timestamp' => pv_date('c'),
            ],
        ];
    }

    return [
        200,
        [
            'ok' => true,
            'service' => 'Club internal admin service',
            'available_actions' => ['status', 'flag'],
        ],
    ];
}
