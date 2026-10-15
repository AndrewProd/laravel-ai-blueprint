<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Php;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;
use AndrewProd\AiBlueprint\Contracts\Analyzer;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Infrastructure\Parsers\PhpFileParser;
use AndrewProd\AiBlueprint\Infrastructure\Parsers\PhpFileSymbols;
use AndrewProd\AiBlueprint\Support\Str;
use ParseError;

/**
 * Discovers classes under app/ and classifies them into Laravel roles.
 * Roles backed by a base class or interface are "observed"; roles derived
 * only from directory or naming conventions are "inferred".
 */
final class PhpClassAnalyzer implements Analyzer
{
    public const VERSION = '1';

    /**
     * Base classes / interfaces that prove a role.
     *
     * @var array<string, string>
     */
    private const BASES = [
        'Illuminate\Database\Eloquent\Model' => 'model',
        'Illuminate\Foundation\Auth\User' => 'model',
        'Illuminate\Database\Eloquent\Relations\Pivot' => 'model',
        'Illuminate\Foundation\Http\FormRequest' => 'form_request',
        'Illuminate\Http\Resources\Json\JsonResource' => 'api_resource',
        'Illuminate\Http\Resources\Json\ResourceCollection' => 'api_resource',
        'Illuminate\Support\ServiceProvider' => 'provider',
        'Illuminate\Console\Command' => 'command',
        'Illuminate\Mail\Mailable' => 'mail',
        'Illuminate\Notifications\Notification' => 'notification',
        'Illuminate\Contracts\Queue\ShouldQueue' => 'job',
        'Illuminate\Contracts\Validation\ValidationRule' => 'validation_rule',
        'Illuminate\Contracts\Broadcasting\ShouldBroadcast' => 'event',
    ];

    /**
     * Conventional directories (relative to the app namespace) => role.
     *
     * @var array<string, string>
     */
    private const DIRECTORIES = [
        'Models' => 'model',
        'Http\Controllers' => 'controller',
        'Http\Middleware' => 'middleware',
        'Http\Requests' => 'form_request',
        'Http\Resources' => 'api_resource',
        'Jobs' => 'job',
        'Events' => 'event',
        'Listeners' => 'listener',
        'Policies' => 'policy',
        'Providers' => 'provider',
        'Console\Commands' => 'command',
        'Mail' => 'mail',
        'Notifications' => 'notification',
        'Observers' => 'observer',
        'Rules' => 'validation_rule',
        'Services' => 'service',
        'Actions' => 'action',
        'Exceptions' => 'exception',
        'Enums' => 'enum',
        'Repositories' => 'repository',
        'Contracts' => 'interface',
        'Casts' => 'cast',
        'Http\Integrations' => 'integration',
        'Integrations' => 'integration',
        'Clients' => 'integration',
    ];

    private readonly PhpFileParser $parser;

    public function __construct(?PhpFileParser $parser = null)
    {
        $this->parser = $parser ?? new PhpFileParser;
    }

    public function name(): string
    {
        return 'php';
    }

    public function supports(ProjectContext $project): bool
    {
        return $project->files->directoryExists('app');
    }

    public function analyze(ProjectContext $project): AnalysisResult
    {
        $rootNamespace = $this->appNamespace($project);
        $components = [];
        $relationships = [];
        $findings = [];
        $failed = 0;
        $files = $project->files->in('app', ['php']);

        foreach ($files as $path) {
            $code = $project->files->read($path);
            if ($code === null) {
                continue;
            }

            $symbols = $this->parseCached($project, $path, $code);
            if (is_int($symbols)) {
                $failed++;
                $findings[] = new Finding('BP006', Severity::Warning, "PHP file could not be parsed (syntax error near line {$symbols}); it was skipped.", $path, $this->name());

                continue;
            }

            foreach ($symbols->classes as $class) {
                [$type, $status] = $this->classify($class, $rootNamespace);
                $id = $this->componentId($type, $class['fqcn'], $rootNamespace);

                $components[] = new Component(
                    $id,
                    $type,
                    $class['name'],
                    $path,
                    $status,
                    [new Evidence($path, $class['line'], $class['fqcn'])],
                    array_filter([
                        'class' => $class['fqcn'],
                        'kind' => $class['kind'],
                        'abstract' => $class['abstract'] ?: null,
                        'extends' => $class['extends'],
                        'implements' => $class['implements'] ?: null,
                        'public_methods' => array_values(array_map(
                            static fn (array $m): string => $m['name'],
                            array_filter($class['methods'], static fn (array $m): bool => $m['visibility'] === 'public'),
                        )) ?: null,
                    ], static fn (mixed $v): bool => $v !== null),
                );

                foreach ($class['implements'] as $interface) {
                    $relationships[] = new Relationship($id, 'class:'.$interface, 'implements', Status::Observed, [new Evidence($path, $class['line'])]);
                }

                if ($type === 'listener') {
                    foreach ($class['methods'] as $method) {
                        if (in_array($method['name'], ['handle', '__invoke'], true) && $method['first_param_type'] !== null) {
                            $relationships[] = new Relationship($id, 'class:'.$method['first_param_type'], 'listens_to', Status::Observed, [new Evidence($path, $method['line'])]);
                        }
                    }
                }

                foreach ($symbols->useLines as $imported => $line) {
                    if (str_starts_with($imported, $rootNamespace.'\\')) {
                        $relationships[] = new Relationship($id, 'class:'.$imported, 'imports', Status::Observed, [new Evidence($path, $line)]);
                    }
                }
            }
        }

        $status = $failed > 0 ? AnalyzerReport::PARTIAL : AnalyzerReport::COMPLETE;
        $notes = ['Classes are discovered with the PHP tokenizer; code is never executed.'];
        if ($failed > 0) {
            $notes[] = "{$failed} file(s) could not be parsed.";
        }

        return new AnalysisResult(new AnalyzerReport($this->name(), $status, $notes), $components, $relationships, $findings, [
            'app_namespace' => $rootNamespace,
        ]);
    }

