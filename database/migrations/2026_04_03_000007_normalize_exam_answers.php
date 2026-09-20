<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_sheet_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('question_number');
            $table->string('answer', 1);
            $table->timestamps();

            $table->unique(['answer_sheet_id', 'question_number']);
        });

        Schema::create('answer_key_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_key_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('question_number');
            $table->string('answer', 1);
            $table->timestamps();

            $table->unique(['answer_key_id', 'question_number']);
        });

        $this->migrateLegacyAnswersToNormalizedTables();

        Schema::table('answer_sheets', function (Blueprint $table) {
            $table->dropColumn($this->questionColumns());
        });

        Schema::table('answer_keys', function (Blueprint $table) {
            $table->dropColumn($this->questionColumns());
        });
    }

    public function down(): void
    {
        Schema::table('answer_sheets', function (Blueprint $table) {
            foreach ($this->questionColumns() as $column) {
                $table->string($column, 1)->nullable();
            }
        });

        Schema::table('answer_keys', function (Blueprint $table) {
            foreach ($this->questionColumns() as $column) {
                $table->string($column, 1)->nullable();
            }
        });

        $this->migrateNormalizedTablesBackToLegacyColumns();

        Schema::dropIfExists('answer_sheet_answers');
        Schema::dropIfExists('answer_key_answers');
    }

    private function migrateLegacyAnswersToNormalizedTables(): void
    {
        $timestamp = now();

        DB::table('answer_sheets')
            ->orderBy('id')
            ->get()
            ->each(function (object $sheet) use ($timestamp): void {
                $rows = [];

                foreach (range(1, 80) as $number) {
                    $column = "q{$number}";

                    if (! isset($sheet->{$column}) || $sheet->{$column} === null) {
                        continue;
                    }

                    $rows[] = [
                        'answer_sheet_id' => $sheet->id,
                        'question_number' => $number,
                        'answer' => $sheet->{$column},
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('answer_sheet_answers')->insert($rows);
                }
            });

        DB::table('answer_keys')
            ->orderBy('id')
            ->get()
            ->each(function (object $answerKey) use ($timestamp): void {
                $rows = [];

                foreach (range(1, 80) as $number) {
                    $column = "q{$number}";

                    if (! isset($answerKey->{$column}) || $answerKey->{$column} === null) {
                        continue;
                    }

                    $rows[] = [
                        'answer_key_id' => $answerKey->id,
                        'question_number' => $number,
                        'answer' => $answerKey->{$column},
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('answer_key_answers')->insert($rows);
                }
            });
    }

    private function migrateNormalizedTablesBackToLegacyColumns(): void
    {
        DB::table('answer_sheet_answers')
            ->orderBy('answer_sheet_id')
            ->orderBy('question_number')
            ->get()
            ->groupBy('answer_sheet_id')
            ->each(function ($answers, $answerSheetId): void {
                $payload = [];

                foreach ($answers as $answer) {
                    $payload['q'.$answer->question_number] = $answer->answer;
                }

                if ($payload !== []) {
                    DB::table('answer_sheets')->where('id', $answerSheetId)->update($payload);
                }
            });

        DB::table('answer_key_answers')
            ->orderBy('answer_key_id')
            ->orderBy('question_number')
            ->get()
            ->groupBy('answer_key_id')
            ->each(function ($answers, $answerKeyId): void {
                $payload = [];

                foreach ($answers as $answer) {
                    $payload['q'.$answer->question_number] = $answer->answer;
                }

                if ($payload !== []) {
                    DB::table('answer_keys')->where('id', $answerKeyId)->update($payload);
                }
            });
    }

    private function questionColumns(): array
    {
        return array_map(
            fn (int $number) => "q{$number}",
            range(1, 80)
        );
    }
};
