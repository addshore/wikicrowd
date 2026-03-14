window._ = require('lodash');

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const getApiUserAgent = () => {
	if (typeof window !== 'undefined' && typeof window.apiUserAgent === 'string' && window.apiUserAgent.trim()) {
		return window.apiUserAgent;
	}

	return 'Addbot';
};

if (typeof window !== 'undefined' && typeof window.fetch === 'function') {
	const originalFetch = window.fetch.bind(window);

	window.fetch = (input, init = {}) => {
		const requestUrl = typeof input === 'string' ? input : input?.url;

		const headers = new Headers(
			init.headers || (typeof Request !== 'undefined' && input instanceof Request ? input.headers : undefined)
		);
		headers.set('Api-User-Agent', getApiUserAgent());

		return originalFetch(input, {
			...init,
			headers
		});
	};
}

if (window.axios?.interceptors?.request) {
	window.axios.interceptors.request.use((config) => {
		if (shouldAddApiUserAgentHeader(config?.url)) {
			if (!config.headers) {
				config.headers = {};
			}

			config.headers['Api-User-Agent'] = getApiUserAgent();
		}

		return config;
	});
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// window.Pusher = require('pusher-js');

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: process.env.MIX_PUSHER_APP_KEY,
//     cluster: process.env.MIX_PUSHER_APP_CLUSTER,
//     forceTLS: true
// });
