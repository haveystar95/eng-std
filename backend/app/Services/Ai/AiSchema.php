<?php

namespace App\Services\Ai;

/** JSON schemas shared by all providers (strict-mode friendly). */
class AiSchema
{
    public static function wordsSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'words' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'term' => ['type' => 'string'],
                            'translation' => ['type' => 'string'],
                            'transcription' => ['type' => 'string'],
                            'example' => ['type' => 'string'],
                            'cefr_level' => ['type' => 'string', 'enum' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']],
                        ],
                        'required' => ['term', 'translation', 'transcription', 'example', 'cefr_level'],
                    ],
                ],
            ],
            'required' => ['words'],
        ];
    }

    public static function gradeSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'correct' => ['type' => 'boolean'],
                'score' => ['type' => 'integer'],
                'feedback' => ['type' => 'string'],
                'corrected' => ['type' => 'string'],
            ],
            'required' => ['correct', 'score', 'feedback', 'corrected'],
        ];
    }
}
