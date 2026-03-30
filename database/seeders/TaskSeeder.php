<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $statuses = ['backlog', 'todo', 'in_progress', 'done'];
        $priorities = ['low', 'medium', 'high'];

        $taskTitles = [
            'Refactor auth middleware',
            'Design task search endpoint',
            'Fix overdue status edge case',
            'Write board filtering tests',
            'Review archived task UX',
            'Optimize dashboard queries',
            'Prepare release notes',
            'Audit policy permissions',
            'Improve error handling on forms',
            'Implement activity logging',
        ];

        User::query()->each(function (User $user) use ($statuses, $priorities, $taskTitles): void {
            for ($i = 0; $i < 30; $i++) {
                $status = fake()->randomElement($statuses);

                // Make due dates diverse so search/filter and overdue logic are easy to test.
                $dueDate = fake()->randomElement([
                    null,
                    now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                    now()->addDays(fake()->numberBetween(1, 20))->toDateString(),
                ]);

                $task = $user->tasks()->create([
                    'title' => fake()->randomElement($taskTitles),
                    'description' => fake()->sentence(16),
                    'due_date' => $dueDate,
                    'status' => $status,
                    'priority' => fake()->randomElement($priorities),
                    'position' => $i,
                ]);

                // Keep a few archived rows for archive/restore testing.
                if (fake()->boolean(12)) {
                    $task->delete();
                }
            }
        });
    }
}
