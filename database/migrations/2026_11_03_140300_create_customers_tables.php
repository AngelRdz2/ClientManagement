<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('full_name', 200)->index();
            $table->string('dui', 10)->unique(); // formato 00000000-0
            $table->string('phone_primary', 20)->index();
            $table->string('phone_secondary', 20)->nullable()->index();
            // Teléfono normalizado con código de país (solo dígitos) para identificar remitentes de WhatsApp.
            $table->string('whatsapp_number', 20)->nullable()->index();
            $table->string('email', 150)->nullable()->index();
            $table->string('address', 500)->nullable();
            $table->date('registered_at')->index();
            $table->string('status', 20)->default('active')->index(); // active | inactive
            $table->foreignId('delinquency_status_id')->nullable()->constrained('delinquency_statuses')->nullOnDelete();
            $table->unsignedInteger('days_overdue')->default(0)->index();
            // Valores desnormalizados (recalculados desde artículos) para filtros y reportes eficientes.
            $table->decimal('outstanding_balance', 12, 2)->default(0)->index();
            $table->unsignedInteger('pending_installments')->default(0);
            $table->date('next_payment_date')->nullable()->index();
            // Preferencias y consentimientos de comunicación.
            $table->boolean('accepts_collection_messages')->default(true);
            $table->boolean('accepts_promotions')->default(false)->index();
            $table->timestamp('promotions_consent_at')->nullable();
            $table->string('promotions_consent_source', 50)->nullable();
            $table->timestamp('whatsapp_opted_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'delinquency_status_id']);
        });

        Schema::create('customer_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('article_code', 50)->index();
            $table->string('description', 255);
            $table->string('contract_number', 50)->index();
            $table->date('acquired_at')->nullable();
            $table->decimal('original_price', 12, 2)->default(0);
            $table->decimal('down_payment', 12, 2)->default(0);
            $table->unsignedSmallInteger('installments_count')->default(0);
            $table->decimal('installment_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('installments_paid')->default(0);
            $table->unsignedSmallInteger('installments_pending')->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('next_payment_date')->nullable()->index();
            $table->unsignedInteger('days_overdue')->default(0);
            $table->string('status', 20)->default('active')->index(); // active | paid_off | cancelled | in_collection
            $table->timestamps();

            $table->unique(['customer_id', 'contract_number', 'article_code'], 'customer_articles_contract_unique');
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_article_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('installments_covered')->default(0);
            $table->date('paid_at')->index();
            $table->string('method', 30)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('delinquency_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('delinquency_statuses')->nullOnDelete();
            $table->unsignedInteger('from_days_overdue')->nullable();
            $table->unsignedInteger('to_days_overdue')->nullable();
            $table->string('source', 20)->index(); // manual | import | automatic | system
            $table->string('reason', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description')->nullable();
            $table->string('color', 20)->default('#6366f1');
            $table->timestamps();
        });

        Schema::create('customer_customer_group', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customer_group');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('customer_status_histories');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_articles');
        Schema::dropIfExists('customers');
    }
};
