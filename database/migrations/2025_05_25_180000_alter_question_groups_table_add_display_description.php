<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterQuestionGroupsTableAddDisplayDescription extends Migration
{
    public function up()
    {
        Schema::table('question_groups', function(Blueprint $table)
        {
            $table->dropColumn('display_description');
        });
    }

    public function down()
    {
        // No-op: column already removed
    }
}