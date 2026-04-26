# Task Manager (Laravel)

## Application Description and Purpose
This project is a Task Manager web application built with Laravel.

The main purpose is to manage personal tasks in a board-style workflow inspired by tools like Trello, with practical productivity features such as:
1. Multi-status task tracking (Backlog, To Do, In Progress, Done)
2. Search, filtering, and pagination
3. Automatic backlog handling for overdue tasks
4. Archive flow (soft delete, restore, permanent delete)

The app is designed for authenticated users, and each user can only manage their own tasks.

## Tech Stack
1. Laravel 12 (PHP 8.2+)
2. Blade templates
3. Tailwind CSS + DaisyUI
4. PostgreSQL (recommended for this setup guide)

## Features Implemented
1. Authentication (register, login, logout)
2. Task CRUD (create, read, update, archive)
3. Task fields:
title, description, due_date, status, priority, position
4. Board view with status columns:
Backlog, To Do, In Progress, Done
5. Left/Right status movement on cards
6. Automatic overdue logic:
past-due tasks are automatically moved to Backlog (except Done)
7. Search across multiple fields:
title, description, status, priority
8. Filters:
status, priority, due date range
9. Pagination with query-string persistence
10. Soft delete archive flow:
archive, restore, permanent delete

## AI Chatbot (Minimum Requirement)
This project now includes an inquiry-only AI chatbot integrated into authenticated pages.

Current scope for this phase:
1. Inquiry-only assistant for task questions (no CRUD actions yet)
2. Conversation persistence in the database
3. Context-aware follow-up support using previous assistant results
4. Global chat widget visible on authenticated pages

### AI Provider and Model
1. Provider: Google Gemini API
2. Model: `gemini-2.5-flash-lite`

### Environment Variables (AI)
Add these to your local `.env`:

```env
GEMINI_API_KEY=your_real_api_key_here
GEMINI_MODEL=gemini-2.5-flash-lite
GEMINI_BASE_URL=https://generativelanguage.googleapis.com
```

Important:
1. Do not commit real API keys
2. Keep keys server-side only
3. Frontend must call backend routes only

### Setup Steps for Chatbot (Minimum Phase)
1. Generate Gemini API key from Google AI Studio
2. Add the key to local `.env`
3. Run migrations for chat tables:

```bash
php artisan migrate
```

4. Start app:

```bash
composer run dev
```

### Example Inquiry Prompts
1. `Show my tasks`
2. `What tasks are due today?`
3. `Show high priority tasks`
4. `How many completed tasks do I have?`
5. `What is my oldest pending task?`

### Current Limitation (Expected in Minimum Phase)
CRUD commands are intentionally blocked in this phase. If a user asks to create, update, or delete, the chatbot responds that it is in inquiry-only mode.

## Installation and Setup Instructions
1. Clone the repository.
2. Go into the project directory.
3. Install PHP dependencies:
```bash
composer install
```
4. Install frontend dependencies:
```bash
npm install
```
5. Create environment file:
```bash
cp .env.example .env
```
6. Generate app key:
```bash
php artisan key:generate
```
7. Configure your database in `.env` (see PostgreSQL section below).
8. Run migrations:
```bash
php artisan migrate
```
9. Seed sample data:
```bash
php artisan db:seed
```
10. Run the app:
```bash
composer run dev
```

## Database Setup Guide (PostgreSQL)
### 1) Create PostgreSQL database
Use `psql` and run:
```sql
CREATE DATABASE task_manager;
CREATE USER task_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE task_manager TO task_user;
```

### 2) Configure `.env`
Set these values:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=task_manager
DB_USERNAME=task_user
DB_PASSWORD=your_secure_password
```

### 3) Run migrations and seeders
```bash
php artisan migrate
php artisan db:seed
```

## Common Laravel Commands
1. Run migrations:
```bash
php artisan migrate
```
2. Refresh database and reseed:
```bash
php artisan migrate:fresh --seed
```
3. Run tests:
```bash
php artisan test
```

## Screenshots

1. Task board view
![Task Board](docs/screenshots/task-board.png)

2. Search and filters
![Search and Filters](docs/screenshots/search-and-filters.png)

3. Archived tasks page
![Archived Tasks](docs/screenshots/archived-tasks.png)

## MVC Architecture Explanation
This project follows Laravel MVC with clear separation of concerns.

### Model Layer (`app/Models`)
```text
app/Models/
|- User.php                  # Authentication user model; owns many tasks
|- Task.php                  # Main domain model (task fields, casts, soft deletes)
```

### Controller Layer (`app/Http/Controllers`)
```text
app/Http/Controllers/
|- TaskController.php        # Task business flow (board, CRUD, move, search/filter, archive lifecycle)
|- Auth/
|  |- RegisteredUserController.php  # Registration flow
|  |- SessionsController.php        # Login/logout flow
```

### Request Validation Layer (`app/Http/Requests`)
```text
app/Http/Requests/
|- TaskRequest.php           # Input validation for create/update task operations
```

### View Layer (`resources/views`)
```text
resources/views/
|- components/
|  |- layout.blade.php       # Shared layout shell
|  |- nav.blade.php          # Main nav links
|  |- task-card.blade.php    # Reusable task card component
|- tasks/
|  |- index.blade.php        # Task board + search/filter UI + pagination
|  |- create.blade.php       # Task creation form
|  |- edit.blade.php         # Task update form + archive action
|  |- show.blade.php         # Single task details
|  |- archived.blade.php     # Archived tasks (restore/permanent delete)
```

### Routing Layer (`routes`)
```text
routes/
|- web.php                   # HTTP endpoints for auth and task flows
```

### Database Layer (`database`)
```text
database/
|- migrations/               # Schema evolution (tasks table, status fields, soft deletes)
|- seeders/
|  |- DatabaseSeeder.php     # Root seeder entrypoint
|  |- TaskSeeder.php         # Realistic task seed data for testing search/filter/archive
```

## Notes
1. All task actions are user-scoped; users can only access their own tasks.
2. Archive uses soft deletes so records are recoverable.
3. Permanent delete is available from the Archived page.
