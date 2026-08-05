<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * ==========================================================
         * 1. RÔLES ET PERMISSIONS
         * ==========================================================
         *
         * Ces données doivent être créées avant les utilisateurs,
         * car TenantSeeder attribue les rôles aux comptes.
         */
        $this->call([
            RolePermissionSeeder::class,

            /*
             * ======================================================
             * 2. DONNÉES DE DÉMONSTRATION
             * ======================================================
             *
             * Crée :
             * - l'organisation de démonstration
             * - le Super Admin
             * - les départements
             * - les postes
             * - les employés
             * - les contrats
             */
            TenantSeeder::class,
        ]);
    }
}
