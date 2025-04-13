<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\QuestionGroup;

class AlterQuestionGroupsTableAddDisplayDescription extends Migration
{
    public function up()
    {
        Schema::table('question_groups', function(Blueprint $table)
        {
            $table->text('display_description')->nullable();
        });
        QuestionGroup::where('name', '=', 'aliases')->update(['display_description' => "Extracted from Wikipedia page leads, these may be useful as Wikidata Item aliases."]);
        QuestionGroup::where('name', '=', 'depicts')->update(['display_description' => "Creating high level depicts statements from existing Commons categories."]);
    }

    public function down()
    {
        Schema::table('question_groups', function(Blueprint $table)
        {
            $table->dropColumn('display_description');
        });
    }
}