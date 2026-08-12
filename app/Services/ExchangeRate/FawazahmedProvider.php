<?php
declare(strict_types=1);

/**
 * Free open exchange rates — fawazahmed0/exchange-api, no API key required.
 *
 * Source: https://github.com/fawazahmed0/exchange-api (MIT, 200+ currencies).
 * Data is served as static JSON from public CDNs, updated daily.
 *
 * Endpoints (v1, latest):
 *   GET https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json
 *     → {"date":"2026-08-12","usd":{"aed":3.6725,"eur":0.8668,…}}
 *   GET …/v1/currencies.json  → {"aed":"Emirati Dirham",…}
 *
 * Notes:
 *   - Currency codes are LOWERCASE; the ProviderRateResponse uppercases them.
 *   - The feed also carries crypto/metals (btc, ada, 1inch…). Only 3-letter
 *     codes pass the syntactic filter (matching the pipeline's /^[A-Z]{3}$/
 *     validation); 1inch-style symbols are dropped. 3-letter crypto entries
 *     are inert downstream — apply() only stores configured currencies.
 *   - jsDelivr is the recommended primary mirror (the project's own
 *     latest.currency-api.pages.dev fallback is unreachable from some
 *     networks, so it is intentionally not used here).
 */
final class FawazahmedProvider implements ExchangeRateProvider
{
    private const BASE_URL = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1';

    private int $timeout;

    public function __construct(int $timeout = 8)
    {
        $this->timeout = max(1, $timeout);
    }

    public function identifier(): string
    {
        return 'fawazahmed';
    }

    public function name(): string
    {
        return 'Free Exchange Rates (fawazahmed0)';
    }

    public function latestRates(string $baseCurrency): ProviderRateResponse
    {
        $url = self::BASE_URL . '/currencies/' . strtolower($baseCurrency) . '.json';
        return self::parseRates($baseCurrency, $this->getJson($url));
    }

    /**
     * Decode an exchange-api payload into the normalized response.
     * Exposed as a testable seam (no network needed).
     */
    public static function parseRates(string $baseCurrency, array $raw): ProviderRateResponse
    {
        $key = strtolower($baseCurrency);
        if (!isset($raw[$key]) || !is_array($raw[$key])) {
            throw new RuntimeException('Exchange-rate API response is missing rates for ' . $baseCurrency . '.');
        }

        $date = isset($raw['date']) && is_string($raw['date']) ? $raw['date'] : null;
        $rates = [];
        foreach ($raw[$key] as $code => $rate) {
            // ISO 4217 3-letter codes only — drop crypto/metals and junk.
            if (is_string($code) && preg_match('/^[A-Za-z]{3}$/', $code) && is_numeric($rate)) {
                $rates[$code] = (string)$rate;
            }
        }
        if (!$rates) {
            throw new RuntimeException('Exchange-rate API response contained no usable rates.');
        }
        return new ProviderRateResponse($baseCurrency, $date, $rates, $raw);
    }

    public function supportedCurrencies(): array
    {
        $raw = $this->getJson(self::BASE_URL . '/currencies.json');
        $codes = [];
        if (is_array($raw)) {
            foreach (array_keys($raw) as $code) {
                if (is_string($code) && preg_match('/^[A-Za-z]{3}$/', $code)) {
                    $codes[] = strtoupper($code);
                }
            }
        }
        sort($codes);
        return $codes;
    }

    /**
     * GET JSON from a URL. cURL when available, stream wrapper otherwise.
     *
     * @throws RuntimeException on transport failure.
     */
    private function getJson(string $url): array
    {
        $body = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'ExchangePro/1.0 (+rate-sync)',
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($errno !== 0) {
                throw new RuntimeException('Rate provider connection failed: ' . $error);
            }
            if ($httpCode >= 400) {
                throw new RuntimeException('Exchange-rate API request failed with HTTP ' . $httpCode . '.');
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
                'user_agent' => 'ExchangePro/1.0 (+rate-sync)',
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                throw new RuntimeException('Rate provider connection failed (allow_url_fopen is off or unreachable).');
            }
            // file_get_contents populates $http_response_header — surface HTTP
            // errors with the same clear message as the cURL path above.
            if (isset($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                        $status = (int)$m[1];
                        if ($status >= 400) {
                            throw new RuntimeException('Exchange-rate API request failed with HTTP ' . $status . '.');
                        }
                        break;
                    }
                }
            }
        }

        if ($body === false || $body === null || trim((string)$body) === '') {
            throw new RuntimeException('Rate provider returned an empty response.');
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Rate provider returned malformed JSON.');
        }
        return $data;
    }
}
