<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Find all duplicate (question_id, user_id) pairs
        $duplicates = DB::table('answers')
            ->select('question_id', 'user_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('question_id', 'user_id')
            ->having('duplicate_count', '>', 1)
            ->get();
        printf("Found %d duplicate question_id/user_id pairs\n", $duplicates->count());

        foreach ($duplicates as $dup) {
            printf("Processing duplicate for question_id %d and user_id %d\n", $dup->question_id, $dup->user_id);
            // For each duplicate pair, delete one (the one with the highest id)
            $row = DB::table('answers')
                ->where('question_id', $dup->question_id)
                ->where('user_id', $dup->user_id)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                printf("Deleting answer with id %d for question_id %d and user_id %d\n", $row->id, $dup->question_id, $dup->user_id);
                DB::table('answers')->where('id', $row->id)->delete();
            }
        }

        // And then seemingly try looping? as we still have issues?
        $maxIterations = 1000;
        $iteration = 0;
        while ($iteration < $maxIterations) {
            try {
            Schema::table('answers', function (Blueprint $table) {
                $table->unique(['question_id', 'user_id']);
            });
            // Success, break the loop
            break;
            } catch (\Illuminate\Database\QueryException $e) {
            $message = $e->getMessage();
            printf("Iteration %d: %s\n", $iteration, $message);
            // Match "Duplicate entry '124-2' for key 'answers_question_id_user_id_unique'"
            if (preg_match("/Duplicate entry '(\d+)-(\d+)'/", $message, $matches)) {
                printf("Found duplicate entry for question_id %s and user_id %s. Deleting...n", $matches[1], $matches[2]);
                $questionId = $matches[1];
                $userId = $matches[2];
                // Delete one duplicate row
                DB::statement("
                DELETE FROM answers
                WHERE id = (
                    SELECT id FROM (
                    SELECT id FROM answers
                    WHERE question_id = ? AND user_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                    ) as sub
                )
                ", [$questionId, $userId]);
            } else {
                // If it's a different error, rethrow
                throw $e;
            }
            }
            $iteration++;
        }
        if ($iteration === $maxIterations) {
            throw new \Exception("Maximum iterations ($maxIterations) reached while trying to add unique constraint.");
        }
    }

    public function down()
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropUnique(['answers_question_id_user_id_unique']);
        });
    }
};
