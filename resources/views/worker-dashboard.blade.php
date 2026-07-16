<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Tugas - Konveksio</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <style>
        body {
            font-variant-ligatures: none !important;
            font-feature-settings: "liga" 0, "clig" 0 !important;
        }
    </style>
</head>
<body class="antialiased">
    @livewire('worker-dashboard', ['token' => $token])
    @livewireScripts
</body>
</html>
