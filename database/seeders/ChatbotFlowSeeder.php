<?php

namespace Database\Seeders;

use App\Models\ChatbotFlow;
use Illuminate\Database\Seeder;

/**
 * Intenciones del chatbot basado en reglas. Las palabras clave y respuestas se administran desde el sistema.
 */
class ChatbotFlowSeeder extends Seeder
{
    public function run(): void
    {
        $flows = [
            ['agent', 'Hablar con un asesor', ['5', 'asesor', 'hablar con un asesor', 'necesito ayuda', 'quiero hablar con una persona', 'persona', 'humano', 'agente', 'ejecutivo', 'operador', 'atencion personalizada'], null, false, 100],
            ['balance', 'Consultar cuánto debo', ['1', 'cuanto debo', 'saldo', 'deuda', 'debo', 'cuanto me falta', 'estado de cuenta'], null, true, 80],
            ['installments', 'Consultar cuotas pendientes', ['3', 'cuotas', 'cuotas pendientes', 'cuantas cuotas', 'me faltan cuotas'], null, true, 75],
            ['next_payment', 'Próxima fecha de pago', ['4', 'fecha de pago', 'proximo pago', 'proxima cuota', 'cuando pago', 'cuando vence', 'vencimiento'], null, true, 74],
            ['articles', 'Consultar mis artículos', ['2', 'articulos', 'articulo', 'productos', 'mis compras', 'que compre', 'contrato', 'contratos'], null, true, 70],
            ['general_info', 'Información general', ['6', 'informacion', 'horario', 'horarios', 'direccion', 'ubicacion', 'sucursal', 'sucursales', 'telefono', 'info'],
                "📍 *{{empresa}}*\n{{direccion_empresa}}\n\n🕗 Horario de atención: {{horario}}\n📞 Teléfono: {{telefono_empresa}}\n✉️ Correo: {{correo_empresa}}\n\nEscribe *menú* para volver a las opciones.", false, 50],
            ['thanks', 'Agradecimiento', ['gracias', 'muchas gracias', 'ok gracias', 'perfecto', 'excelente'],
                '¡Con mucho gusto! 😊 Si necesitas algo más, escribe *menú*.', false, 20],
            ['menu', 'Menú principal', ['menu', 'opciones', 'inicio', 'ayuda', 'volver'], null, false, 15],
            ['logout', 'Cerrar consulta verificada', ['salir', 'cerrar sesion', 'terminar'],
                'Tu sesión de consulta fue cerrada. Para volver a consultar tu información deberás verificar tu identidad nuevamente. ¡Hasta pronto! 👋', false, 12],
            ['greeting', 'Saludo', ['hola', 'buenos dias', 'buenas tardes', 'buenas noches', 'buenas', 'saludos', 'que tal', 'hey'], null, false, 10],
        ];

        foreach ($flows as [$intent, $name, $keywords, $response, $verification, $priority]) {
            ChatbotFlow::updateOrCreate(['intent' => $intent], [
                'name' => $name,
                'keywords' => $keywords,
                'response' => $response,
                'requires_verification' => $verification,
                'priority' => $priority,
                'is_active' => true,
            ]);
        }
    }
}
