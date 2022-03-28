<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->tinyInteger('channel_type')->default(0)->comment('0 - email, 1 - twitter, 2 - google');
            $table->string('channel_id');
            $table->timestamp('email_verified_at')->nullable();
            $table->tinyInteger('role')->unsigned()->default(0);
            $table->tinyInteger('commission')->unsigned()->nullable();
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->string('website')->nullable();
            $table->tinyInteger('cover')->default(0);
            $table->tinyInteger('avatar')->default(0);
            $table->bigInteger('price')->unsigned()->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
