<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerSheetAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'answer_sheet_id',
        'question_number',
        'answer',
    ];

    public function answerSheet(): BelongsTo
    {
        return $this->belongsTo(AnswerSheet::class);
    }
}
