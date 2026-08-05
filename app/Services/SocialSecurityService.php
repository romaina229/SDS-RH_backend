<?php

namespace App\Services;

class SocialSecurityService
{
    public function calculate(float $grossSalary, ?float $occupationalRiskRate = null): array
    {
        $config = config('social_security.benin');

        $riskRate = $occupationalRiskRate ?? $config['occupational_risk']['default_employer_rate'];
        $riskRate = max(
            $config['occupational_risk']['min_rate'],
            min($riskRate, $config['occupational_risk']['max_rate'])
        );

        $employeePension = round($grossSalary * $config['pension']['employee_rate'], 0);
        $employerPension = round($grossSalary * $config['pension']['employer_rate'], 0);
        $employerFamily = round($grossSalary * $config['family_allowance']['employer_rate'], 0);
        $employerRisk = round($grossSalary * $riskRate, 0);

        $totalEmployee = $employeePension;
        $totalEmployer = $employerPension + $employerFamily + $employerRisk;

        return [
            'gross_salary' => $grossSalary,
            'employee_contributions' => [
                'pension' => [
                    'rate' => $config['pension']['employee_rate'] * 100,
                    'amount' => $employeePension,
                ],
            ],
            'employer_contributions' => [
                'pension' => [
                    'rate' => $config['pension']['employer_rate'] * 100,
                    'amount' => $employerPension,
                ],
                'family_allowance' => [
                    'rate' => $config['family_allowance']['employer_rate'] * 100,
                    'amount' => $employerFamily,
                ],
                'occupational_risk' => [
                    'rate' => $riskRate * 100,
                    'amount' => $employerRisk,
                ],
            ],
            'total_employee' => $totalEmployee,
            'total_employer' => $totalEmployer,
            'net_before_income_tax' => $grossSalary - $totalEmployee,
            'total_cost' => $grossSalary + $totalEmployer,
        ];
    }

    public function getPayslip($employee, string $month): array
    {
        $contract = $employee->contracts()->where('status', 'active')->first();

        if (! $contract) {
            throw new \RuntimeException('Aucun contrat actif pour cet employé.');
        }

        $grossSalary = (float) $contract->base_salary;
        $contributions = $this->calculate($grossSalary);

        return [
            'employee' => $employee,
            'month' => $month,
            'gross_salary' => $grossSalary,
            'contributions' => $contributions,
            'net_salary_before_income_tax' => $contributions['net_before_income_tax'],
            'employer_cost' => $contributions['total_cost'],
        ];
    }
}
