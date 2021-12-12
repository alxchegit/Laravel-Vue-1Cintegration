<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auth_data', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->primary()->unique();
            $table->longText('access_token');
            $table->longText('refresh_token');
            $table->dateTime('expires');
            $table->string('base_domain');
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
        Schema::dropIfExists('auth_data');
    }
}
