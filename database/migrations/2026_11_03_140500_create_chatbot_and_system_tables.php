<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_flows', function (Blueprint $table) {
            $table->id();
            $table->string('intent', 50)->unique(); // balance | articles | installments | next_payment | agent | general_info | greeting | menu | opt_out
            $table->string('name', 120);
            $table->json('keywords'); // frases/palabras que activan la intención
            $table->text('response')->nullable(); // texto de respuesta (admite variables)
            $table->boolean('requires_verification')->default(false);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // cliente verificado
            // idle | awaiting_dui | awaiting_second_factor | locked
            $table->string('state', 30)->default('idle');
            $table->string('pending_intent', 50)->nullable();
            $table->json('context')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->string('method', 30); // phone_match | otp | contract_number
            $table->string('code_hash')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | verified | failed | expired
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string | text | bool | int | json | secret
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            // uploaded | validated | queued | processing | completed | failed
            $table->string('status', 20)->default('uploaded')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('customers_created')->default(0);
            $table->unsignedInteger('customers_updated')->default(0);
            $table->unsignedInteger('articles_upserted')->default(0);
            $table->unsignedInteger('statuses_changed')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->string('failure_message', 1000)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('errors');
            $table->json('row_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->nullableMorphs('subject');
            $table->string('description', 500);
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('customer_verifications');
        Schema::dropIfExists('chatbot_sessions');
        Schema::dropIfExists('chatbot_flows');
    }
};
