<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;

final class SyncItem
{
    /** File does not exist yet. */
    public const CREATE = 'create';

    /** Managed, untouched since generation, and the generated content changed. */
    public const UPDATE = 'update';

    /** Already identical to the generated content. */
    public const UNCHANGED = 'unchanged';

    /** Managed file was edited manually. */
    public const CONFLICT = 'conflict';

    /** A file exists at this path that Blueprint did not create. */
    public const UNMANAGED = 'unmanaged';

    /** Append a managed section to an existing Markdown file Blueprint did not create. */
    public const APPEND = 'append';

    public function __construct(
        public readonly string $path,
        public readonly string $action,
        public readonly GeneratedArtifact $artifact,
        public readonly ?string $diff = null,
        public bool $written = false,
        public readonly string $mode = 'file',
        public readonly ?string $current = null,
    ) {}

    public function isConflict(): bool
    {
        return $this->action === self::CONFLICT || $this->action === self::UNMANAGED;
    }
}
