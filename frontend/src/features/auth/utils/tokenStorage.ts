const AUTH_TOKEN_KEY = 'prflow_auth_token';

// Centralizing token storage keeps auth persistence consistent across
// context, interceptor, and future modules without duplicating localStorage logic.
export function getStoredToken(): string | null {
  return localStorage.getItem(AUTH_TOKEN_KEY);
}

export function setStoredToken(token: string): void {
  localStorage.setItem(AUTH_TOKEN_KEY, token);
}

export function clearStoredToken(): void {
  localStorage.removeItem(AUTH_TOKEN_KEY);
}
