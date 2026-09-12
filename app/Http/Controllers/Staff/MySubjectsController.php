<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\QuestionBankEntry;
use App\Models\QuestionCategory;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MySubjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isPrivileged = $user->hasPermission('academic.manage');
        $pairs = $isPrivileged ? collect() : $this->assignedPairs((int) $user->id);
        $allowedKeys = $pairs->map(fn (array $pair): string => $pair['subject_id'].':'.$pair['class_level_id']);

        $categories = QuestionCategory::query()
            ->with(['subject:id,name,code', 'classLevel:id,name'])
            ->whereNotNull('subject_id')
            ->whereNotNull('class_level_id')
            ->when(! $isPrivileged && $pairs->isNotEmpty(), fn (Builder $query): Builder => $query
                ->whereIn('subject_id', $pairs->pluck('subject_id')->all())
                ->whereIn('class_level_id', $pairs->pluck('class_level_id')->all()))
            ->when(! $isPrivileged && $pairs->isEmpty(), fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get()
            ->when(! $isPrivileged, fn (Collection $list): Collection => $list
                ->filter(fn (QuestionCategory $category): bool => $allowedKeys->contains($category->subject_id.':'.$category->class_level_id))
                ->values());

        $counts = $this->questionCounts($categories);

        $items = $categories->map(function (QuestionCategory $category) use ($counts): array {
            $key = $category->subject_id.':'.$category->class_level_id;

            return [
                'subject' => $category->subject ? ['id' => $category->subject->id, 'name' => $category->subject->name, 'code' => $category->subject->code] : null,
                'class_level' => $category->classLevel ? ['id' => $category->classLevel->id, 'name' => $category->classLevel->name] : null,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'questions_count' => (int) ($counts[$key] ?? 0),
            ];
        })->filter(fn (array $item): bool => $item['subject'] !== null && $item['class_level'] !== null)->values();

        if ($isPrivileged && $items->isEmpty()) {
            $items = Subject::query()->orderBy('name')->limit(20)->get()
                ->crossJoin(ClassLevel::query()->orderBy('sort_order')->limit(6)->get())
                ->map(fn ($pair): array => [
                    'subject' => ['id' => $pair[0]->id, 'name' => $pair[0]->name, 'code' => $pair[0]->code],
                    'class_level' => ['id' => $pair[1]->id, 'name' => $pair[1]->name],
                    'category_id' => null,
                    'category_name' => null,
                    'questions_count' => 0,
                ])->values();
        }

        return Inertia::render('Staff/MySubjects/Index', [
            'items' => $items,
            'is_privileged' => $isPrivileged,
        ]);
    }

    /**
     * @return Collection<int, array{subject_id: int, class_level_id: int}>
     */
    private function assignedPairs(int $userId): Collection
    {
        $profile = StaffProfile::query()->where('user_id', $userId)->first();

        if (! $profile) {
            return collect();
        }

        $pairs = [];

        foreach (TeachingAssignment::query()->with('section')->where('staff_profile_id', $profile->id)->cursor() as $assignment) {
            $pair = $this->assignmentPair($assignment);

            if ($pair !== null) {
                $pairs[$pair['subject_id'].':'.$pair['class_level_id']] = $pair;
            }
        }

        return collect(array_values($pairs));
    }

    /**
     * @return array<string, int>
     */
    private function questionCounts(Collection $categories): array
    {
        if ($categories->isEmpty()) {
            return [];
        }

        $rows = QuestionBankEntry::query()->toBase()
            ->selectRaw('subject_id, class_level_id, COUNT(*) as total')
            ->whereIn('subject_id', $categories->pluck('subject_id')->filter()->all())
            ->whereIn('class_level_id', $categories->pluck('class_level_id')->filter()->all())
            ->groupBy('subject_id', 'class_level_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->subject_id.':'.$row->class_level_id] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array{subject_id: int, class_level_id: int}|null
     */
    private function assignmentPair(TeachingAssignment $assignment): ?array
    {
        $classLevelId = $assignment->class_level_id ?? $assignment->section?->class_level_id;

        if (! $assignment->subject_id || ! $classLevelId) {
            return null;
        }

        return ['subject_id' => (int) $assignment->subject_id, 'class_level_id' => (int) $classLevelId];
    }
}
