<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'emitting_authority')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('emitting_authority');
            });
        }

        if (Schema::hasColumn('tenants', 'website')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('website');
            });
        }

        if (Schema::hasColumn('tenants', 'fax')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('fax');
            });
        }
    }
};