<div class="hidden fixed top-0 right-0 px-6 py-4 sm:block" style="z-index: 9999;">
    @auth
        <span class="text-sm text-gray-800 dark:text-gray-400">Hello, {{ Auth::user()->username }}!</span>
    @endauth
    <a href="{{ url('/') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Home</a>
    @auth
        <a href="{{ route('logout') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Logout</a>
    @else
        <a href="{{ route('login') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Login</a>
    @endauth
</div>
