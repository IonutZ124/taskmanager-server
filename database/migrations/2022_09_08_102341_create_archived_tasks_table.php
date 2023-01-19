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
        Schema::create('archived_tasks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("task_id")->unsigned();
            $table->foreign("task_id")->references("id")->on("tasks")->onDelete("cascade");
            $table->bigInteger("archived_by")->unsigned();
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
        Schema::dropIfExists('archived_tasks');
    }
};
