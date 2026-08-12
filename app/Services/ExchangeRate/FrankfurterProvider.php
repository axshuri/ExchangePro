<?php
declare(strict_types=1);

/**
 * Frankfurter (api.frankfurter.dev) — ECB reference rates, no API key required.
 *
 * Endpoints (current version):
 *   GET /v1/latest?base=EUR   → {"amount":1,"base":"EUR","date":"2026-08-11","rates":{...}}
 *   GET /v1/currencies        → {"USD":"US Dollar",...}
 *
 * All requests are server-side; no credentials ever reach the browser.
 */
final class FrankfurterProvider implements ExchangeRateProvider
{
    private const BASE_URL = 'https://api.frankfurter.dev/v1';

    private int $timeout;

    public function __construct(int $timeout = 8)
    {
        $this->timeout = max(1, $timeout);
    }

    public function identifier(): string
    {
        return 'frankfurter';
    }

    public function name(): string
    {
        return 'Frankfurter / ECB';
    }

    public function latestRates(string $baseCurrency): ProviderRateResponse
    {
        $url = self::BASE_URL . '/latest?base=' . urlencode(strtoupper($baseCurrency));
        $raw = $this->getJson($url);

        if (!is_array($raw) || !isset($raw['rates']) || !is_array($raw['rates'])) {
            throw new RuntimeException('Frankfurter response is missing the rates payload.');
        }
        $date = isset($raw['date']) && is_string($raw['date']) ? $raw['date'] : null;
        $base = isset($raw['base']) && is_string($raw['base']) ? $raw['base'] : strtoupper($baseCurrency);

        $rates = [];
        foreach ($raw['rates'] as $code => $rate) {
            if (is_string($code) && is_numeric($rate)) {
                $rates[$code] = (string)$rate;
            }
        }
        return new ProviderRateResponse($base, $date, $rates, $raw);
    }

    public function supportedCurrencies(): array
    {
        $raw = $this->getJson(self::BASE_URL . '/currencies');
        return is_array($raw) ? array_keys($raw) : [];
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
                CURLOPT_USERAGENT => 'ExchangePro/1.0 (+rate-sync)',
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno !== 0) {
                throw new RuntimeException('Rate provider connection failed: ' . $error);
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'user_agent' => 'ExchangePro/1.0 (+rate-sync)',
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                throw new RuntimeException('Rate provider connection failed (allow_url_fopen is off or unreachable).');
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
