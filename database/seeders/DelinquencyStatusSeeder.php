<?php

namespace Database\Seeders;

use App\Models\DelinquencyStatus;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Estados de mora iniciales. Son datos (no código): se pueden editar, desactivar o ampliar desde el sistema.
 */
class DelinquencyStatusSeeder extends Seeder
{
    public function run(): void
    {
        $template = fn (string $name) => MessageTemplate::where('name', $name)->value('id');

        $statuses = [
            ['Solvente', 'SOLVENTE', 'Cliente al día con sus pagos. No recibe mensajes de cobranza.', 0, '#10b981', 1, null, false, true, true, false, true],
            ['Mora 10 días', 'MORA_10', 'Atraso leve: recordatorio amigable de pago.', 10, '#eab308', 2, 'Cobranza · Recordatorio amigable', true, false, false, false, true],
            ['Mora 30 días', 'MORA_30', 'Recordatorio de cuotas pendientes.', 30, '#f97316', 3, 'Cobranza · Cuotas pendientes', true, false, false, false, true],
            ['Mora 60 días', 'MORA_60', 'Mensaje de seguimiento y ofrecimiento de opciones de pago.', 60, '#ef4444', 4, 'Cobranza · Seguimiento', true, false, false, false, true],
            ['Mora 90 días', 'MORA_90', 'Gestión prioritaria.', 90, '#dc2626', 5, 'Cobranza · Gestión prioritaria', true, false, false, false, true],
            ['Mora crítica', 'MORA_CRITICA', 'Más de 120 días de atraso. Gestión prioritaria.', 120, '#9f1239', 6, 'Cobranza · Gestión prioritaria', true, false, false, true, true],
            ['Última gestión', 'ULTIMA_GESTION', 'Última gestión administrativa antes de acciones adicionales. Asignación manual.', 180, '#7c3aed', 7, 'Cobranza · Atención urgente', false, false, false, true, false],
            ['Recuperación', 'RECUPERACION', 'Cuenta en proceso de recuperación o negociación. Excluida de campañas masivas.', 0, '#0ea5e9', 8, null, false, true, false, false, false],
        ];

        foreach ($statuses as [$name, $code, $description, $days, $color, $priority, $templateName, $auto, $exclude, $solvent, $critical, $assignable]) {
            DelinquencyStatus::updateOrCreate(['code' => $code], [
                'name' => $name,
                'description' => $description,
                'min_days_overdue' => $days,
                'color' => $color,
                'priority' => $priority,
                'message_template_id' => $templateName ? $template($templateName) : null,
                'allows_automatic_messages' => $auto,
                'exclude_from_campaigns' => $exclude,
                'is_solvent' => $solvent,
                'is_critical' => $critical,
                'is_auto_assignable' => $assignable,
                'is_active' => true,
            ]);
        }
    }
}
