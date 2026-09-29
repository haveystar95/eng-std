<?php

declare(strict_types=1);

use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;

/*
 * WHERE A PROMPT LIVES (наряд PROMPTS-1). A module that calls a model keeps its prompts in
 * app/Modules/<Module>/Infrastructure/Prompt/current/ and nowhere else: one file per prompt, named as the prompt and its
 * version (`lesson_skeleton.v1.md`, `plan-builder-v2.1.md`), each with a row in docs/prompts/REGISTRY.md whose path and sha256
 * are the file's. A new version replaces the old file; the old text is in git, never beside the new one, in a fixture, in
 * an incoming folder or in a draft. docs/research/ is the archive of the наряды and is not read.
 */

/** A file of current/: the prompt's name, `.` or `-`, its version, `.md`. */
const PRG_FILE = '/^(?<name>[a-z][a-z_-]*?)[.-](?<version>v\d+(?:\.\d+)*)\.md$/';

/** Not the repo's own files: dependencies, runtime data, and the research archive of the наряды. */
const PRG_NOT_SCANNED = ['vendor', 'node_modules', 'storage', '.git', 'docs/research'];

function prgRoot(): string
{
    return dirname(__DIR__, 2);
}

/** @return list<string> every current/ directory of a module, relative to the root */
function prgCurrentDirs(): array
{
    $dirs = glob(prgRoot().'/app/Modules/*/Infrastructure/Prompt/current', GLOB_ONLYDIR) ?: [];
    sort($dirs);

    return array_map(static fn (string $dir): string => substr($dir, strlen(prgRoot()) + 1), $dirs);
}

/** @return list<string> the files of one directory, relative to the root */
function prgFilesIn(string $dir): array
{
    $files = array_values(array_diff(scandir(prgRoot().'/'.$dir) ?: [], ['.', '..']));
    sort($files);

    return array_map(static fn (string $file): string => $dir.'/'.$file, $files);
}

/** @return list<string> every file of every current/ directory, relative to the root */
function prgCurrentFiles(): array
{
    return array_merge(...array_map(prgFilesIn(...), prgCurrentDirs()));
}

/**
 * The rows of the registry's tables of current prompts — the tables whose head is `| имя | версия | путь | sha256 |`.
 *
 * @return list<array{name: string, version: string, path: string, sha256: string}>
 */
function prgRegistryRows(): array
{
    $rows = [];
    $inTable = false;
    foreach (explode("\n", (string) file_get_contents(prgRoot().'/docs/prompts/REGISTRY.md')) as $line) {
        if (str_starts_with($line, '| имя | версия | путь | sha256 |')) {
            $inTable = true;

            continue;
        }
        if (! str_starts_with($line, '|')) {
            $inTable = false;

            continue;
        }
        if (! $inTable || str_starts_with($line, '|---')) {
            continue;
        }
        $cells = array_map(static fn (string $cell): string => trim($cell, " `\t"), explode('|', $line));
        $rows[] = ['name' => $cells[1], 'version' => $cells[2], 'path' => $cells[3], 'sha256' => $cells[4]];
    }

    return $rows;
}

/**
 * Every file of the repo, outside current/ and outside what is not scanned, whose name starts as a prompt's file does —
 * the name of a prompt of some current/, then `.v` or `-v` and a digit.
 *
 * @param  list<string>  $names
 * @return list<string>
 */
function prgStrays(array $names): array
{
    $mask = '/^(?:'.implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $names)).')[.-]v\d/';
    $current = prgCurrentDirs();
    $skip = static function (string $relative) use ($current): bool {
        foreach ([...PRG_NOT_SCANNED, ...$current] as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    };
    $root = prgRoot();
    $strays = [];
    $walk = static function (string $dir) use (&$walk, &$strays, $root, $skip, $mask): void {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            $relative = substr($path, strlen($root) + 1);
            if ($skip($relative)) {
                continue;
            }
            if (is_dir($path) && ! is_link($path)) {
                $walk($path);
            } elseif (preg_match($mask, $entry) === 1) {
                $strays[] = $relative;
            }
        }
    };
    $walk($root);
    sort($strays);

    return $strays;
}

it('keeps exactly one file per prompt in every current/ directory, named as the prompt and its version', function () {
    $dirs = prgCurrentDirs();

    expect($dirs)->toContain(substr(PlanPromptFiles::DIRECTORY, strlen(prgRoot()) + 1));
    foreach ($dirs as $dir) {
        $names = [];
        foreach (prgFilesIn($dir) as $file) {
            expect(preg_match(PRG_FILE, basename($file), $m))->toBe(1, "{$file}: not a prompt file (name, version, .md)");
            $names[] = $m['name'];
        }
        expect($names)->not->toBeEmpty()
            ->and(array_diff_assoc($names, array_unique($names)))->toBe([], "{$dir}: more than one file of one prompt");
    }
});

it('registers every file of current/ in docs/prompts/REGISTRY.md with its own name, version and sha256, and registers nothing else', function () {
    $rows = prgRegistryRows();
    $byPath = array_column($rows, null, 'path');
    $files = prgCurrentFiles();

    expect(array_keys($byPath))->toEqualCanonicalizing($files)
        ->and(count($rows))->toBe(count($byPath), 'a path registered twice');
    foreach ($files as $file) {
        preg_match(PRG_FILE, basename($file), $m);
        expect($byPath[$file]['version'])->toBe(pathinfo($file, PATHINFO_FILENAME), "{$file}: registered under another version")
            ->and($byPath[$file]['name'])->toBe($m['name'], "{$file}: registered under another name")
            ->and($byPath[$file]['sha256'])->toBe(hash_file('sha256', prgRoot().'/'.$file), "{$file}: the text is not the registered one");
    }
});

it('keeps no file of a prompt outside current/ — no old version, copy, fixture, incoming file or draft', function () {
    $names = [];
    foreach (prgCurrentFiles() as $file) {
        if (preg_match(PRG_FILE, basename($file), $m) === 1) {
            $names[] = $m['name'];
        }
    }

    expect($names)->not->toBeEmpty()
        ->and(prgStrays(array_values(array_unique($names))))->toBe([]);
});

it('keeps nothing in docs/prompts/ but REGISTRY.md', function () {
    expect(array_values(array_diff(scandir(prgRoot().'/docs/prompts') ?: [], ['.', '..'])))->toBe(['REGISTRY.md']);
});

it('reads the plan\'s prompts from its current/ only, one file of the map per file of the directory', function () {
    $paths = array_map(PlanPromptFiles::path(...), array_keys(PlanPromptFiles::FILES));

    expect(array_map(static fn (string $path): string => substr($path, strlen(prgRoot()) + 1), $paths))
        ->toEqualCanonicalizing(prgFilesIn(substr(PlanPromptFiles::DIRECTORY, strlen(prgRoot()) + 1)));
    foreach ($paths as $path) {
        expect(dirname($path))->toBe(PlanPromptFiles::DIRECTORY)->and(is_file($path))->toBeTrue();
    }
});
