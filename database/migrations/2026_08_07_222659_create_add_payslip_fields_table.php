// database/migrations/2026_08_07_000001_add_payslip_fields.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedTinyInteger('children_count')->default(0)->after('marital_status');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->string('corps')->nullable()->after('title'); 
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('hierarchy_path')->nullable()->after('name'); 
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('ifu')->nullable()->after('logo');
            $table->string('rccm')->nullable()->after('ifu');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('children_count'));
        Schema::table('positions', fn (Blueprint $t) => $t->dropColumn('corps'));
        Schema::table('departments', fn (Blueprint $t) => $t->dropColumn('hierarchy_path'));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['ifu', 'rccm']));
        Schema::table('payrolls', fn (Blueprint $t) => $t->dropColumn('qr_token'));
    }
};