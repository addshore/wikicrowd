import { DOMAIN, AUTH_URL, CLIENT_ID, REDIRECT_URI, TOKEN_URL } from '../config/oauth';

export function generateCodeVerifier() {
    const array = new Uint8Array(32);
    window.crypto.getRandomValues(array);
    return btoa(String.fromCharCode(...array))
        .replace(/=/g, '')
        .replace(/\+/g, '-')
        .replace(/\//g, '_');
}

export async function generateCodeChallenge(codeVerifier) {
    const encoder = new TextEncoder();
    const data = encoder.encode(codeVerifier);
    const hashed = await window.crypto.subtle.digest('SHA-256', data);
    return btoa(String.fromCharCode(...new Uint8Array(hashed)))
        .replace(/=/g, '')
        .replace(/\+/g, '-')
        .replace(/\//g, '_');
}

export function redirectToAuth(codeChallenge, codeVerifier) {
    localStorage.setItem('code_verifier', codeVerifier);

    const params = new URLSearchParams({
        response_type: 'code',
        client_id: CLIENT_ID,
        redirect_uri: REDIRECT_URI,
        code_challenge: codeChallenge,
        code_challenge_method: 'S256',
    });

    window.location.href = `${AUTH_URL}?${params.toString()}`;
}

export async function fetchToken(code, codeVerifier) {
    const response = await fetch(TOKEN_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            grant_type: 'authorization_code',
            client_id: CLIENT_ID,
            code,
            redirect_uri: REDIRECT_URI,
            code_verifier: codeVerifier,
            code_challenge_method: 'S256',
        }),
    });

    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error_description || 'Failed to fetch token');
    }

    return response.json();
}

export async function fetchUserProfile(accessToken) {
    const response = await fetch(`${DOMAIN}/w/rest.php/oauth2/resource/profile`, {
        headers: {
            Authorization: `Bearer ${accessToken}`,
        },
    });

    if (!response.ok) {
        throw new Error('Failed to fetch user profile');
    }

    return response.json();
}
