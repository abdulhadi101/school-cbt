<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 30)->default('in_progress')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedInteger('seed');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->text('invalidation_reason')->nullable();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exam_id', 'student_id', 'attempt_number']);
            $table->index(['exam_id', 'status']);
        });

        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_version_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->decimal('marks', 8, 2);
            $table->json('question_snapshot');
            $table->json('option_order')->nullable();
            $table->boolean('requires_manual_grading')->default(false)->index();
            $table->timestamps();
            $table->unique(['attempt_id', 'position']);
            $table->unique(['attempt_id', 'question_version_id'], 'attempt_questions_attempt_version_unique');
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_question_id')->constrained()->cascadeOnDelete();
            $table->json('response')->nullable();
            $table->unsignedInteger('client_sequence')->default(0);
            $table->timestamp('client_answered_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->string('grading_status', 30)->default('ungraded')->index();
            $table->decimal('score', 8, 2)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->longText('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'attempt_question_id'], 'attempt_answers_attempt_question_unique');
        });

        Schema::create('answer_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->json('response')->nullable();
            $table->unsignedInteger('client_sequence')->default(0);
            $table->timestamp('client_answered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('source')->default('web');
            $table->timestamps();
        });

        Schema::create('manual_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grader_id')->constrained('users')->restrictOnDelete();
            $table->decimal('previous_score', 8, 2)->nullable();
            $table->decimal('score', 8, 2);
            $table->longText('feedback')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_final')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_events');
        Schema::dropIfExists('manual_grades');
        Schema::dropIfExists('answer_revisions');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempt_questions');
        Schema::dropIfExists('attempts');
    }
};
