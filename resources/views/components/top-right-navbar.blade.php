<div class="fixed top-0 inset-x-0 px-4 py-3 sm:px-6 sm:py-4 flex items-center justify-between sm:justify-end bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm sm:shadow-md" style="z-index: 9999;">
    <!-- Hamburger button -->
    <div class="sm:hidden">
        <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:hidden" aria-label="Toggle menu" aria-expanded="false" aria-controls="menu-items-wrapper">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Menu items wrapper -->
    <div id="menu-items-wrapper" class="block hidden absolute top-full right-0 mt-1 w-56 rounded-md shadow-lg py-1 bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 focus:outline-none sm:relative sm:top-auto sm:right-auto sm:left-auto sm:mt-0 sm:w-auto sm:shadow-none sm:bg-transparent sm:dark:bg-transparent sm:ring-0 sm:flex sm:items-center sm:py-0 sm:space-x-4">
        @auth
            <span class="block px-4 py-2 text-sm text-gray-800 dark:text-gray-300 sm:text-gray-800 sm:dark:text-gray-400 sm:px-0 sm:py-0">Hello, {{ Auth::user()->username }}!</span>
        @endauth
        <a href="{{ url('/') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:text-gray-700 sm:dark:text-gray-500 sm:hover:bg-transparent sm:dark:hover:bg-transparent sm:underline">Home</a>
        @auth
            <a href="{{ route('logout') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:text-gray-700 sm:dark:text-gray-500 sm:hover:bg-transparent sm:dark:hover:bg-transparent sm:underline">Logout</a>
        @else
            <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:text-gray-700 sm:dark:text-gray-500 sm:hover:bg-transparent sm:dark:hover:bg-transparent sm:underline">Login</a>
        @endauth
        <button id="dark-mode-toggle" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white sm:ml-2 sm:w-auto sm:text-xs sm:px-2 sm:py-1 sm:rounded sm:border sm:text-gray-700 sm:dark:text-gray-500" onclick="toggleDarkMode()" title="Toggle dark mode">🌓 Mode Toggle</button>
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
