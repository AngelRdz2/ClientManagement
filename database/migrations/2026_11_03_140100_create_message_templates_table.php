<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 30)->index(); // collection | promotion | reminder | customer_service | custom
            $table->string('description')->nullable();
            $table->text('body');
            // Plantilla aprobada por Meta (WhatsApp Cloud API) para envíos fuera de la ventana de 24 h.
            $table->string('provider_template_name', 150)->nullable();
            $table->string('provider_template_language', 10)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
