<x-layout title="Task Board" mainClass="max-w-[95rem] mx-auto mt-6 px-4">
    @php
        $columns = [
            'backlog' => 'Backlog',
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'done' => 'Done',
        ];

        $statusFilter = isset($statusFilter) && is_string($statusFilter) ? $statusFilter : 'all';
        $priorityFilter = isset($priorityFilter) && is_string($priorityFilter) ? $priorityFilter : 'all';

        if ($statusFilter !== 'all' && !array_key_exists($statusFilter, $columns)) {
            $statusFilter = 'all';
        }

        $priorityOptions = [
            'all' => 'All Priorities',
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ];

        if (!array_key_exists($priorityFilter, $priorityOptions)) {
            $priorityFilter = 'all';
        }

        $paginatorsByStatus = $paginatorsByStatus ?? [];

        $filterLabel = $statusFilter === 'all' ? 'All Tasks' : ($columns[$statusFilter] ?? 'All Tasks');
        $visibleColumns = $statusFilter === 'all' ? $columns : [$statusFilter => ($columns[$statusFilter] ?? 'Tasks')];

        $visiblePageCount = 0;
        $visibleTotalCount = 0;

        foreach ($visibleColumns as $statusKey => $statusLabel) {
            $visiblePageCount += ($tasksByStatus[$statusKey] ?? collect())->count();
            $visibleTotalCount += ($paginatorsByStatus[$statusKey]->total() ?? 0);
        }
    @endphp

    <div class="mt-6 text-white">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-bold text-lg">Your Task Board</h2>
                <p class="text-sm text-gray-300 mt-1">Viewing: {{ $filterLabel }}</p>
            </div>
            <a href="/tasks/create" class="btn btn-sm btn-primary">New Task</a>
        </div>

        <div class="mt-4 rounded-lg bg-base-200 p-4">
            <form method="GET" action="/tasks" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
                <div class="xl:col-span-2">
                    <label for="q" class="block text-sm font-medium mb-1">Search</label>
                    <input
                        id="q"
                        name="q"
                        type="text"
                        value="{{ $searchTerm ?? '' }}"
                        placeholder="Search title, description, status, priority"
                        class="input input-bordered w-full"
                    />
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium mb-1">Status</label>
                    <select id="status" name="status" class="select select-bordered w-full">
                        <option value="all" @selected($statusFilter === 'all')>All Statuses</option>
                        @foreach ($columns as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected($statusFilter === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="priority" class="block text-sm font-medium mb-1">Priority</label>
                    <select id="priority" name="priority" class="select select-bordered w-full">
                        @foreach ($priorityOptions as $priorityKey => $priorityLabel)
                            <option value="{{ $priorityKey }}" @selected($priorityFilter === $priorityKey)>{{ $priorityLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="due_from" class="block text-sm font-medium mb-1">Due From</label>
                    <input id="due_from" name="due_from" type="date" value="{{ $dueFrom ?? '' }}" class="input input-bordered w-full" />
                </div>

                <div>
                    <label for="due_to" class="block text-sm font-medium mb-1">Due To</label>
                    <input id="due_to" name="due_to" type="date" value="{{ $dueTo ?? '' }}" class="input input-bordered w-full" />
                </div>

                <div class="xl:col-span-6 flex gap-2 mt-1">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="/tasks" class="btn btn-sm btn-ghost">Clear</a>
                </div>
            </form>
        </div>

        <div class="mt-3 text-sm text-gray-300">
            Showing {{ $visiblePageCount }} of {{ $visibleTotalCount }} tasks
        </div>

        <div class="mt-6 grid grid-cols-1 {{ $statusFilter === 'all' ? 'lg:grid-cols-4' : 'lg:grid-cols-1' }} gap-4">
            @foreach ($visibleColumns as $statusKey => $statusLabel)
                @php
                    $columnTasks = $tasksByStatus[$statusKey] ?? collect();
                    $columnPaginator = $paginatorsByStatus[$statusKey] ?? null;
                @endphp

                <section class="rounded-lg bg-base-200 p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">{{ $statusLabel }}</h3>
                        <span class="badge badge-outline">{{ $columnPaginator?->total() ?? $columnTasks->count() }}</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($columnTasks as $task)
                            <x-task-card href="/tasks/{{ $task->id }}">
                                <div class="font-semibold">{{ $task->title }}</div>
                                <p class="mt-2 text-sm opacity-90">{{ \Illuminate\Support\Str::limit($task->description, 110) }}</p>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span class="badge badge-outline">{{ $task->priority }}</span>
                                    @if ($task->due_date)
                                        <span class="badge badge-outline">Due {{ $task->due_date->format('M d') }}</span>
                                    @endif
                                </div>

                                <div class="mt-3 flex gap-2">
                                    @if ($task->status !== 'backlog')
                                        <form method="POST" action="/tasks/{{ $task->id }}/move-left">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-xs btn-outline">Left</button>
                                        </form>
                                    @endif

                                    @if ($task->status !== 'done')
                                        <form method="POST" action="/tasks/{{ $task->id }}/move-right">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-xs btn-outline">Right</button>
                                        </form>
                                    @endif
                                </div>
                            </x-task-card>
                        @empty
                            <div class="rounded-lg border border-base-300 p-3 text-sm text-gray-300">
                                No tasks in this column.
                            </div>
                        @endforelse
                    </div>

                    @if ($columnPaginator && $columnPaginator->hasPages())
                        <div class="mt-4">
                            {{ $columnPaginator->onEachSide(1)->links() }}
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

    </div>
</x-layout>