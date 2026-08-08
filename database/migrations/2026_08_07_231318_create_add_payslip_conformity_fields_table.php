<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            //$table->string('ifu')->nullable()->after('logo');
            $table->string('rccm')->nullable()->after('ifu');
            $table->string('fax')->nullable()->after('phone');
            $table->string('website')->nullable()->after('fax');
            $table->string('emitting_authority')->nullable()->after('name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedTinyInteger('children_count')->default(0)->after('marital_status');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->string('corps')->nullable()->after('title');
        });

        Schema::table('departments', function (Blueprint $table) {

            $table->string('hierarchy_path')->nullable()->after('name');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([ 'fax', 'website', 'emitting_authority']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('children_count');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('corps');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('hierarchy_path');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};