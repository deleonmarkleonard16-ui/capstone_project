<?php

return [
    // Fill these from the exact paper instruments approved by the guidance office.
    // No item key is inferred from the legacy demonstration questionnaires.
    /*
    |--------------------------------------------------------------------------
    | BFPI — Big Five Personality Test (BFI-44, 44 items, 5-point Likert scale)
    | Source: PSU Guidance Office instrument (BFI-44 paper answer sheet)
    | Scale: 1 = Disagree (D) | 2 = Slightly Disagree (SD) | 3 = Neutral (N)
    |        4 = Slightly Agree (SA) | 5 = Agree (A)
    | Reverse items: score = 6 − raw answer (5=1, 4=2, 3=3, 2=4, 1=5)
    |--------------------------------------------------------------------------
    */
    'bfpi' => [
        'items'         => 44,
        'min'           => 1,
        'max'           => 5,
        'overlap'       => 'Average',

        'choices' => [
            1 => 'Disagree (D)',
            2 => 'Slightly Disagree (SD)',
            3 => 'Neutral (N)',
            4 => 'Slightly Agree (SA)',
            5 => 'Agree (A)',
        ],

        // Subscale item keys (1-indexed, matching the 5 columns on the paper sheet).
        'subscales' => [
            'Extraversion'      => [1, 6, 11, 16, 21, 26, 31, 36],
            'Agreeableness'     => [2, 7, 12, 17, 22, 27, 32, 37, 42],
            'Conscientiousness' => [3, 8, 13, 18, 23, 28, 33, 38, 43],
            'Neuroticism'       => [4, 9, 14, 19, 24, 29, 34, 39],
            'Openness'          => [5, 10, 15, 20, 25, 30, 35, 40, 41, 44],
        ],

        // Items that are reverse-scored (marked with asterisk * in the rubric column).
        'reverse_items' => [2, 6, 8, 9, 11, 12, 18, 21, 23, 24, 27, 31, 34, 35, 37, 41, 43],
    ],
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
