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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('s3_path');
            $table->json('json_data');
            $table->json('cookie_data');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('last_run_by')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->boolean('status')->default(1)->comment('1 - already, 2 - in use');

            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups');
            $table->foreign('last_run_by')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('profiles');
    }
};
