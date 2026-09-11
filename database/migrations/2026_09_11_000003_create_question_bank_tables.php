<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('question_categories')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('question_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('question_bank_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('question_categories')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'ready', 'retired'])->default('draft')->index();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->enum('type', ['single_choice', 'multiple_choice', 'true_false', 'short_answer', 'numerical', 'fill_blank', 'essay'])->index();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable()->index();
            $table->longText('question_text');
            $table->string('image_path')->nullable();
            $table->decimal('default_marks', 8, 2)->default(1);
            $table->decimal('negative_marks', 8, 2)->default(0);
            $table->json('grading_rules')->nullable();
            $table->longText('general_feedback')->nullable();
            $table->longText('explanation')->nullable();
            $table->string('content_hash', 64)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->unique(['question_bank_entry_id', 'version_number'], 'question_versions_entry_version_unique');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->longText('option_text');
            $table->decimal('fraction', 8, 4)->default(0);
            $table->longText('feedback')->nullable();
            $table->timestamps();
            $table->unique(['question_version_id', 'position']);
        });

        Schema::create('question_bank_entry_question_tag', function (Blueprint $table) {
            $table->foreignId('question_bank_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['question_bank_entry_id', 'question_tag_id'], 'question_entry_tag_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_entry_question_tag');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('question_bank_entries');
        Schema::dropIfExists('question_tags');
        Schema::dropIfExists('question_categories');
    }
};
