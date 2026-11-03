<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delinquency_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->unique(); // identificador usado en importaciones de Excel
            $table->string('description')->nullable();
            $table->unsignedInteger('min_days_overdue')->default(0);
            $table->string('color', 20)->default('#64748b');
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->boolean('allows_automatic_messages')->default(false);
            $table->boolean('exclude_from_campaigns')->default(false);
            // Banderas semánticas: permiten al sistema razonar sin depender del nombre del estado.
            $table->boolean('is_solvent')->default(false)->index();
            $table->boolean('is_critical')->default(false)->index();
            // Si el proceso automático puede asignarlo según los días de mora (los estados manuales no).
            $table->boolean('is_auto_assignable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delinquency_statuses');
    }
};
