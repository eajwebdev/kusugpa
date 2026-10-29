<!-- resources/views/layouts/navigation.blade.php -->
<header class="kp-topbar">
    <div class="kp-topbar-inner">
        <!-- Left: menu toggles + page identity -->
        <div class="flex min-w-0 items-center gap-2">
            <button @click="sidebarOpen = !sidebarOpen" type="button" class="kp-icon-btn lg:hidden"
                :aria-expanded="sidebarOpen.toString()" aria-controls="main-content">
                <span class="sr-only">Open sidebar</span>
                <i class="fas fa-bars text-lg" aria-hidden="true"></i>
            </button>
            <button @click="collapsed = !collapsed" type="button" class="kp-icon-btn hidden lg:inline-grid"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <i class="fas fa-angles-left text-sm transition-transform duration-200"
                    :class="{ 'rotate-180': collapsed }" aria-hidden="true"></i>
            </button>

            <div class="ml-1 min-w-0 border-l pl-4" style="border-color: var(--kp-border)">
                <p class="kp-crumb-eyebrow hidden sm:block">Sugarcane Crop Management &amp; Recording System</p>
                <p class="kp-crumb-title truncate">@hasSection('title')@yield('title')@else Dashboard @endif</p>
            </div>
        </div>

        @include('layouts.partials.manual-help-button')
    </div>
</header>

<script>
    function clockData() {
        return {
            timeFormatted: '',
            dateFormatted: '',
            dayName: '',

            startClock() {
                this.updateClock();
                setInterval(() => {
                    this.updateClock();
                }, 1000);
            },

            updateClock() {
                const now = new Date();
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');

                this.timeFormatted = `${hours}:${minutes}:${seconds}`;
                this.dateFormatted = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
                this.dayName = days[now.getDay()];
            }
        }
    }
</script>
