<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_shows_school_stats_and_upcoming_exams(): void
    {
        SchoolSetting::query()->create(['name' => 'Demo College', 'code' => 'DC']);
        $subject = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MTH']);
        Exam::query()->create([
            'subject_id' => $subject->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'First CA',
            'exam_type' => 'ca',
            'status' => ExamStatus::Published,
            'duration_minutes' => 30,
            'total_marks' => 10,
            'max_attempts' => 1,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'score_release_policy' => 'manual',
        ]);
        Exam::query()->create([
            'subject_id' => $subject->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'Old Draft',
            'exam_type' => 'ca',
            'status' => ExamStatus::Draft,
            'duration_minutes' => 30,
            'total_marks' => 10,
            'max_attempts' => 1,
            'score_release_policy' => 'manual',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Landing')
                ->where('school.name', 'Demo College')
                ->where('stats.subjects', 1)
                ->where('stats.published_exams', 1)
                ->where('upcoming.0.title', 'First CA')
                ->where('upcoming.0.is_open', true)
                ->missing('upcoming.1')
            );
    }

    public function test_landing_works_without_school_settings(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Landing')
                ->where('school.name', 'School CBT')
            );
    }
}
