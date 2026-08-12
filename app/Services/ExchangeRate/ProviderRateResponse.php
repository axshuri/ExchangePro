<?php
declare(strict_types=1);

/**
 * Normalized response from an exchange-rate provider.
 * Rates are [ISO_CODE => rate string], expressed as units of the target
 * currency per 1 unit of $base (e.g. base=EUR, rates['USD'] = 1.154).
 */
final class ProviderRateResponse
{
    /** Provider base currency (ISO code). */
    public string $base;

    /** Provider-reported date (Y-m-d) — the provider's own timestamp. */
    public ?string $date;

    /** @var array<string,string> currency code => rate. */
    public array $rates;

    /** Raw decoded payload (kept for diagnostics). */
    public array $raw;

    public function __construct(string $base, ?string $date, array $rates, array $raw = [])
    {
        $this->base = strtoupper($base);
        $this->date = $date;
        $this->rates = [];
        foreach ($rates as $code => $rate) {
            $this->rates[strtoupper((string)$code)] = (string)$rate;
        }
        $this->raw = $raw;
    }
}
