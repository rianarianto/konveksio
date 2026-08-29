<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $workerShop = \App\Models\Worker::where('portal_token', $token ?? '')->with('shop')->first()?->shop?->name ?? 'Konveksio';
    @endphp
    <title>Keuangan Saya — {{ $workerShop }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rethink+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    @livewire('worker-finance', ['token' => $token])
    @livewireScripts
</body>
</html>
