<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Components;

/**
 * How trustworthy a fact is. Inferred facts must never be presented as confirmed.
 */
enum Status: string
{
    /** Directly found in a source file. */
    case Observed = 'observed';

    /** Derived from naming or structure; may be wrong. */
    case Inferred = 'inferred';

    /** Explicitly confirmed by a human. */
    case Confirmed = 'confirmed';

    /** Not enough information. */
    case Unknown = 'unknown';
}
