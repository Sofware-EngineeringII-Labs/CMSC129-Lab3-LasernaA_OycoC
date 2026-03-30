<div class="navbar bg-base-200 shadow-sm">
    <div class="navbar-start">
        <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-ghost lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                </svg>
            </div>
            <ul tabindex="-1" class="menu menu-sm dropdown-content bg-base-100 rounded-box z-1 mt-3 w-52 p-2 shadow">
                <li><a href="/tasks">Home</a></li>
                <li><a href="/tasks/create">New Task</a></li>
                <li><a href="/tasks/archived">Archived</a></li>
                @can('view-admin')
                    <li><a href="/admin">Admin</a></li>
                @endcan
            </ul>
        </div>
        <a class="btn btn-ghost text-xl">Task Manager</a>
    </div>
    <div class="navbar-center hidden lg:flex">
        <ul class="menu menu-horizontal px-1">
            <li><a href="/tasks">Home</a></li>
            <li><a href="/tasks/create">New Task</a></li>
            <li><a href="/tasks/archived">Archived</a></li>
            @can('view-admin')
                <li><a href="/admin">Admin</a></li>
            @endcan
        </ul>
    </div>
    <div class="navbar-end">
        @guest
            <a class="btn btn-primary" href="/register">Register</a>
            <a class="btn btn-outline" href="/login">Login</a>
        @endguest

        @auth
            <form method="POST" action="/logout">
                @csrf
                @method('DELETE')
                <button class="btn btn-ghost">Log Out</button>
            </form>
        @endauth
    </div>
</div>
