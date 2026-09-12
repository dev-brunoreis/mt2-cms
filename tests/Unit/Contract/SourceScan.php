<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Contract;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class SourceScan
{
    /**
     * @return list<string>
     */
    public static function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    public static function twigFiles(): array
    {
        $files = [];

        foreach (['themes/admin', 'themes/default'] as $relative) {
            $directory = BASE_DIR . '/' . $relative;

            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'twig') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    public static function read(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read ' . $path);
        }

        return $contents;
    }

    public static function methodSource(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();

        if ($filename === false) {
            return '';
        }

        $lines = file($filename);

        if ($lines === false) {
            return '';
        }

        $start = $method->getStartLine() - 1;
        $length = $method->getEndLine() - $start;

        return implode('', array_slice($lines, $start, $length));
    }

    /**
     * @return list<array{path: string, class: class-string, method: string}>
     */
    public static function postRoutes(): array
    {
        $routes = [];

        foreach ([
            BASE_DIR . '/src/Http/AdminRoutes.php',
            BASE_DIR . '/src/Http/PublicRoutes.php',
            BASE_DIR . '/src/Application.php',
        ] as $file) {
            $source = self::read($file);
            $aliases = self::useAliases($source);

            if (!preg_match_all(
                "/addRoute\(\s*'POST',\s*'([^']+)',\s*\[(\\\\?[A-Za-z0-9_\\\\]+)::class,\s*'(\w+)'\]/",
                $source,
                $matches,
                PREG_SET_ORDER,
            )) {
                continue;
            }

            foreach ($matches as $match) {
                $short = $match[2];
                $class = $aliases[$short] ?? (str_starts_with($short, '\\') ? ltrim($short, '\\') : $short);

                $routes[] = [
                    'path' => $match[1],
                    'class' => $class,
                    'method' => $match[3],
                ];
            }
        }

        return $routes;
    }

    /**
     * @param class-string $class
     */
    public static function reachableSource(string $class, string $method, int $depth = 0, array &$seen = []): string
    {
        $key = $class . '::' . $method;

        if ($depth > 8 || isset($seen[$key])) {
            return '';
        }

        $seen[$key] = true;

        if (!class_exists($class) || !method_exists($class, $method)) {
            return '';
        }

        $ref = new ReflectionMethod($class, $method);
        $source = self::methodSource($ref);
        $chunks = [$source];

        if (!preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $calls)) {
            return $source;
        }

        $owner = new ReflectionClass($class);

        foreach (array_unique($calls[1]) as $name) {
            if (!$owner->hasMethod($name)) {
                continue;
            }

            $callee = $owner->getMethod($name);
            $chunks[] = self::reachableSource($callee->getDeclaringClass()->getName(), $name, $depth + 1, $seen);
        }

        return implode("\n", $chunks);
    }

    /**
     * @return list<array{class: class-string, method: string, handlers: list<string>, spec: string}>
     */
    public static function runMassActionCalls(): array
    {
        $calls = [];

        foreach (self::phpFiles(BASE_DIR . '/src/Http/Controller') as $file) {
            $source = self::read($file);
            $class = self::classFromFile($file);

            if ($class === null || !class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);

            foreach ($ref->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $body = self::methodSource($method);

                if (!str_contains($body, '$this->runMassActions(')) {
                    continue;
                }

                $args = self::callArguments($body, '$this->runMassActions');

                if ($args === []) {
                    continue;
                }

                $calls[] = [
                    'class' => $class,
                    'method' => $method->getName(),
                    'handlers' => self::handlerKeys($args[2] ?? ''),
                    'spec' => trim($args[0] ?? ''),
                ];
            }
        }

        return $calls;
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    public static function massActionIdsForSpec(string $class, string $specExpr): array
    {
        $gridClass = self::resolveGridClass($class, $specExpr);
        $ids = self::extractMassActionIds(self::read((new ReflectionClass($gridClass))->getFileName()));

        if ($ids === []) {
            throw new \RuntimeException('No mass action ids on ' . $gridClass . ' for ' . $class);
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public static function extractMassActionIds(string $source): array
    {
        $ids = [];
        $offset = 0;

        while (($pos = strpos($source, '->massActions(', $offset)) !== false) {
            $open = strpos($source, '(', $pos);

            if ($open === false) {
                break;
            }

            $call = self::extractBalanced($source, $open);

            if (preg_match_all("/['\"]id['\"]\s*=>\s*['\"]([a-z0-9_]+)['\"]/", $call, $matches)) {
                foreach ($matches[1] as $id) {
                    $ids[] = $id;
                }
            }

            $offset = $open + 1;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param class-string $class
     * @return class-string
     */
    private static function resolveGridClass(string $class, string $expr, int $depth = 0): string
    {
        if ($depth > 4) {
            throw new \RuntimeException('Could not resolve grid class from ' . $expr);
        }

        if (preg_match('/\\\\?([A-Za-z0-9_]+Grid)::definition\s*\(/', $expr, $match)) {
            $short = $match[1];
            $fqcn = 'Mt2Cms\\Admin\\Grid\\Definitions\\' . $short;

            if (class_exists($fqcn)) {
                return $fqcn;
            }

            $aliases = self::useAliases(self::read((new ReflectionClass($class))->getFileName()));

            if (isset($aliases[$short]) && class_exists($aliases[$short])) {
                return $aliases[$short];
            }

            throw new \RuntimeException('Unknown grid definition class ' . $short . ' from ' . $expr);
        }

        if (preg_match('/\$this->(\w+)->(?:gridDefinition|adminGridDefinition)\s*\(/', $expr, $match)) {
            $ref = new ReflectionClass($class);

            if (!$ref->hasProperty($match[1])) {
                throw new \RuntimeException($class . ' has no property $' . $match[1]);
            }

            $type = $ref->getProperty($match[1])->getType();

            if (!$type instanceof ReflectionNamedType) {
                throw new \RuntimeException('Untyped property $' . $match[1] . ' on ' . $class);
            }

            return $type->getName();
        }

        if (preg_match('/\$this->(\w+)\s*\(/', $expr, $match) && class_exists($class)) {
            $ref = new ReflectionClass($class);

            if ($ref->hasMethod($match[1])) {
                return self::resolveGridClass($class, self::methodSource($ref->getMethod($match[1])), $depth + 1);
            }
        }

        throw new \RuntimeException('Could not resolve grid class from ' . $expr . ' in ' . $class);
    }

    /**
     * @return list<string>
     */
    private static function handlerKeys(string $handlersArg): array
    {
        if (!preg_match_all("/['\"]([a-z0-9_]+)['\"]\s*=>\s*(?:fn\s*\(|function\s*\()/", $handlersArg, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private static function callArguments(string $source, string $name): array
    {
        $pos = strpos($source, $name . '(');

        if ($pos === false) {
            return [];
        }

        $open = strpos($source, '(', $pos);

        if ($open === false) {
            return [];
        }

        $inside = self::extractBalanced($source, $open);
        $inside = substr($inside, 1, -1);
        $args = [];
        $depth = 0;
        $current = '';
        $length = strlen($inside);

        for ($i = 0; $i < $length; $i++) {
            $char = $inside[$i];

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $args[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $args[] = trim($current);
        }

        return $args;
    }

    private static function extractBalanced(string $source, int $open): string
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $start = $source[$open];
        $end = $pairs[$start] ?? ')';
        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            $char = $source[$i];

            if ($char === $start) {
                $depth++;
            } elseif ($char === $end) {
                $depth--;

                if ($depth === 0) {
                    return substr($source, $open, $i - $open + 1);
                }
            }
        }

        return substr($source, $open);
    }

    /**
     * @return array<string, class-string>
     */
    private static function useAliases(string $source): array
    {
        $aliases = [];

        if (!preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $source, $matches, PREG_SET_ORDER)) {
            return $aliases;
        }

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $short = $match[2] ?? (strrchr($fqcn, '\\') !== false ? substr(strrchr($fqcn, '\\'), 1) : $fqcn);
            $aliases[$short] = $fqcn;
            $aliases[$fqcn] = $fqcn;
        }

        return $aliases;
    }

    /**
     * @return class-string|null
     */
    private static function classFromFile(string $file): ?string
    {
        $source = self::read($file);
        $namespace = '';

        if (preg_match('/^namespace\s+([^;]+);/m', $source, $match)) {
            $namespace = $match[1];
        }

        if (!preg_match('/^(?:abstract\s+)?class\s+(\w+)/m', $source, $match)) {
            return null;
        }

        return $namespace . '\\' . $match[1];
    }
}
