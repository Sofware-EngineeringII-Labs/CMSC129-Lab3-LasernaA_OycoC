<x-layout title="Archived Tasks" mainClass="max-w-[80rem] mx-auto mt-6 px-4">
    <div class="mt-6 text-white">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-bold text-lg">Archived Tasks</h2>
                <p class="text-sm text-gray-300 mt-1">Soft-deleted tasks can be restored or permanently deleted.</p>
            </div>
            <a href="/tasks" class="btn btn-sm btn-outline">Back to Board</a>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($archivedTasks as $task)
                <div class="card bg-base-200 text-base-content border border-base-300">
                    <div class="card-body">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="card-title text-base">{{ $task->title }}</h3>
                            <span class="badge badge-outline">{{ str_replace('_', ' ', $task->status) }}</span>
                        </div>

                        <p class="text-sm opacity-80">{{ \Illuminate\Support\Str::limit($task->description, 150) }}</p>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="badge badge-outline">{{ $task->priority }}</span>
                            @if ($task->due_date)
                                <span class="badge badge-outline">Due {{ $task->due_date->format('M d, Y') }}</span>
                            @endif
                            @if ($task->deleted_at)
                                <span class="badge badge-outline">Archived {{ $task->deleted_at->diffForHumans() }}</span>
                            @endif
                        </div>

                        <div class="mt-4 flex gap-2">
                            <form method="POST" action="/tasks/{{ $task->id }}/restore">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-primary">Restore</button>
                            </form>

                            <form method="POST" action="/tasks/{{ $task->id }}/force-delete"
                                onsubmit="return confirm('Permanently delete this task? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">Delete Permanently</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-base-300 p-4 text-sm text-gray-300">
                    No archived tasks yet.
                </div>
            @endforelse
        </div>
    </div>
</x-layout>
