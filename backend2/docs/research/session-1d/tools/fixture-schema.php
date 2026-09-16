<?php

declare(strict_types=1);

/**
 * SESSION-1d · THE FIXTURES AGAINST THE CONTRACT — every card of `docs/fixtures/day-doctor*.json` checked against
 * `PlanCard` in `openapi/openapi.yaml` by a small validator of the subset the spec uses: local `$ref`, `allOf`, `type` (one or a
 * list), `enum`, `const`, `minimum`, `required`, `properties`, `additionalProperties: false`, `items`, `oneOf` (exactly one
 * branch). The payload's `oneOf` must resolve to ONE schema, and its `title` must be the card's `kind`.
 *
 *   docker compose exec -T app php docs/research/session-1d/tools/fixture-schema.php [spec.yaml]
 */

use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../../../../vendor/autoload.php';

$root = __DIR__.'/../../../..';
// The spec to check against — the repository's own by default; another path proves the check can fail (the spec before
// SESSION-1d still requires `text_native_gapped`).
$spec = Yaml::parseFile($argv[1] ?? $root.'/openapi/openapi.yaml');

/** @return list<string> the errors of `$value` against `$schema` at `$path` */
function s1dErrors(array $spec, mixed $value, array $schema, string $path): array
{
    if (isset($schema['$ref'])) {
        $node = $spec;
        foreach (explode('/', substr($schema['$ref'], 2)) as $key) {
            $node = $node[$key];
        }

        return s1dErrors($spec, $value, $node, $path);
    }
    if (isset($schema['allOf'])) {
        $errors = [];
        foreach ($schema['allOf'] as $branch) {
            $errors = [...$errors, ...s1dErrors($spec, $value, $branch, $path)];
        }

        return $errors;
    }
    if (isset($schema['oneOf'])) {
        $matches = 0;
        foreach ($schema['oneOf'] as $branch) {
            if (s1dErrors($spec, $value, $branch, $path) === []) {
                $matches++;
            }
        }

        return $matches === 1 ? [] : ["{$path}: oneOf matched {$matches} branches"];
    }
    $errors = [];
    if (isset($schema['type'])) {
        $types = (array) $schema['type'];
        $actual = match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) && ($value !== [] || in_array('array', $types, true)) => 'array',
            is_array($value) => 'object',
        };
        $ok = in_array($actual, $types, true) || ($actual === 'integer' && in_array('number', $types, true));
        if (! $ok) {
            return ["{$path}: {$actual} is not ".implode('|', $types)];
        }
    }
    if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
        $errors[] = "{$path}: ".json_encode($value).' not in enum';
    }
    if (array_key_exists('const', $schema) && $value !== $schema['const']) {
        $errors[] = "{$path}: ".json_encode($value).' is not '.json_encode($schema['const']);
    }
    if (isset($schema['minimum']) && is_numeric($value) && $value < $schema['minimum']) {
        $errors[] = "{$path}: {$value} below {$schema['minimum']}";
    }
    if (is_array($value) && ! array_is_list($value) || (is_array($value) && $value === [] && isset($schema['properties']))) {
        foreach ($schema['required'] ?? [] as $key) {
            if (! array_key_exists($key, $value)) {
                $errors[] = "{$path}: missing `{$key}`";
            }
        }
        foreach ($value as $key => $item) {
            if (isset($schema['properties'][$key])) {
                $errors = [...$errors, ...s1dErrors($spec, $item, $schema['properties'][$key], "{$path}.{$key}")];
            } elseif (($schema['additionalProperties'] ?? true) === false) {
                $errors[] = "{$path}: unexpected `{$key}`";
            }
        }
    }
    if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
        foreach ($value as $i => $item) {
            $errors = [...$errors, ...s1dErrors($spec, $item, $schema['items'], "{$path}[{$i}]")];
        }
    }

    return $errors;
}

$total = 0;
$bad = 0;
foreach (['day-doctor.json', 'day-doctor-beginner.json'] as $file) {
    $room = json_decode((string) file_get_contents($root.'/docs/fixtures/'.$file), true, flags: JSON_THROW_ON_ERROR);
    foreach ($room['stages'] as $stage) {
        foreach ($stage['cards'] as $card) {
            $total++;
            $errors = s1dErrors($spec, $card, ['$ref' => '#/components/schemas/PlanCard'], "{$file}#{$card['stage']}/{$card['position']}");
            $titles = [];
            foreach ($spec['components']['schemas']['PlanCard']['properties']['payload']['oneOf'] as $branch) {
                $schema = $spec['components']['schemas'][substr($branch['$ref'], strrpos($branch['$ref'], '/') + 1)];
                if (s1dErrors($spec, $card['payload'], $schema, 'payload') === []) {
                    $titles[] = $schema['title'];
                }
            }
            if ($titles !== [$card['kind']]) {
                $errors[] = "{$file}#{$card['stage']}/{$card['position']}: payload resolves to [".implode(', ', $titles)."] for {$card['kind']}";
            }
            if ($errors !== []) {
                $bad++;
                echo implode("\n", array_slice($errors, 0, 5)), "\n";
            }
        }
    }
}
echo "cards checked: {$total}, with errors: {$bad}\n";
