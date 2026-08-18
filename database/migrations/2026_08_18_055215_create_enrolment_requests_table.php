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
        Schema::create('enrolment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->index();
            $table->string('external_user_email');
            $table->string('product_id');
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->enum('status', ['pending', 'enrolled', 'failed', 'skipped'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('idempotency_key')->unique(); // sha256(order_id:product_id)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrolment_requests');
    }
};
