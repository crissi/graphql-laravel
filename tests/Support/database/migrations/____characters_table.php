<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CharactersTable extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('type');
            $table->integer('hours_of_sleep_needed');
            $table->integer('battery_left');
            $table->unsignedInteger('best_friend_id')->nullable();
            $table->timestamps();
        });
    }
}
