<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Free Wi-Fi Feedback')</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <div class="flex min-h-full flex-col">
        <header class="border-b border-slate-200 bg-white px-4 py-3">
            <p class="text-sm font-semibold tracking-tight">DICT Free Wi-Fi Feedback</p>
        </header>

        <main class="mx-auto w-full max-w-xl flex-1 px-4 py-6">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white px-4 py-4 text-xs text-slate-600">
            <p>Anonymous feedback. No account, no name, no phone number collected.</p>
        </footer>
    </div>
</body>
</html>