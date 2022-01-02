<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('question_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->integer('parent')->nullable();
            $table->string('layout');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('question_groups');
    }
}