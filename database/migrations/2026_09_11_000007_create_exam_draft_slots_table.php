<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_draft_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('slot_type', 30);
            $table->foreignId('question_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('tag_ids')->nullable();
            $table->string('difficulty', 20)->nullable();
            $table->unsignedSmallInteger('question_count')->default(1);
            $table->decimal('marks_per_question', 8, 2);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['exam_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_draft_slots');
    }
};
