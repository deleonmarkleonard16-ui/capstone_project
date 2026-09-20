<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerKeyAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'answer_key_id',
        'question_number',
        'answer',
    ];

    public function answerKey(): BelongsTo
    {
        return $this->belongsTo(AnswerKey::class);
    }
}
