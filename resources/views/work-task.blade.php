<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas Produksi — Dunia Bordir Komputer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rethink+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        :root {
            --purple-50: #F2E6FF;
            --purple-100: #E6CCFF;
            --purple-200: #CC99FF;
            --purple-400: #9933FF;
            --purple-500: #8000FF;
            --purple-600: #6600CC;
            --purple-700: #4D0099;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Rethink Sans', ui-sans-serif, system-ui, sans-serif;
            font-variant-ligatures: none;
            font-feature-settings: "liga" 0, "clig" 0;
            background: var(--gray-50);
            min-height: 100vh;
            color: var(--gray-900);
            -webkit-font-smoothing: antialiased;
        }

        /* Scrollbar tipis */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--purple-200); border-radius: 4px; }
    </style>
</head>
<body>
    @livewire('tugas-tukang', ['woId' => $woId, 'token' => $token])

    @livewireScripts
</body>
</html>
