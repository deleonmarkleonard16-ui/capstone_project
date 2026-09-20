<?php

return [
    // Fill these from the exact paper instruments approved by the guidance office.
    // No item key is inferred from the legacy demonstration questionnaires.
    'bfpi' => ['items' => 0, 'subscales' => [], 'reverse_items' => [], 'overlap' => 'Average'],
    // Existing six-item RIASEC questionnaire, selected by the guidance office.
    'career' => [
        'items' => 6, 'min' => 1, 'max' => 5,
        'traits' => [1 => 'Realistic', 2 => 'Investigative', 3 => 'Artistic', 4 => 'Social', 5 => 'Enterprising', 6 => 'Conventional'],
        'choices' => [1 => 'Not interested', 2 => 'Slightly interested', 3 => 'Moderately interested', 4 => 'Very interested', 5 => 'Extremely interested'],
        'questions' => [
            1 => 'I like to work with tools, machinery, equipment, or hands-on physical activities.',
            2 => 'I enjoy analyzing problems, researching scientific ideas, or solving complex puzzles.',
            3 => 'I enjoy creating art, writing, music, designing, or expressing unique creative ideas.',
            4 => 'I like teaching, helping, counseling, or caring for other people.',
            5 => 'I enjoy leading teams, persuading others, marketing, or starting business projects.',
            6 => 'I like organizing records, working with numbers, databases, and structured procedures.',
        ],
    ],
    'exit' => ['items' => 0, 'min' => 1, 'max' => 5],
];
