<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PlanSchemas;

/**
 * THE SCHEMA HAS TO PERMIT WHAT THE PROMPT ASKS FOR.
 *
 * A prompt is a request and a schema is a guarantee, and the vendor enforces only the second one:
 * under structured output a model CANNOT emit a key the schema does not name, however plainly the
 * prompt asks for it. So a prompt moved to v0.4 with the schema left at v0.2 does not read as a
 * drift — it reads as a model that ignores instructions.
 *
 * That is exactly what the live run of наряд P2-v0.4 bought: `PlanSchemas::outline()` still
 * described the old skeleton, every scene came back without its `intro`, and `scene.intro_missing`
 * refused the plan four times over for real money. Nothing in the suite could see it, because the
 * fake model answers past the schema and the fixtures are read from disk.
 *
 * These two tests close that hole from both sides: the hand-written fixtures — the same ones the
 * validators judge — must be expressible under the schemas the vendor is handed. If a shelf, a
 * field or a whole scene ever moves in one file and not the other, one of them fails here.
 */

/**
 * Check a payload against a JSON Schema subset — required keys, no strays, and the item shape of
 * every array. Deliberately hand-rolled and tiny: it asserts the two rules `strict` mode is about.
 *
 * @param  array<string, mixed>  $schema
 * @return list<string>  paths that broke, empty when the payload fits
 */
function schemaMisfits(array $schema, mixed $value, string $path = '$'): array
{
    $types = (array) ($schema['type'] ?? []);

    if (in_array('object', $types, true) && is_array($value)) {
        $out = [];
        /** @var array<string, mixed> $properties */
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach (array_keys($properties) as $key) {
            if (! array_key_exists($key, $value)) {
                $out[] = "{$path}.{$key} — схема требует, ответа нет";
            }
        }
        foreach (array_keys($value) as $key) {
            if (! array_key_exists($key, $properties)) {
                $out[] = "{$path}.{$key} — ответ пишет, схема не разрешает";
            }
        }
        foreach ($properties as $key => $sub) {
            if (array_key_exists($key, $value) && is_array($sub)) {
                $out = [...$out, ...schemaMisfits($sub, $value[$key], "{$path}.{$key}")];
            }
        }

        return $out;
    }

    if (in_array('array', $types, true) && is_array($value)) {
        $out = [];
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
        foreach ($value as $i => $item) {
            $out = [...$out, ...schemaMisfits($items, $item, "{$path}[{$i}]")];
        }

        return $out;
    }

    return [];
}

/** @return array<string, mixed> */
function planFixtureJson(string $name): array
{
    /** @var array<string, mixed> $raw */
    $raw = json_decode((string) file_get_contents(__DIR__ . "/../../Fixtures/plan/{$name}"), true);

    return $raw;
}

it('lets P1 answer with the skeleton the v0.4 prompt asks for — scenes, and a вводка in each', function () {
    $schema = PlanSchemas::outline();

    // The вводка is named here first, because its absence is the failure that paid for this file.
    $scene = $schema['properties']['scenes']['items']['properties'];
    expect(array_keys($scene))
        ->toBe(['position', 'title', 'intro', 'skills', 'opening_lines', 'entities'])
        ->and(array_keys($schema['properties']))->toBe(['goal_summary', 'scenes']);

    expect(schemaMisfits($schema, planFixtureJson('s1-outline.v0.4.json')))->toBe([])
        ->and(schemaMisfits($schema, planFixtureJson('s2-outline.v0.4.json')))->toBe([])
        ->and(schemaMisfits($schema, planFixtureJson('s3-outline.v0.4.json')))->toBe([]);
});

it('lets P2 answer with all six shelves of a day-scene', function () {
    $schema = PlanSchemas::day();

    expect(array_keys($schema['properties']))
        ->toBe(['hear', 'say', 'ask', 'words', 'chunks', 'numbers']);

    expect(schemaMisfits($schema, planFixtureJson('s1-day1.v0.4.json')))->toBe([])
        ->and(schemaMisfits($schema, planFixtureJson('s3-day1.v0.4.json')))->toBe([]);
});
