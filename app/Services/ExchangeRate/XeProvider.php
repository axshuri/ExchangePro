<?php
declare(strict_types=1);

/**
 * XE.com Currency Data API (XECDAPI) — live market rates. Requires a paid
 * subscription with an API key (https://www.xe.com/xecurrencydata).
 *
 * Endpoints (v1):
 *   GET /v1/convert_from.json?from=BASE&to=* → {"terms":…,"from":"USD","amount":1,
 *      "timestamp":"2026-08-11T14:30:00Z",
 *      "to":[{"quotecurrency":"EUR","mid":0.925412}, …]}
 *   GET /v1/currencies.json                  → {"terms":…,"to":[{"iso":"USD",
 *      "currency_name":"US Dollar"}, …]}
 *
 * Authentication: HTTP Basic — username = account_id, password = api_key.
 * All requests are server-side; credentials never reach the browser.
 */
final class XeProvider implements ExchangeRateProvider
{
    private const BASE_URL = 'https://xecdapi.xe.com/v1';

    private string $accountId;
    private string $apiKey;
    private int $timeout;

    public function __construct(string $accountId = '', string $apiKey = '', int $timeout = 8)
    {
        $this->accountId = trim($accountId);
        $this->apiKey = trim($apiKey);
        $this->timeout = max(1, $timeout);
    }

    public function identifier(): string
    {
        return 'xe';
    }

    public function name(): string
    {
        return 'XE.com Currency Data';
    }

    public function latestRates(string $baseCurrency): ProviderRateResponse
    {
        $this->assertConfigured();
        $url = self::BASE_URL . '/convert_from.json?from=' . urlencode(strtoupper($baseCurrency)) . '&to=*';
        return self::parseRates($baseCurrency, $this->getJson($url));
    }

    /**
     * Decode an XE convert_from payload into the normalized response.
     * Exposed as a testable seam (no network needed).
     */
    public static function parseRates(string $baseCurrency, array $raw): ProviderRateResponse
    {
        if (!isset($raw['to']) || !is_array($raw['to'])) {
            throw new RuntimeException('XE response is missing the rates payload.');
        }

        // XE timestamps are ISO 8601 (e.g. 2026-08-11T14:30:00Z) — keep the date.
        $date = isset($raw['timestamp']) && is_string($raw['timestamp'])
            ? substr($raw['timestamp'], 0, 10)
            : null;
        if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = null;
        }
        $base = isset($raw['from']) && is_string($raw['from'])
            ? $raw['from']
            : strtoupper($baseCurrency);

        $rates = [];
        foreach ($raw['to'] as $entry) {
            if (!is_array($entry)) continue;
            $code = isset($entry['quotecurrency']) ? (string)$entry['quotecurrency'] : '';
            $mid = $entry['mid'] ?? null;
            if ($code !== '' && is_numeric($mid)) {
                $rates[$code] = (string)$mid;
            }
        }
        return new ProviderRateResponse($base, $date, $rates, $raw);
    }

    public function supportedCurrencies(): array
    {
        $this->assertConfigured();
        $raw = $this->getJson(self::BASE_URL . '/currencies.json');
        $codes = [];
        if (isset($raw['to']) && is_array($raw['to'])) {
            foreach ($raw['to'] as $entry) {
                if (is_array($entry) && isset($entry['iso']) && is_string($entry['iso'])) {
                    $codes[] = $entry['iso'];
                }
            }
        }
        return $codes;
    }

    private function assertConfigured(): void
    {
        if ($this->accountId === '' || $this->apiKey === '') {
            throw new RuntimeException(
                'The XE.com provider requires an account ID and API key. '
                . 'Configure them under Automatic exchange rate updates → Provider.'
            );
        }
    }

    /**
     * GET JSON from a URL with HTTP Basic auth. cURL when available, stream
     * wrapper otherwise.
     *
     * @throws RuntimeException on transport failure.
     */
    private function getJson(string $url): array
    {
        $auth = 'Authorization: Basic ' . base64_encode($this->accountId . ':' . $this->apiKey);
        $headers = [$auth, 'Accept: application/json'];

        $body = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
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
            if ($httpCode === 401 || $httpCode === 403) {
                throw new RuntimeException('XE.com rejected the API credentials (HTTP ' . $httpCode . '). Check the account ID and API key.');
            }
            if ($httpCode >= 400) {
                throw new RuntimeException('XE.com request failed with HTTP ' . $httpCode . '.');
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
                'user_agent' => 'ExchangePro/1.0 (+rate-sync)',
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                throw new RuntimeException('Rate provider connection failed (allow_url_fopen is off or unreachable).');
            }
            // file_get_contents populates $http_response_header — surface HTTP
            // errors with the same clear messages as the cURL path above.
            if (isset($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                        $status = (int)$m[1];
                        if ($status === 401 || $status === 403) {
                            throw new RuntimeException('XE.com rejected the API credentials (HTTP ' . $status . '). Check the account ID and API key.');
                        }
                        if ($status >= 400) {
                            throw new RuntimeException('XE.com request failed with HTTP ' . $status . '.');
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
