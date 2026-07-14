@if(auth()->check())
    <div class="hidden md:flex flex-col text-right mr-3" style="line-height: 1.25; align-items: flex-end; justify-content: center; margin-right: 12px;">
        <span style="font-size: 11px; font-weight: 600; color: #6b7280;">
            Hi, {{ auth()->user()->name }}
        </span>
        <span style="font-size: 12px; font-weight: 800; color: #1f2937; letter-spacing: -0.01em;">
            {{ strtoupper(auth()->user()->role) }} - {{ \Filament\Facades\Filament::getTenant()?->name ?? 'Toko' }}
        </span>
    </div>
@endif
