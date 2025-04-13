const USER_KEY = 'user';
const ACCESS_TOKEN_KEY = 'access_token';

export function getUser() {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null');
}

export function setUser(user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function removeUser() {
    localStorage.removeItem(USER_KEY);
}

export function getAccessToken() {
    return localStorage.getItem(ACCESS_TOKEN_KEY);
}

export function setAccessToken(token) {
    localStorage.setItem(ACCESS_TOKEN_KEY, token);
}

export function removeAccessToken() {
    localStorage.removeItem(ACCESS_TOKEN_KEY);
}
