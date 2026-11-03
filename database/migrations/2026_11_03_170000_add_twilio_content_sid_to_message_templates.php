<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada proveedor identifica sus plantillas aprobadas de forma distinta:
 *  - Meta (WhatsApp Cloud API): nombre de la plantilla + idioma (provider_template_name / provider_template_language)
 *  - Twilio: Content SID (HX...) del Content Template Builder (twilio_content_sid)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('twilio_content_sid', 64)->nullable()->after('provider_template_language');
        });

        // Los Content SID de Twilio guardados antes en el campo de Meta se mueven a su propio campo.
        DB::table('message_templates')->where('provider_template_name', 'like', 'HX%')->update([
            'twilio_content_sid' => DB::raw('provider_template_name'),
            'provider_template_name' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->whereNotNull('twilio_content_sid')->whereNull('provider_template_name')
            ->update(['provider_template_name' => DB::raw('twilio_content_sid')]);

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn('twilio_content_sid');
        });
    }
};
