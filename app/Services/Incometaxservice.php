<?php

namespace App\Services;

class IncomeTaxService
{
    /**
     * Calcule l'ITS mensuel par tranches progressives.
     *
     * ATTENTION : barème fourni à titre de gabarit technique (voir
     * config/income_tax.php). À faire valider par un expert-comptable
     * avant utilisation en production.
     */
    public function calculate(float $taxableIncome, string $country = 'benin'): float
    {
        $brackets = config("income_tax.{$country}.brackets", []);

        $tax = 0.0;
        $previousThreshold = 0.0;

        foreach ($brackets as $bracket) {
            $upTo = $bracket['up_to'];
            $rate = $bracket['rate'];

            $bracketCeiling = $upTo === null ? $taxableIncome : min($taxableIncome, $upTo);
            $taxableInBracket = max(0, $bracketCeiling - $previousThreshold);

            $tax += $taxableInBracket * $rate;
            $previousThreshold = $upTo === null ? $taxableIncome : $upTo;

            if ($upTo !== null && $taxableIncome <= $upTo) {
                break;
            }
        }

        return round($tax, 0);
    }
}