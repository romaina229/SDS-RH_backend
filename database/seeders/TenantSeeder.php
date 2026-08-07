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
                // ===== DIRECTION A1 =====
                ['title' => 'Président Directeur Général PDG', 'grade' => 'A1'],
                ['title' => 'Directeur Général', 'grade' => 'A1'],
                ['title' => 'Directeur Général Adjoint', 'grade' => 'A1'],
                ['title' => 'Directeur Administratif et Financier DAF', 'grade' => 'A1'],
                ['title' => 'Directeur des Ressources Humaines DRH', 'grade' => 'A1'],
                ['title' => 'Directeur Commercial', 'grade' => 'A1'],
                ['title' => 'Directeur Marketing', 'grade' => 'A1'],
                ['title' => 'Directeur des Opérations COO', 'grade' => 'A1'],
                ['title' => 'Directeur Technique CTO', 'grade' => 'A1'],
                ['title' => 'Directeur des Systèmes d’Information DSI', 'grade' => 'A1'],
                ['title' => 'Directeur Juridique', 'grade' => 'A1'],
                ['title' => 'Directeur Logistique', 'grade' => 'A1'],
                ['title' => 'Directeur de Projet', 'grade' => 'A1'],
                ['title' => 'Directeur Qualité', 'grade' => 'A1'],
                ['title' => 'Directeur de la Communication', 'grade' => 'A1'],

                // ===== RESSOURCES HUMAINES A2-B1 =====
                ['title' => 'Responsable RH', 'grade' => 'A2'],
                ['title' => 'Responsable GPEC', 'grade' => 'A2'],
                ['title' => 'Responsable Recrutement', 'grade' => 'A2'],
                ['title' => 'Responsable Formation', 'grade' => 'A2'],
                ['title' => 'Responsable Paie et Administration du Personnel', 'grade' => 'A2'],
                ['title' => 'Responsable QVT', 'grade' => 'A2'],
                ['title' => 'Responsable Relations Sociales', 'grade' => 'A2'],
                ['title' => 'Business Partner RH', 'grade' => 'A2'],
                ['title' => 'Chargé de Mission Diversité et Inclusion', 'grade' => 'A2'],
                ['title' => 'Chargé de Mission RSE', 'grade' => 'A2'],
                ['title' => 'Gestionnaire de Paie', 'grade' => 'A2'],
                ['title' => 'Chargé d’Administration du Personnel', 'grade' => 'B1'],
                ['title' => 'Assistant RH', 'grade' => 'B1'],
                ['title' => 'Chargé de Recrutement', 'grade' => 'B1'],
                ['title' => 'Chargé de Formation', 'grade' => 'B1'],
                ['title' => 'Gestionnaire SIRH', 'grade' => 'B1'],
                ['title' => 'Stagiaire RH', 'grade' => 'C2'],

                // ===== FINANCE & COMPTABILITE A2-C1 =====
                ['title' => 'Chef Comptable', 'grade' => 'A2'],
                ['title' => 'Comptable', 'grade' => 'B1'],
                ['title' => 'Comptable Fournisseurs', 'grade' => 'B1'],
                ['title' => 'Comptable Clients', 'grade' => 'B1'],
                ['title' => 'Comptable Immobilisations', 'grade' => 'B1'],
                ['title' => 'Auditeur Interne', 'grade' => 'A2'],
                ['title' => 'Auditeur Externe', 'grade' => 'A2'],
                ['title' => 'Contrôleur de Gestion', 'grade' => 'A2'],
                ['title' => 'Contrôleur Financier', 'grade' => 'A2'],
                ['title' => 'Trésorier', 'grade' => 'A2'],
                ['title' => 'Responsable Trésorerie', 'grade' => 'A2'],
                ['title' => 'Agent Recouvrement', 'grade' => 'B1'],
                ['title' => 'Caissier', 'grade' => 'C1'],
                ['title' => 'Assistant Comptable', 'grade' => 'C1'],

                // ===== COMMERCIAL & MARKETING A2-C1 =====
                ['title' => 'Responsable Commercial', 'grade' => 'A2'],
                ['title' => 'Responsable ADV', 'grade' => 'A2'],
                ['title' => 'Commercial Sedentaire', 'grade' => 'B1'],
                ['title' => 'Commercial Terrain', 'grade' => 'B1'],
                ['title' => 'Commercial Export', 'grade' => 'B1'],
                ['title' => 'Commercial Grands Comptes', 'grade' => 'A2'],
                ['title' => 'Télévendeur', 'grade' => 'C1'],
                ['title' => 'Agent Service Client', 'grade' => 'B1'],
                ['title' => 'Responsable SAV', 'grade' => 'A2'],
                ['title' => 'Marketing Manager', 'grade' => 'A2'],
                ['title' => 'Chargé de Communication', 'grade' => 'B1'],
                ['title' => 'Community Manager', 'grade' => 'B1'],
                ['title' => 'Chargé d’Etudes Marketing', 'grade' => 'B1'],
                ['title' => 'Responsable Événementiel', 'grade' => 'B1'],
                ['title' => 'Brand Manager', 'grade' => 'A2'],

                // ===== INFORMATIQUE & DIGITAL A2-B1 =====
                ['title' => 'Chef de Projet IT', 'grade' => 'A2'],
                ['title' => 'Chef de Projet Digital', 'grade' => 'A2'],
                ['title' => 'Développeur Full Stack', 'grade' => 'A2'],
                ['title' => 'Développeur Backend', 'grade' => 'A2'],
                ['title' => 'Développeur Frontend', 'grade' => 'A2'],
                ['title' => 'Développeur Mobile', 'grade' => 'A2'],
                ['title' => 'Développeur Laravel', 'grade' => 'A2'],
                ['title' => 'Développeur React', 'grade' => 'A2'],
                ['title' => 'Administrateur Système et Réseau', 'grade' => 'A2'],
                ['title' => 'Technicien Informatique', 'grade' => 'B1'],
                ['title' => 'Support IT', 'grade' => 'B1'],
                ['title' => 'Data Analyst', 'grade' => 'A2'],
                ['title' => 'Data Scientist', 'grade' => 'A2'],
                ['title' => 'Responsable Cybersécurité', 'grade' => 'A2'],
                ['title' => 'UX Designer', 'grade' => 'A2'],
                ['title' => 'UI Designer', 'grade' => 'B1'],

                // ===== LOGISTIQUE, ACHATS & TRANSPORT A2-C1 =====
                ['title' => 'Responsable Logistique', 'grade' => 'A2'],
                ['title' => 'Responsable Achats', 'grade' => 'A2'],
                ['title' => 'Acheteur', 'grade' => 'B1'],
                ['title' => 'Assistant Achats', 'grade' => 'B1'],
                ['title' => 'Responsable Stock', 'grade' => 'B1'],
                ['title' => 'Magasinier', 'grade' => 'C1'],
                ['title' => 'Gestionnaire de Stock', 'grade' => 'B1'],
                ['title' => 'Responsable Transport', 'grade' => 'A2'],
                ['title' => 'Chauffeur', 'grade' => 'C1'],
                ['title' => 'Chauffeur Poids Lourd', 'grade' => 'C1'],
                ['title' => 'Déclarant en Douane', 'grade' => 'B1'],
                ['title' => 'Transit', 'grade' => 'B1'],

                // ===== BTP, INDUSTRIE & PRODUCTION A2-C1 =====
                ['title' => 'Ingénieur Génie Civil', 'grade' => 'A2'],
                ['title' => 'Ingénieur BTP', 'grade' => 'A2'],
                ['title' => 'Ingénieur Industriel', 'grade' => 'A2'],
                ['title' => 'Conducteur de Travaux', 'grade' => 'A2'],
                ['title' => 'Chef de Chantier', 'grade' => 'B1'],
                ['title' => 'Conducteur d’Engins', 'grade' => 'C1'],
                ['title' => 'Électricien', 'grade' => 'B1'],
                ['title' => 'Électricien Bâtiment', 'grade' => 'B1'],
                ['title' => 'Plombier', 'grade' => 'C1'],
                ['title' => 'Menuisier', 'grade' => 'C1'],
                ['title' => 'Soudeur', 'grade' => 'C1'],
                ['title' => 'Technicien de Maintenance', 'grade' => 'B1'],
                ['title' => 'Responsable Qualité', 'grade' => 'A2'],
                ['title' => 'Agent de Production', 'grade' => 'C1'],
                ['title' => 'Chef d’Atelier', 'grade' => 'B1'],

                // ===== AGRICULTURE & AGROALIMENTAIRE A2-C1 =====
                ['title' => 'Ingénieur Agronome', 'grade' => 'A2'],
                ['title' => 'Responsable Exploitation Agricole', 'grade' => 'A2'],
                ['title' => 'Technicien Agricole', 'grade' => 'B1'],
                ['title' => 'Responsable Qualité Agro', 'grade' => 'A2'],
                ['title' => 'Ouvrier Agricole', 'grade' => 'C1'],
                ['title' => 'Responsable Usine Agro', 'grade' => 'A2'],
                ['title' => 'Technicien Agroalimentaire', 'grade' => 'B1'],

                // ===== SANTE A2-C1 =====
                ['title' => 'Médecin', 'grade' => 'A2'],
                ['title' => 'Médecin Spécialiste', 'grade' => 'A2'],
                ['title' => 'Pharmacien', 'grade' => 'A2'],
                ['title' => 'Infirmier', 'grade' => 'B1'],
                ['title' => 'Infirmier Chef', 'grade' => 'A2'],
                ['title' => 'Sage-femme', 'grade' => 'B1'],
                ['title' => 'Laborantin', 'grade' => 'B1'],
                ['title' => 'Aide-soignant', 'grade' => 'C1'],
                ['title' => 'Kinésithérapeute', 'grade' => 'B1'],
                ['title' => 'Responsable Hôpital', 'grade' => 'A1'],

                // ===== EDUCATION A2-B1 =====
                ['title' => 'Directeur d’École', 'grade' => 'A2'],
                ['title' => 'Proviseur', 'grade' => 'A2'],
                ['title' => 'Censeur', 'grade' => 'B1'],
                ['title' => 'Enseignant', 'grade' => 'B1'],
                ['title' => 'Professeur de Mathématiques', 'grade' => 'B1'],
                ['title' => 'Professeur de Français', 'grade' => 'B1'],
                ['title' => 'Surveillant Général', 'grade' => 'B1'],
                ['title' => 'Conseiller d’Orientation', 'grade' => 'B1'],
                ['title' => 'Bibliothécaire', 'grade' => 'B1'],

                // ===== SECTEUR PUBLIC & PARA-PUBLIC BENIN B1-C1 =====
                ['title' => 'Agent SONEB', 'grade' => 'B1'],
                ['title' => 'Agent SBEE', 'grade' => 'B1'],
                ['title' => 'Agent des Douanes', 'grade' => 'B1'],
                ['title' => 'Agent des Impôts', 'grade' => 'B1'],
                ['title' => 'Agent de la Mairie', 'grade' => 'B1'],
                ['title' => 'Agent de Police', 'grade' => 'B1'],
                ['title' => 'Agent de la Préfecture', 'grade' => 'B1'],
                ['title' => 'Secrétaire Administratif', 'grade' => 'B1'],
                ['title' => 'Agent de l’Etat Civil', 'grade' => 'B1'],

                // ===== ONG, BANQUE, ASSURANCE A2-B1 =====
                ['title' => 'Chargé de Projet', 'grade' => 'A2'],
                ['title' => 'Coordonnateur de Projet', 'grade' => 'A2'],
                ['title' => 'Chargé de Suivi Evaluation', 'grade' => 'B1'],
                ['title' => 'Juriste', 'grade' => 'A2'],
                ['title' => 'Conseiller Juridique', 'grade' => 'A2'],
                ['title' => 'Chargé de Clientèle Banque', 'grade' => 'B1'],
                ['title' => 'Responsable d’Agence Banque', 'grade' => 'A2'],
                ['title' => 'Agent d’Assurance', 'grade' => 'B1'],
                ['title' => 'Gestionnaire de Sinistres', 'grade' => 'B1'],

                // ===== HOTELLERIE, TOURISME & SERVICES C1-B1 =====
                ['title' => 'Directeur d’Hôtel', 'grade' => 'A2'],
                ['title' => 'Réceptionniste', 'grade' => 'B1'],
                ['title' => 'Chef Cuisinier', 'grade' => 'B1'],
                ['title' => 'Serveur', 'grade' => 'C1'],
                ['title' => 'Femme de Chambre', 'grade' => 'C1'],
                ['title' => 'Guide Touristique', 'grade' => 'B1'],
                ['title' => 'Secrétaire', 'grade' => 'B1'],
                ['title' => 'Agent Administratif', 'grade' => 'B1'],
                ['title' => 'Agent de Sécurité', 'grade' => 'C1'],
                ['title' => 'Gardien', 'grade' => 'C1'],
                ['title' => 'Agent d’Entretien', 'grade' => 'C1'],

                // ===== GENERATION DES 800 AUTRES PAR DUPLICATION =====
                // Pour arriver à 1000 on ajoute les variantes: Junior, Senior, Expert, par secteur
                ['title' => 'Commercial Junior', 'grade' => 'B1'],
                ['title' => 'Commercial Senior', 'grade' => 'A2'],
                ['title' => 'Développeur Junior', 'grade' => 'B1'],
                ['title' => 'Développeur Senior', 'grade' => 'A2'],
                ['title' => 'Comptable Junior', 'grade' => 'B1'],
                ['title' => 'Comptable Senior', 'grade' => 'A2'],
                ['title' => 'Assistant Commercial', 'grade' => 'C1'],
                ['title' => 'Assistant Marketing', 'grade' => 'C1'],
                ['title' => 'Assistant Administratif', 'grade' => 'C1'],
                ['title' => 'Stagiaire Commercial', 'grade' => 'C2'],

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
            //$this->command->info('👤 Super Admin       : admin@sdsrh.com');
            //$this->command->info('🔑 Mot de passe      : ' . $demoPassword);
            //$this->command->info('🛡️  Rôle              : super_admin (aucune organisation)');
            $this->command->info('----------------------------------------------');
            //$this->command->info('👤 Admin Organisation : admin.org@sdsrh.com');
            //$this->command->info('🔑 Mot de passe       : ' . env('DEMO_ORG_ADMIN_PASSWORD', 'OrgAdmin@2026.'));
           // $this->command->info('🛡️  Rôle               : admin_org');
            $this->command->info('==============================================');
            $this->command->info('');
        });
    }
}