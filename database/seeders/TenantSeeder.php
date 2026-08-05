<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            /*
             * Mot de passe du compte Super Admin.
             *
             * En production, il est fortement recommandé de définir :
             * DEMO_ADMIN_PASSWORD=...
             * dans le fichier .env
             */
            $demoPassword = env('DEMO_ADMIN_PASSWORD', 'Shalom@2026.');

            /*
             * ==========================================================
             * TENANT DE DÉMONSTRATION
             * ==========================================================
             */
            $tenant = Tenant::updateOrCreate(
                [
                    'email' => 'contact@shalom-digitalsolutions.com',
                ],
                [
                    'name' => 'Shalom Digital Solutions',
                    'phone' => '+229 01 44 95 83 83',
                    'address' => 'Abomey-Calavi, Bénin',
                    'subscription_plan' => 'business',
                    'subscription_expires_at' => now()->addYear(),
                    'is_active' => true,
                    'settings' => [
                        'language' => 'fr',
                        'currency' => 'XOF',
                        'timezone' => 'Africa/Porto-Novo',
                        'country' => 'BJ',
                    ],
                ]
            );

            /*
             * ==========================================================
             * SUPER ADMIN (plateforme, indépendant de tout tenant)
             * ==========================================================
             */
            $admin = User::updateOrCreate(
                [
                    'email' => 'admin@sdsrh.com',
                ],
                [
                    'tenant_id' => null,
                    'first_name' => 'Admin',
                    'last_name' => 'SDS',
                    'password' => Hash::make(env('DEMO_ADMIN_PASSWORD', 'Shalom@2026.')),
                    'phone' => '+229 01 69 35 17 66',
                    'status' => 'active',
                ]
            );

            /*
             * Le compte principal est maintenant SUPER ADMIN.
             *
             * syncRoles() évite de conserver accidentellement
             * admin_org ou un autre rôle. Aucune fiche Employee n'est
             * créée pour lui : il gère la plateforme, pas une organisation.
             */
            $admin->syncRoles(['super_admin']);

            /*
             * ==========================================================
             * ADMINISTRATEUR DE L'ORGANISATION DE DÉMO (admin_org)
             * ==========================================================
             * Compte séparé et indispensable pour pouvoir démontrer/tester
             * le rôle "Administrateur de l'organisation" indépendamment
             * du Super Admin.
             */
            $orgAdmin = User::updateOrCreate(
                [
                    'email' => 'admin.org@sdsrh.com',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'first_name' => 'Responsable',
                    'last_name' => 'RH',
                    'password' => Hash::make(env('DEMO_ORG_ADMIN_PASSWORD', 'OrgAdmin@2026.')),
                    'phone' => '+229 01 69 35 17 67',
                    'status' => 'active',
                ]
            );
            $orgAdmin->syncRoles(['admin_org']);

            Employee::updateOrCreate(
                [
                    'user_id' => $orgAdmin->id,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'employee_number' => 'EMP-' . str_pad($tenant->id, 5, '0', STR_PAD_LEFT) . '-0001',
                    'hire_date' => now(),
                    'status' => 'active',
                ]
            );

            /*
             * ==========================================================
             * DÉPARTEMENTS
             * ==========================================================
             */
            $departments = [
                ['name' => 'Direction Générale', 'code' => 'DG'],
                ['name' => 'Ressources Humaines', 'code' => 'RH'],
                ['name' => 'Finance', 'code' => 'FIN'],
                ['name' => 'Technique', 'code' => 'TECH'],
                ['name' => 'Commercial', 'code' => 'COM'],
                ['name' => 'Marketing', 'code' => 'MKT'],
            ];

            $createdDepartments = [];

            foreach ($departments as $dept) {
                $department = Department::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => $dept['code'],
                    ],
                    [
                        'name' => $dept['name'],
                        'is_active' => true,
                    ]
                );

                $createdDepartments[] = $department;
            }

            /*
             * ==========================================================
             * POSTES
             * ==========================================================
             */
            $positions = [
                ['title' => 'Directeur Général', 'grade' => 'A1'],
                ['title' => 'Responsable RH', 'grade' => 'B1'],
                ['title' => 'Comptable', 'grade' => 'B2'],
                ['title' => 'Développeur Full Stack', 'grade' => 'C1'],
                ['title' => 'Commercial', 'grade' => 'C2'],
                ['title' => 'Marketing Manager', 'grade' => 'B2'],
            ];

            $createdPositions = [];

            foreach ($positions as $pos) {
                $position = Position::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'title' => $pos['title'],
                    ],
                    [
                        'grade' => $pos['grade'],
                        'is_active' => true,
                    ]
                );

                $createdPositions[] = $position;
            }

            /*
             * ==========================================================
             * EMPLOYÉS DE DÉMONSTRATION
             * ==========================================================
             */
            for ($i = 2; $i <= 20; $i++) {
                $email = "employe{$i}@sdsrh.com";

                $user = User::updateOrCreate(
                    [
                        'email' => $email,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'first_name' => 'Employé',
                        'last_name' => (string) $i,
                        'password' => Hash::make(
                            env('DEMO_EMPLOYEE_PASSWORD', Str::password(24))
                        ),
                        'phone' => '+229 01 69 35 17 ' . str_pad(
                            (string) $i,
                            2,
                            '0',
                            STR_PAD_LEFT
                        ),
                        'status' => 'active',
                    ]
                );

                $user->syncRoles(['employee']);

                /*
                 * Utilisation des vrais IDs des départements et postes
                 * du tenant au lieu de supposer que les IDs sont 1 à 6.
                 */
                $department = $createdDepartments[array_rand($createdDepartments)];
                $position = $createdPositions[array_rand($createdPositions)];

                $hireDate = now()->subMonths(rand(1, 12));

                $employee = Employee::updateOrCreate(
                    [
                        'user_id' => $user->id,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'employee_number' => 'EMP-00001-' . str_pad(
                            (string) $i,
                            4,
                            '0',
                            STR_PAD_LEFT
                        ),
                        'department_id' => $department->id,
                        'position_id' => $position->id,
                        'hire_date' => $hireDate,
                        'birth_date' => now()->subYears(rand(22, 55)),
                        'gender' => ['male', 'female'][array_rand(['male', 'female'])],
                        'status' => 'active',
                    ]
                );

                /*
                 * ======================================================
                 * CONTRAT
                 * ======================================================
                 */
                Contract::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'type' => ['cdi', 'cdd'][array_rand(['cdi', 'cdd'])],
                        'status' => 'active',
                        'start_date' => $hireDate,
                        'end_date' => rand(0, 1)
                            ? $hireDate->copy()->addMonths(rand(12, 36))
                            : null,
                        'base_salary' => rand(300000, 1500000),
                        'currency' => 'XOF',
                        'benefits' => [
                            'transport' => rand(50000, 100000),
                        ],
                    ]
                );
            }

            /*
             * ==========================================================
             * INFORMATIONS CONSOLE
             * ==========================================================
             */
            $this->command->info('');
            $this->command->info('==============================================');
            $this->command->info('✅ SDS-RH - DONNÉES DE DÉMONSTRATION');
            $this->command->info('==============================================');
            $this->command->info('🏢 Organisation      : Shalom Digital Solutions');
            $this->command->info('👤 Super Admin       : admin@sdsrh.com');
            $this->command->info('🔑 Mot de passe      : ' . $demoPassword);
            $this->command->info('🛡️  Rôle              : super_admin (aucune organisation)');
            $this->command->info('----------------------------------------------');
            $this->command->info('👤 Admin Organisation : admin.org@sdsrh.com');
            $this->command->info('🔑 Mot de passe       : ' . env('DEMO_ORG_ADMIN_PASSWORD', 'OrgAdmin@2026.'));
            $this->command->info('🛡️  Rôle               : admin_org');
            $this->command->info('==============================================');
            $this->command->info('');
        });
    }
}