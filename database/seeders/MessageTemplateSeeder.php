<?php

namespace Database\Seeders;

use App\Enums\TemplateCategory;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Cobranza (una por estado de mora)
            ['Cobranza · Recordatorio amigable', TemplateCategory::Collection, 'Para clientes con 10 días de mora',
                "Hola {{primer_nombre}}, esperamos que se encuentre bien. 😊\n\nLe recordamos amablemente que su cuota de \${{valor_cuota}} venció el {{fecha_pago}}. Actualmente su saldo pendiente es de \${{saldo_pendiente}}.\n\nSi ya realizó su pago, por favor ignore este mensaje. ¡Gracias por su preferencia!\n\n{{empresa}}"],
            ['Cobranza · Cuotas pendientes', TemplateCategory::Collection, 'Para clientes con 30 días de mora',
                "Estimado(a) {{nombre}}:\n\nLe informamos que su cuenta {{codigo_cliente}} presenta {{cuotas_pendientes}} cuota(s) pendiente(s) y {{dias_mora}} días de atraso. Saldo pendiente: \${{saldo_pendiente}}.\n\nLe invitamos a ponerse al día para evitar recargos. Puede pagar en cualquiera de nuestras sucursales o comunicarse al {{telefono_empresa}}.\n\n{{empresa}}"],
            ['Cobranza · Seguimiento', TemplateCategory::Collection, 'Para clientes con 60 días de mora',
                "{{nombre}}, le saludamos de {{empresa}}.\n\nSu crédito registra {{dias_mora}} días de atraso con un saldo de \${{saldo_pendiente}}. Queremos ayudarle a encontrar una solución: responda este mensaje o escriba *asesor* para conversar con nuestro equipo sobre opciones de pago.\n\nAtentamente, Departamento de Cobros."],
            ['Cobranza · Gestión prioritaria', TemplateCategory::Collection, 'Para mora de 90 días y mora crítica',
                "{{nombre}}, su cuenta {{codigo_cliente}} se encuentra en *gestión prioritaria* por {{dias_mora}} días de atraso (saldo: \${{saldo_pendiente}}).\n\nEs importante que se comunique con nosotros a la brevedad para evitar que su caso sea trasladado a otras instancias. Escriba *asesor* para atención inmediata.\n\n{{empresa}} · {{telefono_empresa}}"],
            ['Cobranza · Atención urgente', TemplateCategory::Collection, 'Para clientes en última gestión',
                "AVISO IMPORTANTE para {{nombre}}:\n\nSu cuenta {{codigo_cliente}} se encuentra en *última gestión administrativa* con un saldo de \${{saldo_pendiente}}. Requerimos su atención urgente para regularizar su situación.\n\nComuníquese hoy mismo al {{telefono_empresa}} o escriba *asesor* por este medio.\n\n{{empresa}}"],

            // Recordatorios
            ['Recordatorio · Próxima cuota', TemplateCategory::Reminder, 'Aviso preventivo antes del vencimiento',
                "Hola {{primer_nombre}} 👋, le recordamos que su próxima cuota de \${{valor_cuota}} vence el {{fecha_pago}}.\n\nPague a tiempo y mantenga su historial impecable. ¡Gracias por confiar en {{empresa}}!"],

            // Promociones
            ['Promo · $0 de prima', TemplateCategory::Promotion, 'Venta sin prima para clientes actuales',
                "¡{{primer_nombre}}, tenemos algo especial para usted! 🎉\n\nComo cliente de {{empresa}} puede llevarse su próximo artículo con *$0 de prima*. Válido en refrigeradoras, televisores, celulares y más.\n\nVisítenos o responda este mensaje para más información.\n\nSi no desea recibir promociones, responda BAJA."],
            ['Promo · Paga después de 3 meses', TemplateCategory::Promotion, 'Comienza a pagar después de 3 meses',
                "{{primer_nombre}}, ¡estrene hoy y *comience a pagar dentro de 3 meses*! 🛋️📺\n\nPromoción exclusiva para clientes con buen historial en {{empresa}}. Cupos limitados.\n\nResponda *INFO* y un asesor le atenderá.\n\nSi no desea recibir promociones, responda BAJA."],
            ['Promo · Ofertas de temporada', TemplateCategory::Promotion, 'Campañas de temporada',
                "¡Llegó la temporada de ofertas en {{empresa}}! 🛍️\n\n{{primer_nombre}}, aproveche descuentos de hasta 30% en línea blanca y electrónica, con cuotas accesibles.\n\nVisítenos en nuestras sucursales.\n\nSi no desea recibir promociones, responda BAJA."],

            // Atención al cliente
            ['Atención · Seguimiento de consulta', TemplateCategory::CustomerService, 'Seguimiento después de una atención',
                'Hola {{primer_nombre}}, gracias por comunicarse con {{empresa}}. ¿Pudimos resolver su consulta? Si necesita algo más, estamos para servirle.'],
            ['Personalizada · Saldo detallado por artículo', TemplateCategory::Custom, 'Incluye el detalle de todos los artículos',
                "{{nombre}}, este es el detalle de su cuenta:\n\n{{lista_articulos}}\n\nSaldo total pendiente: \${{saldo_pendiente}}.\n\n{{empresa}}"],
        ];

        foreach ($templates as [$name, $category, $description, $body]) {
            MessageTemplate::updateOrCreate(['name' => $name], [
                'category' => $category,
                'description' => $description,
                'body' => $body,
                'is_active' => true,
            ]);
        }
    }
}
