<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuDepictsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qu_depicts', function (Blueprint $table) {
            $table->id();
            $table->string('mediainfo_id');
            $table->string('depicts_id');
            $table->text('img_url'); // img urls can be quite long, so use text
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qu_depicts');
    }
}