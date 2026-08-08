<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Tenant;

class PayslipBuilderService
{
    public function __construct(
        private SocialSecurityService $socialSecurity,
        private IncomeTaxService $incomeTax,
    ) {}


    public function build(Employee $employee, Tenant $tenant, float $baseSalary, float $bonuses = 0, float $deductions = 0): array
    {
        $config = $this->resolveConfig($tenant);
        $contributions = $this->socialSecurity->calculate($baseSalary);
        $incomeTax = $this->incomeTax->calculate($baseSalary + $bonuses);

        $context = [
            'base_salary' => $baseSalary,
            'bonuses' => $bonuses,
            'income_tax' => $incomeTax,
            'social_security_employee' => $contributions['total_employee'],
            'social_security_employer' => $contributions['total_employer'],
        ];

        $items = [];
        $totalGain = 0.0;
        $totalRetenue = 0.0;

        foreach ($config['gains'] as $line) {
            $amount = $this->resolve($line, $context, $employee);
            $items[] = [
                'code' => $line['code'],
                'label' => $line['label'],
                'gain' => $amount,
                'retenue' => null,
                'rappel' => null,
            ];
            $totalGain += $amount;
        }

        foreach ($config['retenues'] as $line) {
            $amount = $this->resolve($line, $context, $employee);
            $items[] = [
                'code' => $line['code'],
                'label' => $line['label'],
                'gain' => null,
                'retenue' => $amount,
                'rappel' => null,
            ];
            $totalRetenue += $amount;
        }

        foreach ($config['patronal'] as $line) {
            $amount = $this->resolve($line, $context, $employee);
            $items[] = [
                'code' => $line['code'],
                'label' => $line['label'],
                'gain' => null,
                'retenue' => $amount,
                'rappel' => null,
                'patronal' => true,
            ];

        }

        $net = $totalGain - $totalRetenue - $deductions;

        return [
            'items' => $items,
            'total_gain' => round($totalGain, 0),
            'total_retenue' => round($totalRetenue, 0),
            'net' => round($net, 0),
        ];
    }


    private function resolveConfig(Tenant $tenant): array
    {
        $custom = $tenant->settings['payslip_items'] ?? null;

        if (is_array($custom) && isset($custom['gains'], $custom['retenues'], $custom['patronal'])) {
            return $custom;
        }

        return config('payslip_items');
    }

    private function resolve(array $line, array $context, Employee $employee): float
    {
        if (isset($line['source'])) {
            return round((float) ($context[$line['source']] ?? 0), 0);
        }
        if (isset($line['fixed'])) {
            return round((float) $line['fixed'], 0);
        }
        if (isset($line['rate_of'])) {
            return round((float) ($context[$line['rate_of']] ?? 0) * (float) $line['rate'], 0);
        }
        if (isset($line['per_child'])) {
            return round(((int) ($employee->children_count ?? 0)) * (float) $line['per_child'], 0);
        }
        return 0.0;
    }
}