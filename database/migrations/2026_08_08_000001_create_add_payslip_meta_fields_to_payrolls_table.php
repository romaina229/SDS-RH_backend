<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les informations qui apparaissent sur le modèle officiel de
 * bulletin de paie (bloc identité à droite) : jours travaillés sur la
 * période, taux horaire indicatif, mode de paiement, et la date effective
 * de paiement affichée dans le statut ("Payé le ...").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'worked_days')) {
                $table->unsignedTinyInteger('worked_days')->nullable()->after('month');
            }
            if (! Schema::hasColumn('payrolls', 'hourly_rate')) {
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('worked_days');
            }
            if (! Schema::hasColumn('payrolls', 'payment_method')) {
                $table->string('payment_method')->nullable()->default('Virement bancaire')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('payrolls', 'worked_days') ? 'worked_days' : null,
                Schema::hasColumn('payrolls', 'hourly_rate') ? 'hourly_rate' : null,
                Schema::hasColumn('payrolls', 'payment_method') ? 'payment_method' : null,
            ]));
        });
    }
};