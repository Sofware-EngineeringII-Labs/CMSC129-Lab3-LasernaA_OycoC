<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Http\Requests\TaskRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    private const STATUS_ORDER = ['backlog', 'todo', 'in_progress', 'done'];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->syncOverdueTasksToBacklog();

        $statusFilterRaw = request('status', 'all');
        $statusFilter = is_string($statusFilterRaw) ? $statusFilterRaw : 'all';

        if ($statusFilter !== 'all' && !in_array($statusFilter, self::STATUS_ORDER, true)) {
            $statusFilter = 'all';
        }

        $tasks = Auth::user()
            ->tasks()
            ->orderByRaw("CASE status WHEN 'backlog' THEN 1 WHEN 'todo' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'done' THEN 4 ELSE 5 END")
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        $tasksByStatus = [
            'backlog' => $tasks->where('status', 'backlog')->values(),
            'todo' => $tasks->where('status', 'todo')->values(),
            'in_progress' => $tasks->where('status', 'in_progress')->values(),
            'done' => $tasks->where('status', 'done')->values(),
        ];

        return view('tasks.index', [
            'tasksByStatus' => $tasksByStatus,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * Display archived tasks.
     */
    public function archived()
    {
        $archivedTasks = Auth::user()
            ->tasks()
            ->onlyTrashed()
            ->latest('deleted_at')
            ->get();

        return view('tasks.archived', [
            'archivedTasks' => $archivedTasks,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('tasks.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaskRequest $request)
    {
        $validated = $request->validated();

        $validated = $this->applyAutomaticBacklogRules($validated);

        Auth::user()->tasks()->create($validated);

        return redirect('/tasks');
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        Gate::authorize('update', $task);

        return view('tasks.show', [
            'task' => $task
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', [
            'task' => $task
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TaskRequest $request, Task $task)
    {
        Gate::authorize('update', $task);

        $validated = $request->validated();

        $validated = $this->applyAutomaticBacklogRules($validated, $task);

        $task->update($validated);

        return redirect("/tasks/{$task->id}");
    }

    /**
     * Move task to the previous status column.
     */
    public function moveLeft(Task $task)
    {
        Gate::authorize('update', $task);

        $currentIndex = array_search($task->status, self::STATUS_ORDER, true);

        if ($currentIndex === false || $currentIndex === 0) {
            return redirect('/tasks');
        }

        $newStatus = self::STATUS_ORDER[$currentIndex - 1];

        $task->update([
            'status' => $newStatus,
            'position' => $this->nextPosition($newStatus),
        ]);

        return redirect('/tasks');
    }

    /**
     * Move task to the next status column.
     */
    public function moveRight(Task $task)
    {
        Gate::authorize('update', $task);

        $currentIndex = array_search($task->status, self::STATUS_ORDER, true);

        if ($currentIndex === false || $currentIndex === count(self::STATUS_ORDER) - 1) {
            return redirect('/tasks');
        }

        $newStatus = self::STATUS_ORDER[$currentIndex + 1];

        $task->update([
            'status' => $newStatus,
            'position' => $this->nextPosition($newStatus),
        ]);

        return redirect('/tasks');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        Gate::authorize('update', $task);

        $task->delete();

        return redirect('/tasks');
    }

    /**
     * Restore a previously archived task.
     */
    public function restore(int $taskId)
    {
        /** @var \App\Models\User $user */
        $task = Auth::user()
            ->tasks()
            ->onlyTrashed()
            ->findOrFail($taskId);

        $task->restore();

        return redirect('/tasks/archived');
    }

    /**
     * Permanently delete an archived task.
     */
    public function forceDelete(int $taskId)
    {
        /** @var \App\Models\User $user */
        $task = Auth::user()
            ->tasks()
            ->onlyTrashed()
            ->findOrFail($taskId);

        $task->forceDelete();

        return redirect('/tasks/archived');
    }

    private function nextPosition(string $status): int
    {
        $maxPosition = Auth::user()
            ->tasks()
            ->where('status', $status)
            ->max('position');

        return $maxPosition === null ? 0 : $maxPosition + 1;
    }

    private function applyAutomaticBacklogRules(array $validated, ?Task $existingTask = null): array
    {
        $status = $validated['status'];
        $originalStatus = $existingTask?->status;

        if ($this->isPastDue($validated['due_date']) && $status !== 'done') {
            $status = 'backlog';
            $validated['status'] = $status;
        }

        if ($originalStatus !== $status) {
            $validated['position'] = $this->nextPosition($status);
        }

        return $validated;
    }

    private function syncOverdueTasksToBacklog(): void
    {
        $overdueTasks = Auth::user()
            ->tasks()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['backlog', 'done'])
            ->orderBy('due_date')
            ->orderBy('position')
            ->get();

        if ($overdueTasks->isEmpty()) {
            return;
        }

        $nextBacklogPosition = $this->nextPosition('backlog');

        foreach ($overdueTasks as $task) {
            $task->update([
                'status' => 'backlog',
                'position' => $nextBacklogPosition,
            ]);

            $nextBacklogPosition++;
        }
    }

    private function isPastDue(?string $dueDate): bool
    {
        if ($dueDate === null || $dueDate === '') {
            return false;
        }

        return Carbon::parse($dueDate)->isBefore(now()->startOfDay());
    }
}
