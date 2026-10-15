<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Support\Paths;
use InvalidArgumentException;

/**
 * Validated package configuration with safe defaults.
 */
final class BlueprintConfig
{
    /** Always excluded, regardless of configuration. */
    public const ALWAYS_EXCLUDED = ['vendor', 'node_modules', '.git'];

    /** Never read. User configuration can add patterns but not remove these. */
    public const BUILTIN_SENSITIVE = [
        '.env', '.env.*', '*.env', '.envrc',
        '*.pem', '*.key', '*.p12', '*.pfx', '*.crt', '*.jks', '*.keystore',
        'id_rsa', 'id_rsa*', 'id_dsa*', 'id_ecdsa*', 'id_ed25519*',
        'auth.json', '.npmrc', '.pypirc', '.netrc', '.htpasswd',
        'credentials.json', 'credentials.*', '*credentials*.json', 'service-account*.json',
        '*.sqlite', '*.sqlite3', '*.db', '*.sql', '*.dump',
        'secrets.*', '*.secrets.*',
    ];

    public const ADAPTERS = ['generic', 'codex', 'claude', 'cursor'];

    public const ANALYZERS = ['composer', 'php', 'routes', 'migrations', 'tests', 'frontend'];

    /**
     * @param  list<string>  $include
     * @param  list<string>  $exclude
     * @param  list<string>  $sensitive
     * @param  array<string, bool>  $analyzers
     * @param  array<string, bool>  $adapters
     * @param  list<string>  $disabledRules
     */
    public function __construct(
        public readonly string $outputDirectory,
        public readonly array $include,
        public readonly array $exclude,
        public readonly array $sensitive,
        public readonly array $analyzers,
        public readonly array $adapters,
        public readonly bool $documentation,
        public readonly string $docsDirectory,
        public readonly string $tasksDirectory,
        public readonly bool $cache,
        public readonly bool $strict,
        public readonly array $disabledRules,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException with a message naming the invalid key
     */
    public static function fromArray(array $config): self
    {
        $include = self::pathList($config['paths']['include'] ?? ['app', 'routes', 'database/migrations', 'tests', 'resources'], 'paths.include');
        $exclude = array_values(array_unique(array_merge(
            self::ALWAYS_EXCLUDED,
            self::stringList($config['paths']['exclude'] ?? [], 'paths.exclude'),
        )));

        return new self(
            outputDirectory: self::relativeDirectory($config['output_directory'] ?? '.ai-blueprint', 'output_directory'),
            include: $include,
            exclude: $exclude,
            sensitive: array_values(array_unique(array_merge(self::BUILTIN_SENSITIVE, self::stringList($config['sensitive'] ?? [], 'sensitive')))),
            analyzers: self::toggles($config['analyzers'] ?? [], self::ANALYZERS, 'analyzers'),
            adapters: self::toggles($config['adapters'] ?? [], self::ADAPTERS, 'adapters'),
            documentation: (bool) ($config['documentation']['enabled'] ?? true),
            docsDirectory: self::relativeDirectory($config['documentation']['directory'] ?? 'docs', 'documentation.directory'),
            tasksDirectory: self::relativeDirectory($config['tasks']['directory'] ?? 'docs/tasks', 'tasks.directory'),
            cache: (bool) ($config['cache']['enabled'] ?? true),
            strict: (bool) ($config['checks']['strict'] ?? false),
            disabledRules: self::stringList($config['checks']['disabled'] ?? [], 'checks.disabled'),
        );
    }

    /**
     * @return list<string>
     */
    public function enabledAdapters(): array
    {
        return array_keys(array_filter($this->adapters));
    }

    public function analyzerEnabled(string $name): bool
    {
        return $this->analyzers[$name] ?? true;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $key): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("ai-blueprint config [{$key}] must be a list of strings.");
        }
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("ai-blueprint config [{$key}] must contain only non-empty strings.");
            }
        }

        return array_values($value);
    }

    /**
     * @return list<string>
     */
    private static function pathList(mixed $value, string $key): array
    {
        return array_map(static fn (string $p): string => self::relativeDirectory($p, $key), self::stringList($value, $key));
    }

    private static function relativeDirectory(mixed $value, string $key): string
    {
        if (! is_string($value) || trim($value) === '' || Paths::isAbsolute($value)) {
            throw new InvalidArgumentException("ai-blueprint config [{$key}] must be a non-empty path relative to the project root.");
        }

        try {
            $normalized = Paths::normalize($value);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException("ai-blueprint config [{$key}] must not point outside the project root.");
        }

        if ($normalized === '') {
            throw new InvalidArgumentException("ai-blueprint config [{$key}] must not be the project root itself.");
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $known
     * @return array<string, bool>
     */
    private static function toggles(mixed $value, array $known, string $key): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("ai-blueprint config [{$key}] must be an array of name => bool.");
        }
        foreach (array_keys($value) as $name) {
            if (! in_array($name, $known, true)) {
                throw new InvalidArgumentException("ai-blueprint config [{$key}] contains unknown entry [{$name}]. Known: ".implode(', ', $known).'.');
            }
        }

        $result = [];
        foreach ($known as $name) {
            $result[$name] = (bool) ($value[$name] ?? true);
        }

        return $result;
    }
}
