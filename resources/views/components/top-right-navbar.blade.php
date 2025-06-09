<div class="hidden fixed top-0 right-0 px-6 py-4 sm:block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-md" style="z-index: 9999;">
    @auth
        <span class="text-sm text-gray-800 dark:text-gray-400">Hello, {{ Auth::user()->username }}!</span>
    @endauth
    <a href="{{ url('/') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Home</a>
    @auth
        <a href="{{ route('logout') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Logout</a>
    @else
        <a href="{{ route('login') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Login</a>
    @endauth
    <button id="dark-mode-toggle" class="ml-2 px-2 py-1 rounded border text-xs" onclick="toggleDarkMode()" title="Toggle dark mode">🌓</button>
    <script>
        function setDarkModeClass(isDark) {
            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark ? '1' : '0');
        }
        // On page load, set dark mode from localStorage
        (function() {
            const darkPref = localStorage.getItem('darkMode');
            if (darkPref === '1' || (darkPref === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                setDarkModeClass(true);
            } else {
                setDarkModeClass(false);
            }
        })();
    </script>
</div>
