<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Remove duplicates before adding unique constraint
        DB::statement('DELETE a1 FROM answers a1 JOIN answers a2 ON a1.question_id = a2.question_id AND a1.user_id = a2.user_id AND a1.id > a2.id');
        Schema::table('answers', function (Blueprint $table) {
            $table->unique(['question_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropUnique(['answers_question_id_user_id_unique']);
        });
    }
};
