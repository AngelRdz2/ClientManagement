<?php

namespace App\Services\Import;

/**
 * Estructura documentada del archivo de importación de clientes (una fila por artículo/contrato).
 * Un cliente con varios artículos se repite en varias filas con el mismo código y DUI.
 */
final class ImportColumns
{
    /** columna => [descripción, obligatoria, ejemplo] */
    public const CUSTOMER = [
        'codigo_cliente' => ['Código único del cliente. Identifica al cliente para crear o actualizar.', true, 'CLI-001001'],
        'nombre_completo' => ['Nombres y apellidos.', true, 'Ana Beatriz Rivas Pérez'],
        'dui' => ['DUI con 9 dígitos (00000000-0). Se valida el dígito verificador.', true, '04567891-1'],
        'telefono_principal' => ['Teléfono con WhatsApp. 8 dígitos o con código de país.', true, '7123-4567'],
        'telefono_secundario' => ['Teléfono alterno.', false, '2222-3333'],
        'correo' => ['Correo electrónico.', false, 'ana.rivas@correo.com'],
        'direccion' => ['Dirección completa.', false, 'Col. Escalón, San Salvador'],
        'fecha_registro' => ['Fecha de registro (dd/mm/aaaa). Si se omite: fecha de importación.', false, '15/03/2025'],
        'estado_cliente' => ['activo | inactivo. Por defecto: activo.', false, 'activo'],
        'estado_mora' => ['Código o nombre del estado de mora (ver hoja "Estados de mora").', false, 'MORA_30'],
        'dias_mora' => ['Días de atraso. Si no se indica estado_mora, el estado se calcula con estos días.', false, '35'],
        'acepta_promociones' => ['si | no. Consentimiento para recibir promociones.', false, 'si'],
        'observaciones' => ['Notas internas.', false, ''],
    ];

    public const ARTICLE = [
        'codigo_articulo' => ['Código del artículo. Obligatorio si la fila incluye un artículo.', false, 'TV-SAM55'],
        'descripcion_articulo' => ['Descripción del artículo.', false, 'Televisor Samsung 55"'],
        'numero_contrato' => ['Número de contrato. Junto al código de artículo identifica el artículo para actualizarlo.', false, 'CT-2025-000123'],
        'fecha_adquisicion' => ['Fecha de adquisición (dd/mm/aaaa).', false, '20/03/2025'],
        'precio_original' => ['Precio de venta.', false, '650.00'],
        'prima' => ['Prima pagada.', false, '50.00'],
        'cantidad_cuotas' => ['Número total de cuotas.', false, '12'],
        'valor_cuota' => ['Valor de cada cuota.', false, '58.50'],
        'cuotas_pagadas' => ['Cuotas pagadas a la fecha.', false, '6'],
        'saldo_pendiente' => ['Saldo pendiente. Si se omite: cuotas pendientes × valor de cuota.', false, '351.00'],
        'proxima_fecha_pago' => ['Próxima fecha de pago (dd/mm/aaaa).', false, '20/10/2025'],
        'estado_articulo' => ['vigente | cancelado | anulado | en_cobranza. Por defecto: vigente.', false, 'vigente'],
    ];

    /** @return array<string, array{0:string,1:bool,2:string}> */
    public static function all(): array
    {
        return self::CUSTOMER + self::ARTICLE;
    }

    /** @return list<string> */
    public static function headings(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> */
    public static function required(): array
    {
        return array_keys(array_filter(self::all(), fn ($c) => $c[1]));
    }
}
