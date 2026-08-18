<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_course_map', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique();
            $table->unsignedBigInteger('moodle_course_id');
            $table->unsignedInteger('moodle_role_id')->default(5); // 5 = Student in a stock Moodle install
            $table->boolean('active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_course_map');
    }
};
