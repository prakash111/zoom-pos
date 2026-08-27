with open('resources/views/layouts/tenant.blade.php', 'r') as f:
    content = f.read()

# 1. Update <aside x-ref="dockNavEl"
old_aside = """        <aside x-ref="dockNavEl"
               x-show="layout === 'slim' || layout === 'expanded'\""""
new_aside = """        <aside x-ref="dockNavEl"
               x-show="layout === 'slim' || layout === 'expanded'"
               @mouseenter="pauseAutoScroll()"
               @mouseleave="resumeAutoScroll()"
               @focusin="pauseAutoScroll()"
               @focusout="resumeAutoScroll()\""""
content = content.replace(old_aside, new_aside)

# 2. Update Slim Mode Container with Left/Right micro arrows and scroll container
old_slim_start = """            <!-- Navigation Items Container (Slim Mode) -->
            <div x-show="layout === 'slim'"
                 :class="{
                     'w-full flex flex-col items-center gap-4 sm:gap-5 my-auto': position === 'left' || position === 'right',
                     'flex-1 flex flex-row items-center justify-center gap-1.5 sm:gap-2.5 overflow-x-auto no-scrollbar mx-2 py-1': position === 'top' || position === 'bottom',
                     'flex flex-row sm:flex-col items-center gap-2': position === 'floating'
                 }">"""

new_slim_start = """            <!-- Navigation Items Container (Slim Mode) -->
            <div x-show="layout === 'slim'"
                 class="relative flex items-center"
                 :class="{
                     'w-full flex-col my-auto': position === 'left' || position === 'right',
                     'flex-1 flex-row mx-1 sm:mx-2 min-w-0': position === 'top' || position === 'bottom',
                     'flex-row sm:flex-col': position === 'floating'
                 }">
                
                <!-- Left Micro-Scroll Arrow -->
                <button type="button"
                        x-show="position === 'top' || position === 'bottom'"
                        @click="scrollNav(-140)"
                        class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/25 text-white text-xs font-black shrink-0 flex items-center justify-center mr-1 transition active:scale-90 cursor-pointer shadow-xs"
                        title="{{ __('Scroll Left') }}">
                    ‹
                </button>

                <div data-dock-scroll-container
                     x-ref="scrollNavContainer"
                     @mouseenter="pauseAutoScroll()"
                     @mouseleave="resumeAutoScroll()"
                     @focusin="pauseAutoScroll()"
                     @focusout="resumeAutoScroll()"
                     :class="{
                         'w-full flex flex-col items-center gap-4 sm:gap-5 my-auto': position === 'left' || position === 'right',
                         'flex-1 flex flex-row items-center justify-start sm:justify-center gap-1.5 sm:gap-2.5 overflow-x-auto no-scrollbar py-1 scroll-smooth': position === 'top' || position === 'bottom',
                         'flex flex-row sm:flex-col items-center gap-2': position === 'floating'
                     }">"""
content = content.replace(old_slim_start, new_slim_start)

old_slim_end = """                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Billing & Plans') }}</span>
                </a>
            </div>"""

new_slim_end = """                    <span :class="(position === 'left' || position === 'right') ? 'vertical-rail-label text-[10px] sm:text-[11px]' : 'text-xs whitespace-nowrap font-bold'">{{ __('Billing & Plans') }}</span>
                </a>
                </div>

                <!-- Right Micro-Scroll Arrow -->
                <button type="button"
                        x-show="position === 'top' || position === 'bottom'"
                        @click="scrollNav(140)"
                        class="w-6 h-6 rounded-lg bg-white/10 hover:bg-white/25 text-white text-xs font-black shrink-0 flex items-center justify-center ml-1 transition active:scale-90 cursor-pointer shadow-xs"
                        title="{{ __('Scroll Right') }}">
                    ›
                </button>
            </div>"""
content = content.replace(old_slim_end, new_slim_end)

# 3. Update macOS Dock style & event listeners
old_macos_nav = """        <nav x-show="layout === 'macos-dock'"
             x-cloak
             :style="customBg ? () : ''"
             :class="position === 'top' ? 'top-4 sm:top-6' : 'bottom-4 sm:bottom-6'\""""

new_macos_nav = """        <nav x-show="layout === 'macos-dock'"
             x-cloak
             @mouseenter="pauseAutoScroll()"
             @mouseleave="resumeAutoScroll()"
             @focusin="pauseAutoScroll()"
             @focusout="resumeAutoScroll()"
             :style="customBg ? ('background: ' + customBg + ' !important;') : ''"
             :class="position === 'top' ? 'top-4 sm:top-6' : 'bottom-4 sm:bottom-6'\""""
content = content.replace(old_macos_nav, new_macos_nav)

# 4. Update Speed-Dial tray style
old_speed_dial = """            <!-- Speed Dial Expanded Tray -->
            <div x-show="speedDialOpen"
                 x-cloak
                 :style="customBg ? () : ''\""""

new_speed_dial = """            <!-- Speed Dial Expanded Tray -->
            <div x-show="speedDialOpen"
                 x-cloak
                 :style="customBg ? ('background: ' + customBg + ' !important;') : ''\""""
content = content.replace(old_speed_dial, new_speed_dial)

with open('resources/views/layouts/tenant.blade.php', 'w') as f:
    f.write(content)

print('Updated tenant.blade.php with auto-scroll carousel & micro arrows successfully.')
