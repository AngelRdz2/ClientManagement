<?php

namespace App\Support;

/**
 * Catálogo central de permisos y roles iniciales. Los roles y sus permisos se pueden
 * modificar desde el módulo de usuarios; este catálogo solo define el estado inicial.
 */
final class Permissions
{
    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    public const CUSTOMERS_IMPORT = 'customers.import';

    public const DELINQUENCY_MANAGE = 'delinquency.manage';

    public const CAMPAIGNS_MANAGE = 'campaigns.manage';

    public const MESSAGES_SEND = 'messages.send';

    public const CONVERSATIONS_ATTEND = 'conversations.attend';

    public const TEMPLATES_MANAGE = 'templates.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const USERS_MANAGE = 'users.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const ROLE_ADMIN = 'Administrador';

    public const ROLE_SUPERVISOR = 'Supervisor';

    public const ROLE_AGENT = 'Asesor';

    public const ROLE_COLLECTOR = 'Operador de cobranza';

    /** @return array<string, string> permiso => descripción */
    public static function all(): array
    {
        return [
            self::CUSTOMERS_VIEW => 'Consultar clientes',
            self::CUSTOMERS_MANAGE => 'Crear y editar clientes',
            self::CUSTOMERS_IMPORT => 'Importar Excel',
            self::DELINQUENCY_MANAGE => 'Administrar estados de mora',
            self::CAMPAIGNS_MANAGE => 'Crear campañas',
            self::MESSAGES_SEND => 'Enviar mensajes',
            self::CONVERSATIONS_ATTEND => 'Atender conversaciones',
            self::TEMPLATES_MANAGE => 'Administrar plantillas',
            self::REPORTS_VIEW => 'Consultar reportes',
            self::USERS_MANAGE => 'Administrar usuarios',
            self::SETTINGS_MANAGE => 'Administrar configuración',
        ];
    }

    /** @return array<string, list<string>> */
    public static function defaultRoles(): array
    {
        return [
            self::ROLE_ADMIN => array_keys(self::all()),
            self::ROLE_SUPERVISOR => [
                self::CUSTOMERS_VIEW, self::CUSTOMERS_MANAGE, self::CUSTOMERS_IMPORT, self::DELINQUENCY_MANAGE,
                self::CAMPAIGNS_MANAGE, self::MESSAGES_SEND, self::CONVERSATIONS_ATTEND, self::TEMPLATES_MANAGE,
                self::REPORTS_VIEW,
            ],
            self::ROLE_AGENT => [
                self::CUSTOMERS_VIEW, self::MESSAGES_SEND, self::CONVERSATIONS_ATTEND,
            ],
            self::ROLE_COLLECTOR => [
                self::CUSTOMERS_VIEW, self::CUSTOMERS_MANAGE, self::CUSTOMERS_IMPORT, self::CAMPAIGNS_MANAGE,
                self::MESSAGES_SEND, self::TEMPLATES_MANAGE, self::REPORTS_VIEW,
            ],
        ];
    }
}
