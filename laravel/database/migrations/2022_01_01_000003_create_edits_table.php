<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEditsTable extends Migration
{

    public function up()
    {
        Schema::create('edits', function (Blueprint $table) {
            $table->id();
            $table->integer('question_id');
            $table->integer('user_id');
            $table->integer('revision_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('edits');
    }
}