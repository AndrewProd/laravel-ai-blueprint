<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Output directory
    |--------------------------------------------------------------------------
    |
    | Where blueprint.json, decisions.json, manifest.json and the analysis cache
    | are stored, relative to the project root. Commit everything except cache/.
    |
    */

    'output_directory' => '.ai-blueprint',

    /*
    |--------------------------------------------------------------------------
    | Scanned paths
    |--------------------------------------------------------------------------
    |
    | Only directories listed in "include" are scanned. "exclude" entries are
    | directories or glob patterns that are never scanned, even inside included
    | directories. Exclude entries without "/" match a directory or file name
    | at any depth. Root files such as composer.json and package.json are read
    | individually.
    |
    */

    'paths' => [
        'include' => [
            'app',
            'routes',
            'database/migrations',
            'tests',
            'resources',
        ],

        'exclude' => [
            'vendor',
            'node_modules',
            'storage',
            'bootstrap/cache',
            '.git',
            'public/build',
            'public/hot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive files
    |--------------------------------------------------------------------------
    |
    | Files matching these patterns are never read, cached or reported by
    | content. Patterns without "/" match the file name anywhere. These are
    | added to the built-in list; they cannot remove built-in patterns.
    |
    */

    'sensitive' => [
        // '*.secret.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Analyzers
    |--------------------------------------------------------------------------
    */

    'analyzers' => [
        'composer' => true,
        'php' => true,
        'routes' => true,
        'migrations' => true,
        'tests' => true,
        'frontend' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent adapters
    |--------------------------------------------------------------------------
    |
    | generic -> AGENTS.md
    | codex   -> AGENTS.md (Codex reads AGENTS.md)
    | claude  -> CLAUDE.md and .claude/rules/*.md
    | cursor  -> .cursor/rules/*.mdc
    |
    */

    'adapters' => [
        'generic' => true,
        'codex' => true,
        'claude' => true,
        'cursor' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated documentation
    |--------------------------------------------------------------------------
    */

    'documentation' => [
        'enabled' => true,
        'directory' => 'docs',
    ],

    'tasks' => [
        'directory' => 'docs/tasks',
    ],

    'cache' => [
        'enabled' => true,
    ],

    'checks' => [
        // Treat warnings as failures (same as --strict).
        'strict' => false,

        // Rule ids to skip, e.g. ['BP007'].
        'disabled' => [],
    ],
];
