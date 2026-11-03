<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->unique(); // número WhatsApp normalizado
            $table->string('contact_name', 200)->nullable();
            $table->string('provider', 30);
            // bot | pending_agent | assigned | in_progress | closed
            $table->string('status', 20)->default('bot')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_inbound_at')->nullable(); // ventana de atención de 24 h (Meta)
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamp('agent_requested_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('is_simulated')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 20)->index(); // collection | promotion | reminder | custom
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            // En campañas de cobranza: usar la plantilla asociada a cada estado de mora.
            $table->boolean('use_status_templates')->default(false);
            $table->json('filters')->nullable();
            // draft | scheduled | processing | completed | cancelled | failed
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('direction', 10)->index(); // outbound | inbound
            $table->string('sender_type', 20)->index(); // customer | bot | agent | campaign | system
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->index();
            $table->text('body');
            $table->string('provider', 30);
            $table->string('provider_message_id', 191)->nullable()->unique();
            // Clave de idempotencia: evita enviar dos veces el mismo mensaje lógico.
            $table->string('idempotency_key', 191)->nullable()->unique();
            // pending | queued | sent | delivered | read | failed | received
            $table->string('status', 20)->default('pending')->index();
            $table->string('error_code', 50)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['direction', 'status', 'created_at']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('phone', 20);
            $table->text('rendered_body')->nullable();
            // pending | queued | sent | failed | skipped
            $table->string('status', 20)->default('pending')->index();
            $table->string('skip_reason', 255)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'customer_id']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('message'); // message | event | note
            $table->string('sender_type', 20); // customer | bot | agent | system
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            // agent_requested | taken | assigned | returned_to_bot | closed | reopened
            $table->string('action', 30)->index();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_assignments');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('conversations');
    }
};
