<div class="fixed top-0 right-0 p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg" style="z-index: 50;">
    <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center p-1 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:hidden" aria-label="Toggle menu" aria-expanded="false" aria-controls="menu-items-wrapper">
        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <!-- Menu items wrapper -->
    <div id="menu-items-wrapper" class="block hidden top-0 right-0 rounded-md shadow-xl px-3 py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none sm:flex sm:items-center sm:space-x-3 z-50">
        @auth
            <span class="block px-4 py-2 text-sm text-gray-800 dark:text-gray-300 sm:text-gray-800 sm:dark:text-gray-400 sm:px-0 sm:py-0">Hello, {{ Auth::user()->username }}!</span>
        @endauth
        <a href="{{ url('/') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:text-gray-700 sm:dark:text-gray-500 sm:hover:bg-transparent sm:dark:hover:bg-transparent sm:underline">Home</a>
        @auth
            <a href="{{ route('logout') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:text-gray-700 sm:dark:text-gray-500 sm:hover:bg-transparent sm:dark:hover:bg-transparent sm:underline">Logout</a>
        @else
            <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:text-gray-700 sm:dark:text-gray-500 sm:hover:bg-transparent sm:dark:hover:bg-transparent sm:underline">Login</a>
        @endauth
        <button id="dark-mode-toggle" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:ml-2 sm:w-auto sm:text-base sm:p-1 sm:rounded-md sm:border-0 sm:text-gray-700 sm:dark:text-gray-300 hover:sm:bg-gray-100 dark:hover:sm:bg-gray-700" onclick="toggleDarkMode()" title="Toggle dark mode"><span class="sm:hidden">🌓 Mode Toggle</span><span class="hidden sm:inline">🌓</span></button>
    </div>

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

        // New mobile menu toggle functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const menuItemsWrapper = document.getElementById('menu-items-wrapper');

        if (mobileMenuButton && menuItemsWrapper) {
            mobileMenuButton.addEventListener('click', function() {
                menuItemsWrapper.classList.toggle('hidden');
                const isHidden = menuItemsWrapper.classList.contains('hidden');
                mobileMenuButton.setAttribute('aria-expanded', String(!isHidden)); // Ensure string value
            });
        }
    </script>
</div>
