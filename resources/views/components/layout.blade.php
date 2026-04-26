@props([
    'title' => 'sample title',
    'mainClass' => 'max-w-3xl mx-auto mt-6',
])

<!DOCTYPE html>
<html lang="en" data-theme="night">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
</head>
<body class = "">
    <x-nav/>
    <main class="{{ $mainClass }}">
        {{ $slot }}
    </main>

    @auth
        <x-chat-widget />
    @endauth
</body>
</html>