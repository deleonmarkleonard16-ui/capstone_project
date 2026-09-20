<?php

namespace App\Services;

class GuidanceCategories
{
    public const LABELS = ['psychological' => 'Psychological Assessment', 'personality' => 'Personality Test', 'career' => 'Career Test'];
    public const TESTS = ['psychological' => ['dass21', 'phq9', 'gad7'], 'personality' => ['bfpi'], 'career' => ['career']];
}
