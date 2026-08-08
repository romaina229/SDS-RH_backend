<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * TENANTS
         */
        if (!Schema::hasColumn('tenants', 'fax')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('fax')->nullable();
            });
        }

        if (!Schema::hasColumn('tenants', 'website')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('website')->nullable();
            });
        }

        if (!Schema::hasColumn('tenants', 'emitting_authority')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('emitting_authority')->nullable();
            });
        }

        /*
         * EMPLOYEES
         */
        if (!Schema::hasColumn('employees', 'children_count')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedTinyInteger('children_count')->default(0);
            });
        }

        /*
         * POSITIONS
         */
        if (!Schema::hasColumn('positions', 'corps')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->string('corps')->nullable();
            });
        }

        /*
         * DEPARTMENTS
         */
        if (!Schema::hasColumn('departments', 'hierarchy_path')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->string('hierarchy_path')->nullable();
            });
        }

        /*
         * PAYROLLS
         */
        if (!Schema::hasColumn('payrolls', 'qr_token')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->string('qr_token', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        /*
         * TENANTS
         */
        if (Schema::hasColumn('tenants', 'fax')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('fax');
            });
        }

        if (Schema::hasColumn('tenants', 'website')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('website');
            });
        }

        if (Schema::hasColumn('tenants', 'emitting_authority')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('emitting_authority');
            });
        }

        /*
         * EMPLOYEES
         */
        if (Schema::hasColumn('employees', 'children_count')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('children_count');
            });
        }

        /*
         * POSITIONS
         */
        if (Schema::hasColumn('positions', 'corps')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropColumn('corps');
            });
        }

        /*
         * DEPARTMENTS
         */
        if (Schema::hasColumn('departments', 'hierarchy_path')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('hierarchy_path');
            });
        }

        /*
         * PAYROLLS
         */
        if (Schema::hasColumn('payrolls', 'qr_token')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('qr_token');
            });
        }
    }
};