    /**
     * @param  array{kind: string, name: string, fqcn: string, extends: string|null, implements: list<string>}  $class
     * @return array{0: string, 1: Status}
     */
    public function classify(array $class, string $rootNamespace): array
    {
        if ($class['kind'] === 'interface') {
            return ['interface', Status::Observed];
        }
        if ($class['kind'] === 'enum') {
            return ['enum', Status::Observed];
        }
        if ($class['kind'] === 'trait') {
            return ['trait', Status::Observed];
        }

        $relative = str_starts_with($class['fqcn'], $rootNamespace.'\\')
            ? substr($class['fqcn'], strlen($rootNamespace) + 1)
            : $class['fqcn'];

        $byDirectory = null;
        foreach (self::DIRECTORIES as $directory => $role) {
            if (str_starts_with($relative, $directory.'\\')) {
                $byDirectory = $role;
                break;
            }
        }

        foreach (array_merge([$class['extends']], $class['implements']) as $base) {
            if ($base !== null && isset(self::BASES[$base])) {
                $role = self::BASES[$base];
                // A queued listener/mail/notification implements ShouldQueue but is not a job.
                if ($role === 'job' && $byDirectory !== null && $byDirectory !== 'job') {
                    return [$byDirectory, Status::Inferred];
                }

                return [$role, Status::Observed];
            }
        }

        if ($byDirectory !== null) {
            return [$byDirectory, Status::Inferred];
        }

        if (str_ends_with($class['name'], 'Controller')) {
            return ['controller', Status::Inferred];
        }
        if (str_ends_with($class['name'], 'Service')) {
            return ['service', Status::Inferred];
        }

        return ['class', Status::Observed];
    }

    public function componentId(string $type, string $fqcn, string $rootNamespace): string
    {
        $relative = str_starts_with($fqcn, $rootNamespace.'\\') ? substr($fqcn, strlen($rootNamespace) + 1) : $fqcn;

        foreach (self::DIRECTORIES as $directory => $role) {
            if ($role === $type && str_starts_with($relative, $directory.'\\')) {
                $relative = substr($relative, strlen($directory) + 1);
                break;
            }
        }

        return $type.'.'.implode('.', array_map([Str::class, 'snake'], explode('\\', $relative)));
    }

    /**
     * @return PhpFileSymbols|int symbols, or the line of the syntax error
     */
    private function parseCached(ProjectContext $project, string $path, string $code): PhpFileSymbols|int
    {
        $parse = function () use ($code): array {
            try {
                return $this->parser->parse($code)->toArray();
            } catch (ParseError $e) {
                return ['error_line' => $e->getLine()];
            }
        };

        /** @var array<string, mixed> $data */
        $data = $project->cache === null
            ? $parse()
            : $project->cache->remember($this->name(), self::VERSION, $path, $code, $parse);

        return isset($data['error_line']) ? (int) $data['error_line'] : PhpFileSymbols::fromArray($data);
    }

    private function appNamespace(ProjectContext $project): string
    {
        $composer = $project->files->readJson('composer.json');
        foreach ((array) ($composer['autoload']['psr-4'] ?? []) as $namespace => $directory) {
            if (in_array(rtrim((string) (is_array($directory) ? ($directory[0] ?? '') : $directory), '/'), ['app', 'app/'], true)) {
                return rtrim((string) $namespace, '\\');
            }
        }

        return 'App';
    }
}
