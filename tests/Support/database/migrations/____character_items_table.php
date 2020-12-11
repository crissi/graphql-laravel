<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CharacterItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('character_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('character_id');
            $table->boolean('is_heavy');
            $table->timestamps();
        });
    }
}
