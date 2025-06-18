@extends('layouts.app')

@section('title', 'Service Temporarily Unavailable')

@section('content')
<div class="container mx-auto px-4 py-8 text-center">
    <div class="max-w-md mx-auto">
        <div class="mb-8">
            <h1 class="text-6xl font-bold text-gray-400 mb-4">503</h1>
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">Service Temporarily Unavailable</h2>
            <p class="text-gray-600 mb-6">
                We're experiencing some technical difficulties. Please try again in a few moments.
            </p>
        </div>
        
        <div class="space-y-4">
            <button 
                onclick="window.location.reload()" 
                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-200"
            >
                Try Again
            </button>
            
            <div>
                <a href="{{ route('home') }}" class="text-blue-500 hover:text-blue-600 underline">
                    Go to Homepage
                </a>
            </div>
        </div>
        
        <div class="mt-8 text-sm text-gray-500">
            <p>If this problem persists, please contact support.</p>
        </div>
    </div>
</div>

<script>
    // Auto-refresh after 30 seconds
    setTimeout(function() {
        window.location.reload();
    }, 30000);
</script>
@endsection
