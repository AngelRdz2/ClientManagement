<?php

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * Iconos Lucide servidos desde un archivo PHP precompilado (bootstrap/cache/lucide-icons.php).
 *
 * Motivo: los componentes <x-lucide-*> leen el SVG del disco en cada petición y <x-dynamic-component>
 * recompila su plantilla en cada uso (~1 ms por ícono). Con el arreglo precompilado, OPcache mantiene los
 * SVG en memoria y renderizar un ícono es solo concatenar texto.
 *
 * Generar o actualizar el archivo: php artisan icons:lucide
 */
final class Icons
{
    public const SOURCE = 'vendor/mallardduck/blade-lucide-icons/resources/svg/icons';

    public const CACHE = 'bootstrap/cache/lucide-icons.php';

    /** @var array<string, array{0: string, 1: string}>|null nombre => [atributos raíz, contenido interno] */
    private static ?array $icons = null;

    public static function svg(string $name, ComponentAttributeBag|string $attributes = ''): string
    {
        self::$icons ??= is_file($cache = base_path(self::CACHE)) ? require $cache : [];

        $icon = self::$icons[$name] ??= self::load($name);
        if ($icon === null) {
            return '';
        }

        $extra = trim((string) $attributes);

        return '<svg '.$icon[0].' aria-hidden="true"'.($extra !== '' ? ' '.$extra : '').'>'.$icon[1].'</svg>';
    }

    /** @return array{0: string, 1: string}|null */
    public static function load(string $name): ?array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return null;
        }

        $file = base_path(self::SOURCE.'/'.$name.'.svg');
        if (! is_file($file)) {
            report(new \InvalidArgumentException("Ícono Lucide no encontrado: {$name}"));

            return null;
        }

        return self::parse((string) file_get_contents($file));
    }

    /** @return array{0: string, 1: string}|null */
    public static function parse(string $svg): ?array
    {
        if (! preg_match('/<svg\s+([^>]*)>(.*)<\/svg>/s', $svg, $m)) {
            return null;
        }

        return [trim(preg_replace('/\s+/', ' ', $m[1])), trim(preg_replace('/>\s+</', '><', $m[2]))];
    }

    /**
     * Genera el archivo precompilado solo con los iconos que usa la aplicación (cualquier palabra entre comillas en
     * app/ y resources/views que coincida con un ícono de Lucide). Un archivo pequeño se carga casi sin costo;
     * los iconos no incluidos se leen del disco como respaldo. Devuelve la cantidad de iconos.
     */
    public static function build(): int
    {
        $available = array_flip(array_map(fn ($f) => basename($f, '.svg'), glob(base_path(self::SOURCE.'/*.svg'))));
        $used = [];

        foreach (['app', 'resources/views', 'database/seeders', 'config'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                preg_match_all('/["\']([a-z0-9]+(?:-[a-z0-9]+)*)["\']/', (string) file_get_contents($file->getPathname()), $m);
                foreach ($m[1] as $name) {
                    if (isset($available[$name])) {
                        $used[$name] = true;
                    }
                }
            }
        }

        $icons = [];
        foreach (array_keys($used) as $name) {
            if ($parsed = self::parse((string) file_get_contents(base_path(self::SOURCE.'/'.$name.'.svg')))) {
                $icons[$name] = $parsed;
            }
        }
        ksort($icons);

        $target = base_path(self::CACHE);
        $tmp = $target.'.'.uniqid().'.tmp';
        file_put_contents($tmp, '<?php return '.var_export($icons, true).';'.PHP_EOL);
        rename($tmp, $target);
        self::$icons = null;

        return count($icons);
    }
}
