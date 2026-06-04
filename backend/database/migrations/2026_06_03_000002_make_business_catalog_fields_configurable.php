<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('treatment_type')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('treatment_type', [
                'consulta_general',
                'brackets',
                'brackets_metalicos',
                'brackets_esteticos',
                'ortodoncia',
                'ortodoncia_invisible',
                'ortodoncia_infantil',
            ])->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'other'])->change();
        });
    }
};
