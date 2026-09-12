<?php

namespace Database\Seeders;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\AcademicSession;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\ClassLevel;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAudience;
use App\Models\ExamDraftSlot;
use App\Models\Permission;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureAcademicStructure();
        $publisher = $this->ensureStaff();
        $students = $this->createStudents();
        $questions = $this->createQuestions();
        $exam = $this->createExam($publisher, $questions);
        $this->createAttempts($exam, $students);
    }

    private function ensureAcademicStructure(): void
    {
        $session = AcademicSession::query()->firstOrCreate(
            ['name' => now()->year.'/'.now()->addYear()->year],
            ['is_current' => true]
        );

        Term::query()->firstOrCreate(
            ['academic_session_id' => $session->id, 'name' => 'First Term'],
            ['is_current' => true]
        );

        ClassLevel::query()->firstOrCreate(['name' => 'JSS 1'], ['sort_order' => 1]);
        ClassLevel::query()->firstOrCreate(['name' => 'JSS 2'], ['sort_order' => 2]);

        $jss1 = ClassLevel::query()->where('name', 'JSS 1')->first();
        Section::query()->firstOrCreate(['class_level_id' => $jss1->id, 'name' => 'A'], ['capacity' => 40]);
        Section::query()->firstOrCreate(['class_level_id' => $jss1->id, 'name' => 'B'], ['capacity' => 40]);

        Subject::query()->firstOrCreate(['code' => 'MTH'], ['name' => 'Mathematics']);
    }

    private function ensureStaff(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'exam-officer'], ['label' => 'Exam Officer']);
        $perm = Permission::query()->firstOrCreate(
            ['name' => 'exams.publish'],
            ['label' => 'Publish Exams', 'group' => 'exams', 'description' => null]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::query()->firstOrCreate(
            ['email' => 'demo.teacher@school.test'],
            [
                'name' => 'Demo Teacher',
                'username' => 'demo.teacher',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createStudents(): array
    {
        $jss1a = Section::query()->whereHas('classLevel', fn ($q) => $q->where('name', 'JSS 1'))->where('name', 'A')->first();
        $jss1b = Section::query()->whereHas('classLevel', fn ($q) => $q->where('name', 'JSS 1'))->where('name', 'B')->first();
        $session = AcademicSession::query()->where('is_current', true)->first();
        $studentRole = Role::query()->firstOrCreate(['name' => 'student'], ['label' => 'Student']);

        $names = [
            ['Amina', 'Lawal'], ['Musa', 'Bello'], ['Chidinma', 'Okafor'], ['Tunde', 'Adeyemi'],
            ['Fatima', 'Abubakar'], ['Emeka', 'Nwosu'], ['Aisha', 'Mohammed'], ['Olumide', 'Ogundimu'],
            ['Ngozi', 'Eze'], ['Yusuf', 'Ibrahim'], ['Blessing', 'Okonkwo'], ['Adewale', 'Olanrewaju'],
            ['Sade', 'Adeyemi'], ['Kemi', 'Oladipo'], ['Chukwuemeka', 'Obi'], ['Zainab', 'Sanusi'],
            ['Deji', 'Olatunde'], ['Funke', 'Adebayo'], ['Ifeanyi', 'Uche'], ['Halima', 'Bello'],
            ['Segun', 'Oyewale'], ['Amara', 'Nnadi'], ['Babatunde', 'Ogundipe'], ['Chioma', 'Azubuike'],
            ['Damilola', 'Oke'], ['Grace', 'Effiong'], ['Hassan', 'Aliyu'], ['Ifeoma', 'Chukwu'],
            ['Jide', 'Otokitis'], ['Kubra', 'Abdullahi'],
        ];

        $students = [];
        foreach ($names as $i => [$first, $last]) {
            $num = str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $section = $i < 15 ? $jss1a : $jss1b;

            $user = User::query()->firstOrCreate(
                ['email' => strtolower($first).'.'.strtolower($last).'@student.test'],
                [
                    'name' => "$first $last",
                    'username' => strtolower($first).$num,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            $user->roles()->syncWithoutDetaching([$studentRole->id]);

            $student = Student::query()->firstOrCreate(
                ['admission_number' => "STD$num"],
                [
                    'user_id' => $user->id,
                    'first_name' => $first,
                    'last_name' => $last,
                ]
            );

            Enrollment::query()->firstOrCreate(
                ['student_id' => $student->id, 'academic_session_id' => $session->id],
                ['section_id' => $section->id, 'is_current' => true]
            );

            $students[] = $student;
        }

        return $students;
    }

    private function createQuestions(): array
    {
        $subject = Subject::query()->where('code', 'MTH')->first();
        $jss1 = ClassLevel::query()->where('name', 'JSS 1')->first();
        $category = QuestionCategory::query()->firstOrCreate(
            ['slug' => 'mth-jss1-arithmetic'],
            [
                'subject_id' => $subject->id,
                'class_level_id' => $jss1->id,
                'name' => 'Arithmetic',
            ]
        );

        $questions = [
            ['q' => 'What is 12 + 15?', 'options' => ['27', '28', '26', '25'], 'correct' => 0],
            ['q' => 'What is 48 / 6?', 'options' => ['8', '7', '9', '6'], 'correct' => 0],
            ['q' => 'What is 9 x 7?', 'options' => ['63', '56', '72', '54'], 'correct' => 0],
            ['q' => 'Simplify: 3/4 + 1/2', 'options' => ['5/4', '4/4', '7/4', '3/4'], 'correct' => 0],
            ['q' => 'What is 100 - 37?', 'options' => ['63', '67', '73', '57'], 'correct' => 0],
            ['q' => 'What is 15% of 200?', 'options' => ['30', '25', '35', '20'], 'correct' => 0],
            ['q' => 'What is the square root of 81?', 'options' => ['9', '8', '7', '6'], 'correct' => 0],
            ['q' => 'What is 2.5 x 4?', 'options' => ['10', '8', '12', '9'], 'correct' => 0],
            ['q' => 'Solve: 2x + 4 = 12', 'options' => ['x = 4', 'x = 6', 'x = 8', 'x = 3'], 'correct' => 0],
            ['q' => 'What is 3 squared?', 'options' => ['9', '6', '12', '27'], 'correct' => 0],
        ];

        $versions = [];
        foreach ($questions as $index => $data) {
            $entry = QuestionBankEntry::query()->create([
                'category_id' => $category->id,
                'subject_id' => $subject->id,
                'class_level_id' => $jss1->id,
                'status' => 'ready',
                'title' => $data['q'],
            ]);

            $version = QuestionVersion::query()->create([
                'question_bank_entry_id' => $entry->id,
                'version_number' => 1,
                'type' => QuestionType::SingleChoice,
                'difficulty' => $index < 4 ? 'easy' : ($index < 8 ? 'medium' : 'hard'),
                'question_text' => $data['q'],
                'default_marks' => 1,
                'negative_marks' => 0,
                'grading_rules' => [],
                'content_hash' => hash('sha256', $data['q'].uniqid()),
                'ready_at' => now(),
            ]);

            foreach ($data['options'] as $pos => $optionText) {
                QuestionOption::query()->create([
                    'question_version_id' => $version->id,
                    'position' => $pos + 1,
                    'option_text' => $optionText,
                    'fraction' => $pos === $data['correct'] ? 1 : 0,
                ]);
            }

            $versions[] = $version;
        }

        return $versions;
    }

    private function createExam(User $publisher, array $questions): Exam
    {
        $subject = Subject::query()->where('code', 'MTH')->first();
        $term = Term::query()->where('is_current', true)->first();
        $jss1 = ClassLevel::query()->where('name', 'JSS 1')->first();

        $exam = Exam::query()->create([
            'subject_id' => $subject->id,
            'term_id' => $term?->id,
            'created_by' => $publisher->id,
            'title' => 'Mathematics First CA',
            'description' => 'First continuous assessment test for JSS 1 Mathematics.',
            'instructions' => 'Answer all questions. Each question carries 1 mark.',
            'exam_type' => 'ca',
            'status' => ExamStatus::Approved,
            'duration_minutes' => 20,
            'total_marks' => 10,
            'pass_percentage' => 50,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'score_release_policy' => 'manual',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHours(2),
        ]);

        foreach ($questions as $index => $question) {
            ExamDraftSlot::query()->create([
                'exam_id' => $exam->id,
                'slot_type' => 'fixed_question',
                'question_version_id' => $question->id,
                'question_count' => 1,
                'marks_per_question' => 1,
                'position' => $index + 1,
            ]);
        }

        app(ExamRevisionPublisher::class)->publish($exam, $publisher);

        ExamAudience::query()->create([
            'exam_id' => $exam->id,
            'class_level_id' => $jss1->id,
            'section_id' => Section::query()->whereHas('classLevel', fn ($q) => $q->where('name', 'JSS 1'))->where('name', 'A')->first()->id,
        ]);

        return $exam->fresh();
    }

    private function createAttempts(Exam $exam, array $students): void
    {
        $revision = $exam->latestRevision;
        $attemptStates = [
            ['submitted', 15],
            ['graded', 8],
            ['released', 5],
        ];

        $attemptNumber = 0;
        foreach ($attemptStates as [$status, $count]) {
            $eligibleStudents = array_slice($students, $attemptNumber, $count);

            foreach ($eligibleStudents as $student) {
                $attemptNumber++;
                $attempt = Attempt::query()->create([
                    'exam_id' => $exam->id,
                    'exam_revision_id' => $revision->id,
                    'student_id' => $student->id,
                    'attempt_number' => 1,
                    'status' => AttemptStatus::InProgress,
                    'started_at' => now()->subMinutes(10),
                    'deadline_at' => now()->addMinutes(15),
                    'seed' => random_int(100000, 999999),
                ]);

                $questions = $exam->draftSlots()->with('questionVersion.options')->get();

                foreach ($questions as $slot) {
                    $qv = $slot->questionVersion;
                    $correctOption = $qv->options->firstWhere('fraction', '>= 1');
                    $selectedOption = $status === 'graded' || $status === 'released'
                        ? $correctOption
                        : $qv->options->random();

                    $aq = AttemptQuestion::query()->create([
                        'attempt_id' => $attempt->id,
                        'exam_slot_id' => $slot->id,
                        'question_version_id' => $qv->id,
                        'position' => $slot->position,
                        'marks' => $slot->marks_per_question,
                        'question_snapshot' => [
                            'type' => $qv->type->value,
                            'question_text' => $qv->question_text,
                            'options' => $qv->options->map(fn ($o) => [
                                'id' => $o->id,
                                'option_text' => $o->option_text,
                                'position' => $o->position,
                                'fraction' => $o->fraction,
                            ])->values()->all(),
                        ],
                        'requires_manual_grading' => false,
                    ]);

                    $isCorrect = $selectedOption?->id === $correctOption?->id;

                    $gradingStatus = match ($status) {
                        'submitted' => GradingStatus::AutoGraded,
                        'graded', 'released' => GradingStatus::AutoGraded,
                        default => GradingStatus::Ungraded,
                    };

                    $answer = AttemptAnswer::query()->create([
                        'attempt_id' => $attempt->id,
                        'attempt_question_id' => $aq->id,
                        'response' => $selectedOption ? ['selected_option_id' => $selectedOption->id] : null,
                        'client_sequence' => 1,
                        'client_answered_at' => now(),
                        'answered_at' => now(),
                        'time_spent_seconds' => random_int(10, 120),
                        'grading_status' => $gradingStatus,
                        'score' => $isCorrect ? 1 : 0,
                        'is_correct' => $isCorrect,
                        'graded_at' => $status !== 'submitted' ? now() : null,
                    ]);
                }

                $score = $attempt->fresh()->answers()->sum('score');
                $maxScore = $attempt->fresh()->questions()->sum('marks');
                $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0;

                $finalStatus = match ($status) {
                    'submitted' => AttemptStatus::Submitted,
                    'graded' => AttemptStatus::Graded,
                    'released' => AttemptStatus::Released,
                };

                $attempt->forceFill([
                    'status' => $finalStatus,
                    'submitted_at' => now()->subMinutes(5),
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'graded_at' => in_array($status, ['graded', 'released']) ? now()->subMinutes(3) : null,
                    'released_at' => $status === 'released' ? now()->subMinute() : null,
                ])->save();
            }
        }
    }
}
