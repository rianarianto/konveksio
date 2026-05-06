<style>
    /* Force Sidebar/Topbar Toggle Button Border */
    .fi-sidebar-header button.fi-sidebar-open-collapse-sidebar-btn,
    .fi-sidebar-header button.fi-sidebar-close-collapse-sidebar-btn,
    .fi-topbar-start button.fi-topbar-open-collapse-sidebar-btn,
    .fi-topbar-start button.fi-topbar-close-collapse-sidebar-btn,
    button[class*="collapse-sidebar-btn"] {
        border: 1px solid #d1d5db !important;
        /* Visible gray border */
        background-color: white !important;
        border-radius: 8px !important;
        width: 38px !important;
        height: 38px !important;
        /* Do NOT force display: flex !important here to allow system to hide it */
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: #4b5563 !important;
        box-shadow: none !important;
        margin-right: 12px !important;
        /* Spacing from logo */
    }

    button[class*="collapse-sidebar-btn"][style*="display: none"] {
        display: none !important;
    }

    .dark .fi-sidebar-header button.fi-sidebar-open-collapse-sidebar-btn,
    .dark .fi-sidebar-header button.fi-sidebar-close-collapse-sidebar-btn,
    .dark .fi-topbar-start button.fi-topbar-open-collapse-sidebar-btn,
    .dark .fi-topbar-start button.fi-topbar-close-collapse-sidebar-btn,
    .dark button[class*="collapse-sidebar-btn"] {
        border-color: #4b5563 !important;
        background-color: #1f2937 !important;
        color: #d1d5db !important;
    }

    /* Flip Layout: Move button to the right */
    .fi-sidebar-header,
    .fi-topbar-start {
        flex-direction: row-reverse !important;
        justify-content: flex-end !important;
        align-items: center !important; /* Center vertically (Rata Middle) */
        gap: 16px !important;
    }

    .fi-sidebar-header-logo-ctn,
    .fi-topbar-start > a,
    .fi-logo {
        margin-right: auto !important;
        /* Pushes logo away from button in reverse layout */
    }
</style>

<div class="flex items-center gap-2 mt-1">
    <img src="{{ asset('images/logo.jpeg') }}" alt="Logo" class="h-10 w-auto rounded-md object-contain" />
    <div x-show="$store.sidebar.isOpen" class="flex flex-col text-left leading-none transition-all duration-300"
        style="margin-top: 2px;">
        <span class="text-[16px] font-bold text-gray-800 dark:text-gray-100 tracking-tight whitespace-nowrap"
            style="line-height: 1;">Dunia Bordir Komputer</span>
    </div>
</div>