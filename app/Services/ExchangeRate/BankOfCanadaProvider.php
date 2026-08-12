<?php
declare(strict_types=1);

/**
 * Bank of Canada — official daily exchange rates, no API key required.
 *
 * Source: the Bank of Canada Valet API (https://www.bankofcanada.ca/valet).
 * Series are FXCAD<ISO>, published once per business day. Each value is
 * expressed as units of the foreign currency per 1 Canadian dollar (e.g.
 * FXCADUSD = 0.7180 means 1 CAD = 0.718 USD).
 *
 * Endpoints (current version):
 *   GET /valet/observations/FXCADAUD,FXCADUSD,.../json?recent=1
 *     → {"terms":…,"seriesDetail":{…},"observations":[
 *          {"d":"2026-08-11","FXCADUSD":{"v":"0.7180"},…}]}
 *
 * The provider is always CAD-based — even when the application's base
 * currency differs, RateSyncService cross-rates from the CAD quotes. The
 * full series-list endpoint (~3.6 MB) is intentionally NOT fetched; the
 * official FXCAD list is pinned below (it changes rarely).
 */
final class BankOfCanadaProvider implements ExchangeRateProvider
{
    private const BASE_URL = 'https://www.bankofcanada.ca/valet';

    /** Official daily FX series (ISO 4217 codes the Bank of Canada publishes). */
    private const CURRENCIES = [
        'AUD', 'BRL', 'CHF', 'CNY', 'EUR', 'GBP', 'HKD', 'IDR', 'INR',
        'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PEN', 'PLN', 'RUB',
        'SAR', 'SEK', 'SGD', 'THB', 'TRY', 'TWD', 'USD', 'VND', 'ZAR',
    ];

    private int $timeout;

    public function __construct(int $timeout = 8)
    {
        $this->timeout = max(1, $timeout);
    }

    public function identifier(): string
    {
        return 'bankofcanada';
    }

    public function name(): string
    {
        return 'Bank of Canada';
    }

    /**
     * Fetch the latest official daily rates. The Bank of Canada only
     * publishes CAD-based quotes, so $baseCurrency is ignored — rates are
     * returned with base = CAD and cross-rated by the caller if needed.
     */
    public function latestRates(string $baseCurrency): ProviderRateResponse
    {
        $series = implode(',', array_map(fn($iso) => 'FXCAD' . $iso, self::CURRENCIES));
        $url = self::BASE_URL . '/observations/' . $series . '/json?recent=1';
        return self::parseRates($this->getJson($url));
    }

    /**
     * Decode a Bank of Canada observations payload into the normalized
     * response. Exposed as a testable seam (no network needed).
     *
     * Valet's ?recent=1 returns the latest observation PER SERIES, so the
     * payload can contain ragged date buckets (a discontinued series keeps
     * its last published value, e.g. FXCADVND dated years back). We merge
     * values across every observation — observations arrive chronologically,
     * so later entries win — and report the newest published date.
     */
    public static function parseRates(array $raw): ProviderRateResponse
    {
        if (!isset($raw['observations']) || !is_array($raw['observations']) || count($raw['observations']) === 0) {
            throw new RuntimeException('Bank of Canada response contains no observations.');
        }

        $rates = [];
        $date = null;
        foreach ($raw['observations'] as $obs) {
            if (!is_array($obs)) continue;
            if (isset($obs['d']) && is_string($obs['d'])
                && ($date === null || strcmp($obs['d'], $date) > 0)) {
                $date = $obs['d'];
            }
            foreach (self::CURRENCIES as $iso) {
                $v = $obs['FXCAD' . $iso]['v'] ?? null;
                if (is_numeric($v)) {
                    $rates[$iso] = (string)$v;
                }
            }
        }
        if (!$rates) {
            throw new RuntimeException('Bank of Canada response contained no usable rates.');
        }
        return new ProviderRateResponse('CAD', $date, $rates, $raw);
    }

    public function supportedCurrencies(): array
    {
        return self::CURRENCIES;
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
                throw new RuntimeException('Bank of Canada request failed with HTTP ' . $httpCode . '.');
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
