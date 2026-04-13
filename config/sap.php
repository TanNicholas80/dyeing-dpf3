<?php

/**
 * Konfigurasi API SAP (dipakai AuxlController & ProsesController).
 *
 * .env yang disarankan:
 *   SAP_BASE_URL=http://host:port
 *   SAP_CLIENT=100
 *   SAP_AUTHORIZATION_HEADER="Basic xxx"   (opsi 1: header lengkap)
 *   SAP_USERNAME=...                       (opsi 2: dipakai jika header tidak di-set)
 *   SAP_PASSWORD=...
 *   SAP_HTTP_TIMEOUT=30
 *   SAP_HTTP_TIMEOUT_PROXY=10
 *   SAP_PATH_ZTERIMA_OP=/sap/bc/zdyes/zterima_op   (opsional override path)
 */

$baseUrl = rtrim((string) env('SAP_BASE_URL', 'http://18.139.142.16:8020'), '/');

$authorization = env('SAP_AUTHORIZATION_HEADER');
if (! filled($authorization)) {
    $user = env('SAP_USERNAME');
    $password = env('SAP_PASSWORD');
    if (filled($user) && $password !== null && $password !== '') {
        $authorization = 'Basic ' . base64_encode($user . ':' . $password);
    }
}
// Agar install lama tetap jalan sebelum .env diisi; set SAP_AUTHORIZATION_HEADER untuk override.
if (! filled($authorization)) {
    $authorization = 'Basic RFRfV01TOldtczAxMTEyMDI1QA==';
}

return [
    'base_url' => $baseUrl,
    'sap_client' => (string) env('SAP_CLIENT', '100'),
    'authorization' => $authorization,

    'timeout' => [
        'default' => (int) env('SAP_HTTP_TIMEOUT', 30),
        'proxy' => (int) env('SAP_HTTP_TIMEOUT_PROXY', 10),
    ],

    /**
     * Path relatif (tanpa query ?sap-client=).
     * Override per endpoint lewat .env jika path SAP berubah.
     */
    'paths' => [
        'zterima_op' => env('SAP_PATH_ZTERIMA_OP', '/sap/bc/zdyes/zterima_op'),
        'zterima_data' => env('SAP_PATH_ZTERIMA_DATA', '/sap/bc/zdyes/zterima_data'),
        'zterima_kimia' => env('SAP_PATH_ZTERIMA_KIMIA', '/sap/bc/zdyes/zterima_kimia'),
        'zterima_cancel' => env('SAP_PATH_ZTERIMA_CANCEL', '/sap/bc/zdyes/zterima_cancel'),
        'zterima_zchm' => env('SAP_PATH_ZTERIMA_ZCHM', '/sap/bc/zdyes/zterima_zchm'),
        'zterima_cstmr' => env('SAP_PATH_ZTERIMA_CSTMR', '/sap/bc/zdyes/zterima_cstmr'),
        'zterima_mkt' => env('SAP_PATH_ZTERIMA_MKT', '/sap/bc/zdyes/zterima_mkt'),
    ],
];
