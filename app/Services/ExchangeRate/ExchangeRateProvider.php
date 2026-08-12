<?php
declare(strict_types=1);

/**
 * Exchange-rate provider abstraction.
 *
 * The application only talks to this interface — concrete providers
 * (Frankfurter, XE.com, Bank of Canada, fawazahmed0 today — others later)
 * implement it, so a provider can be swapped without touching application
 * logic.
 */
interface ExchangeRateProvider
{
    /** Stable identifier stored in the DB, e.g. 'frankfurter'. */
    public function identifier(): string;

    /** Human-readable provider name for the UI, e.g. 'Frankfurter / ECB'. */
    public function name(): string;

    /**
     * Fetch the latest reference rates against $baseCurrency.
     * Rates are expressed as units of the target currency per 1 unit of base.
     *
     * @throws RuntimeException on network/parse failure.
     */
    public function latestRates(string $baseCurrency): ProviderRateResponse;

    /** ISO codes this provider can supply rates for. */
    public function supportedCurrencies(): array;
}
