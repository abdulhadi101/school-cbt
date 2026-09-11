<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->enum('exam_type', ['ca', 'exam', 'practice'])->default('ca')->index();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'published', 'closed', 'archived'])->default('draft')->index();
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('total_marks', 8, 2);
            $table->decimal('pass_percentage', 5, 2)->default(50);
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('closes_at')->nullable()->index();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->enum('score_release_policy', ['immediate', 'after_close', 'manual', 'scheduled'])->default('manual');
            $table->timestamp('score_release_at')->nullable();
            $table->boolean('show_responses')->default(false);
            $table->boolean('show_correct_answers')->default(false);
            $table->boolean('show_feedback')->default(false);
            $table->string('access_code_hash')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('settings_snapshot');
            $table->decimal('total_marks', 8, 2);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'revision_number']);
        });

        Schema::create('exam_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_revision_id')->constrained()->cascadeOnDelete();
            $table->enum('slot_type', ['fixed_question', 'random_pool']);
            $table->foreignId('question_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('tag_ids')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
            $table->unsignedSmallInteger('question_count')->default(1);
            $table->decimal('marks_per_question', 8, 2);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['exam_revision_id', 'position']);
        });

        Schema::create('exam_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['exam_id', 'class_level_id', 'section_id'], 'exam_audiences_unique');
        });

        Schema::create('exam_accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('extra_minutes')->default(0);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedSmallInteger('extra_attempts')->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_accommodations');
        Schema::dropIfExists('exam_audiences');
        Schema::dropIfExists('exam_slots');
        Schema::dropIfExists('exam_revisions');
        Schema::dropIfExists('exams');
    }
};
