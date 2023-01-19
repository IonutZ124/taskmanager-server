<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('board_invites', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("board_id")->unsigned();
            $table->foreign("board_id")->references("id")->on("boards")->onDelete("cascade");
            $table->string("email");
            $table->string("code");
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
        Schema::dropIfExists('board_invites');
    }
};
