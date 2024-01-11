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
            $table->string('name')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('tags')->nullable();
            $table->float('distance')->nullable();
            $table->integer('ele_max')->nullable();
            $table->integer('ele_min')->nullable();
            $table->integer('ele_from')->nullable();
            $table->integer('ele_to')->nullable();
            $table->integer('ascent')->nullable();
            $table->integer('descent')->nullable();
            $table->integer('duration_forward_hiking')->nullable();
            $table->integer('duration_backward_hiking')->nullable();
            $table->integer('duration_forward_bike')->nullable();
            $table->integer('duration_backward_bike')->nullable();
            $table->boolean('round_trip')->nullable();
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
