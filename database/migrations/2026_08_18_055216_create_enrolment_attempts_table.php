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
        Schema::create('enrolment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrolment_requests_id')->constrained('enrolment_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->json('request_body');
            $table->json('response_body')->nullable();
            $table->boolean('succeeded')->default(false);
            $table->timestamp('created_at')->useCurrent();
            /*
            class EnrolmentAttempt extends Model
            {
                const UPDATED_AT = null;
            }
            */
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrolment_attempts');
    }
};
