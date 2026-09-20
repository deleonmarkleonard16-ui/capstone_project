<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnswerKey extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected array $pendingAnswers = [];

    protected ?array $questionValueCache = null;

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AnswerKeyAnswer::class);
    }

    public function fill(array $attributes)
    {
        [$baseAttributes, $questionAttributes] = $this->splitQuestionAttributes($attributes);

        foreach ($questionAttributes as $questionNumber => $value) {
            $this->pendingAnswers[$questionNumber] = $value;
        }

        $this->questionValueCache = null;

        return parent::fill($baseAttributes);
    }

    public function getAttribute($key)
    {
        $questionNumber = AnswerSheet::questionNumberFromColumn((string) $key);

        if ($questionNumber !== null) {
            return $this->answerForQuestion($questionNumber);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        $questionNumber = AnswerSheet::questionNumberFromColumn((string) $key);

        if ($questionNumber !== null) {
            $this->pendingAnswers[$questionNumber] = $value;
            $this->questionValueCache = null;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected static function booted(): void
    {
        static::saved(function (self $answerKey): void {
            $answerKey->syncPendingAnswers();
        });
    }

    public function answerForQuestion(int $questionNumber): ?string
    {
        return $this->questionValues()[$questionNumber] ?? null;
    }

    private function splitQuestionAttributes(array $attributes): array
    {
        $baseAttributes = [];
        $questionAttributes = [];

        foreach ($attributes as $key => $value) {
            $questionNumber = AnswerSheet::questionNumberFromColumn((string) $key);

            if ($questionNumber === null) {
                $baseAttributes[$key] = $value;
                continue;
            }

            $questionAttributes[$questionNumber] = $value;
        }

        return [$baseAttributes, $questionAttributes];
    }

    private function questionValues(): array
    {
        if ($this->questionValueCache !== null) {
            return $this->questionValueCache;
        }

        $answers = $this->exists
            ? $this->answers()->pluck('answer', 'question_number')->all()
            : [];

        foreach ($this->pendingAnswers as $questionNumber => $value) {
            if ($value === null || $value === '') {
                unset($answers[$questionNumber]);
                continue;
            }

            $answers[$questionNumber] = $value;
        }

        return $this->questionValueCache = array_map(
            fn (?string $value) => $value,
            $answers
        );
    }

    private function syncPendingAnswers(): void
    {
        if (! $this->exists || $this->pendingAnswers === []) {
            return;
        }

        $upserts = [];
        $deleteQuestionNumbers = [];

        foreach ($this->pendingAnswers as $questionNumber => $value) {
            if ($value === null || $value === '') {
                $deleteQuestionNumbers[] = $questionNumber;
                continue;
            }

            $upserts[] = [
                'answer_key_id' => $this->id,
                'question_number' => $questionNumber,
                'answer' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($upserts !== []) {
            AnswerKeyAnswer::query()->upsert(
                $upserts,
                ['answer_key_id', 'question_number'],
                ['answer', 'updated_at']
            );
        }

        if ($deleteQuestionNumbers !== []) {
            $this->answers()
                ->whereIn('question_number', $deleteQuestionNumbers)
                ->delete();
        }

        $this->pendingAnswers = [];
        $this->questionValueCache = null;
        $this->unsetRelation('answers');
    }
}
