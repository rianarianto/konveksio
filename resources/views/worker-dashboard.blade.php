<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Tugas - Konveksio</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="antialiased">
    @livewire('worker-dashboard', ['token' => $token])
    @livewireScripts
</body>
</html>
