<?php

namespace App\Support;

class SapApi
{
    /**
     * URL penuh untuk endpoint SAP (termasuk ?sap-client=).
     *
     * @param  string  $pathKey  Kunci di config('sap.paths')
     */
    public static function url(string $pathKey): string
    {
        $paths = config('sap.paths', []);
        if (! isset($paths[$pathKey])) {
            throw new \InvalidArgumentException("SAP path key tidak dikenal: {$pathKey}");
        }

        $base = rtrim((string) config('sap.base_url'), '/');
        $path = '/' . ltrim((string) $paths[$pathKey], '/');
        $client = (string) config('sap.sap_client', '100');

        return $base . $path . '?sap-client=' . rawurlencode($client);
    }

    /**
     * Header standar untuk panggilan SAP (Authorization hanya jika terisi di config).
     *
     * @return array<string, string>
     */
    public static function defaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'text/plain',
            'Accept' => 'application/json',
        ];

        $auth = config('sap.authorization');
        if (filled($auth)) {
            $headers['Authorization'] = $auth;
        }

        return $headers;
    }

    /**
     * Opsi Guzzle umum: header + timeout default.
     *
     * @param  array<string, mixed>  $merge
     * @return array<string, mixed>
     */
    public static function guzzleOptions(array $merge = []): array
    {
        return array_merge([
            'headers' => self::defaultHeaders(),
            'timeout' => (int) config('sap.timeout.default', 30),
        ], $merge);
    }

    /**
     * Timeout untuk proxy Select2 (lebih pendek).
     */
    public static function proxyGuzzleOptions(array $merge = []): array
    {
        return self::guzzleOptions(array_merge([
            'timeout' => (int) config('sap.timeout.proxy', 10),
        ], $merge));
    }
}
