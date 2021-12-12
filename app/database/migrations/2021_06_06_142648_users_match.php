<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UsersMatch extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('users_match', function (Blueprint $table) {
            $table->integer('account_id');
            $table->integer('amo_user_id');
            $table->longText('c_user_id')->nullable(true);
            $table->timestamps();

            $table->primary(['account_id', 'amo_user_id'], 'primary_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_match');
    }
}
