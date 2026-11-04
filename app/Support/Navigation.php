<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Definición del menú lateral. Cada elemento se muestra solo si el usuario tiene el permiso requerido.
 */
final class Navigation
{
    /** @return list<array{label: string, items: list<array<string, mixed>>}> */
    public static function for(User $user): array
    {
        $groups = [
            ['label' => 'Principal', 'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'layout-dashboard'],
                ['label' => 'Clientes', 'route' => 'customers.index', 'active' => ['customers.index', 'customers.show', 'customers.create', 'customers.edit'], 'icon' => 'users', 'can' => Permissions::CUSTOMERS_VIEW],
                ['label' => 'Tablero de clientes', 'route' => 'customers.board', 'active' => 'customers.board', 'icon' => 'square-kanban', 'can' => Permissions::CUSTOMERS_VIEW],
                ['label' => 'Conversaciones', 'route' => 'conversations.index', 'active' => 'conversations.*', 'icon' => 'messages-square', 'can' => Permissions::CONVERSATIONS_ATTEND, 'badge' => 'pending_agent'],
            ]],
            ['label' => 'Cobranza', 'items' => [
                ['label' => 'Estados de mora', 'route' => 'delinquency.index', 'active' => 'delinquency.index', 'icon' => 'gauge', 'can' => Permissions::DELINQUENCY_MANAGE],
                ['label' => 'Actualizar estados', 'route' => 'delinquency.updates', 'active' => ['delinquency.updates', 'delinquency.history'], 'icon' => 'refresh-cw', 'can' => Permissions::CUSTOMERS_MANAGE],
                ['label' => 'Importar Excel', 'route' => 'customers.import', 'active' => 'customers.import*', 'icon' => 'file-spreadsheet', 'can' => Permissions::CUSTOMERS_IMPORT],
                ['label' => 'Campañas de cobranza', 'route' => 'campaigns.index', 'active' => 'campaigns.*', 'icon' => 'hand-coins', 'can' => Permissions::CAMPAIGNS_MANAGE],
            ]],
            ['label' => 'Marketing', 'items' => [
                ['label' => 'Promociones', 'route' => 'promotions.index', 'active' => 'promotions.*', 'icon' => 'megaphone', 'can' => Permissions::CAMPAIGNS_MANAGE],
                ['label' => 'Plantillas', 'route' => 'templates.index', 'active' => 'templates.*', 'icon' => 'file-text', 'can' => Permissions::TEMPLATES_MANAGE],
                ['label' => 'Grupos de clientes', 'route' => 'groups.index', 'active' => 'groups.*', 'icon' => 'layers', 'can' => Permissions::CUSTOMERS_MANAGE],
            ]],
            ['label' => 'Monitoreo', 'items' => [
                ['label' => 'Historial de mensajes', 'route' => 'messages.index', 'active' => 'messages.*', 'icon' => 'send', 'can' => Permissions::MESSAGES_SEND],
                ['label' => 'Reportes', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'chart-column', 'can' => Permissions::REPORTS_VIEW],
            ]],
            ['label' => 'Administración', 'items' => [
                ['label' => 'Simulador WhatsApp', 'route' => 'simulator', 'active' => 'simulator', 'icon' => 'flask-conical', 'can' => Permissions::CONVERSATIONS_ATTEND],
                ['label' => 'Chatbot', 'route' => 'chatbot.index', 'active' => 'chatbot.*', 'icon' => 'bot', 'can' => Permissions::SETTINGS_MANAGE],
                ['label' => 'Usuarios y roles', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'user-cog', 'can' => Permissions::USERS_MANAGE],
                ['label' => 'Configuración', 'route' => 'settings.index', 'active' => 'settings.*', 'icon' => 'settings', 'can' => Permissions::SETTINGS_MANAGE],
            ]],
        ];

        return collect($groups)
            ->map(function (array $group) use ($user) {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    fn (array $item) => Route::has($item['route']) && (! isset($item['can']) || $user->can($item['can']))
                ));

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }
}
