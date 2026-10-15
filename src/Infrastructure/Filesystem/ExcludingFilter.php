<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

use AndrewProd\AiBlueprint\Support\Paths;
use Closure;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;

/**
 * Prunes excluded directories and symlinked directories before descending into them.
 *
 * @extends RecursiveFilterIterator<string, \SplFileInfo, RecursiveDirectoryIterator>
 */
final class ExcludingFilter extends RecursiveFilterIterator
{
    /**
     * @param  Closure(string): bool  $isExcluded
     */
    public function __construct(
        RecursiveDirectoryIterator $iterator,
        private readonly string $root,
        private readonly Closure $isExcluded,
    ) {
        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        /** @var \SplFileInfo $current */
        $current = $this->current();

        if ($current->isDir() && $current->isLink()) {
            return false;
        }

        $relative = Paths::relativeTo($this->root, str_replace('\\', '/', $current->getPathname()));

        return $relative !== null && ! ($this->isExcluded)($relative);
    }

    public function getChildren(): self
    {
        /** @var RecursiveDirectoryIterator $inner */
        $inner = $this->getInnerIterator();

        return new self($inner->getChildren(), $this->root, $this->isExcluded);
    }
}
