<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->geometry('geometry');
            $table->string('name');
            $table->unsignedBigInteger('source_id');
            $table->json('tags');
            $table->decimal('distance');
            $table->integer('ele_max');
            $table->integer('ele_min');
            $table->integer('ele_from');
            $table->integer('ele_to');
            $table->integer('ascent');
            $table->integer('descent');
            $table->integer('duration_forward_hiking');
            $table->integer('duration_backward_hiking');
            $table->boolean('round_trip');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
