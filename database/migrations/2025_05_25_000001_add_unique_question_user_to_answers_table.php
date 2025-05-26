<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Remove duplicates before adding unique constraint
        // Cant do the below query, as it is too slow
        // DB::statement('DELETE a1 FROM answers a1 JOIN answers a2 ON a1.question_id = a2.question_id AND a1.user_id = a2.user_id AND a1.id > a2.id');

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
