<?php

namespace App\Support\Permissions;

final class PermissionRegistry
{
    /**
     * @return array<int, array{name: string, label: string, group: string, description: string}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'system.manage', 'label' => 'Manage System', 'group' => 'system', 'description' => 'Configure application settings and backups.'],
            ['name' => 'users.manage', 'label' => 'Manage Users', 'group' => 'identity', 'description' => 'Create and update staff and student accounts.'],
            ['name' => 'academic.manage', 'label' => 'Manage Academic Catalog', 'group' => 'academic', 'description' => 'Manage sessions, terms, classes, sections, and subjects.'],
            ['name' => 'questions.view', 'label' => 'View Questions', 'group' => 'question_bank', 'description' => 'View question-bank entries and versions.'],
            ['name' => 'questions.create', 'label' => 'Create Questions', 'group' => 'question_bank', 'description' => 'Create draft questions.'],
            ['name' => 'questions.update', 'label' => 'Update Questions', 'group' => 'question_bank', 'description' => 'Edit draft questions and create new versions.'],
            ['name' => 'questions.review', 'label' => 'Review Questions', 'group' => 'question_bank', 'description' => 'Mark question versions as ready or retired.'],
            ['name' => 'exams.view', 'label' => 'View Exams', 'group' => 'exams', 'description' => 'View exam drafts, revisions, and schedules.'],
            ['name' => 'exams.create', 'label' => 'Create Exams', 'group' => 'exams', 'description' => 'Create exam drafts and slots.'],
            ['name' => 'exams.update', 'label' => 'Update Exams', 'group' => 'exams', 'description' => 'Edit draft exams before publication.'],
            ['name' => 'exams.submit', 'label' => 'Submit Exams', 'group' => 'exams', 'description' => 'Submit exam drafts for approval.'],
            ['name' => 'exams.approve', 'label' => 'Approve Exams', 'group' => 'exams', 'description' => 'Approve or reject submitted exams.'],
            ['name' => 'exams.publish', 'label' => 'Publish Exams', 'group' => 'exams', 'description' => 'Create immutable published exam revisions.'],
            ['name' => 'exams.close', 'label' => 'Close Exams', 'group' => 'exams', 'description' => 'Close published exams.'],
            ['name' => 'attempts.take', 'label' => 'Take Exams', 'group' => 'attempts', 'description' => 'Start, resume, autosave, and submit eligible attempts.'],
            ['name' => 'attempts.invigilate', 'label' => 'Invigilate Attempts', 'group' => 'attempts', 'description' => 'Monitor live attempts and apply invigilator actions.'],
            ['name' => 'attempts.reopen', 'label' => 'Reopen Attempts', 'group' => 'attempts', 'description' => 'Reopen or extend attempts with an audit reason.'],
            ['name' => 'attempts.invalidate', 'label' => 'Invalidate Attempts', 'group' => 'attempts', 'description' => 'Invalidate attempts with an audit reason.'],
            ['name' => 'grades.view', 'label' => 'View Grades', 'group' => 'grading', 'description' => 'View grading queues and submitted answers.'],
            ['name' => 'grades.grade', 'label' => 'Grade Answers', 'group' => 'grading', 'description' => 'Enter manual marks and feedback.'],
            ['name' => 'grades.finalize', 'label' => 'Finalize Grades', 'group' => 'grading', 'description' => 'Finalize attempts after all required grading is complete.'],
            ['name' => 'grades.regrade', 'label' => 'Regrade Answers', 'group' => 'grading', 'description' => 'Reopen finalized marks through an audited correction flow.'],
            ['name' => 'results.view', 'label' => 'View Results', 'group' => 'results', 'description' => 'View released and unreleased result reports.'],
            ['name' => 'results.release', 'label' => 'Release Results', 'group' => 'results', 'description' => 'Release scores, responses, feedback, and answer keys according to policy.'],
            ['name' => 'results.export', 'label' => 'Export Results', 'group' => 'results', 'description' => 'Export results and candidate responses.'],
            ['name' => 'audit.view', 'label' => 'View Audit Logs', 'group' => 'audit', 'description' => 'View exam, attempt, grading, and system audit logs.'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array
    {
        return [
            'system-admin' => array_column(self::all(), 'name'),
            'exam-officer' => [
                'users.manage',
                'academic.manage',
                'questions.view',
                'questions.review',
                'exams.view',
                'exams.approve',
                'exams.publish',
                'exams.close',
                'attempts.invigilate',
                'attempts.reopen',
                'attempts.invalidate',
                'grades.view',
                'grades.finalize',
                'grades.regrade',
                'results.view',
                'results.release',
                'results.export',
                'audit.view',
            ],
            'question-author' => [
                'questions.view',
                'questions.create',
                'questions.update',
                'exams.view',
                'exams.create',
                'exams.update',
                'exams.submit',
            ],
            'invigilator' => [
                'exams.view',
                'attempts.invigilate',
                'attempts.reopen',
                'attempts.invalidate',
            ],
            'grader' => [
                'questions.view',
                'exams.view',
                'grades.view',
                'grades.grade',
            ],
            'report-viewer' => [
                'results.view',
                'results.export',
            ],
            'student' => [
                'attempts.take',
            ],
        ];
    }
}
