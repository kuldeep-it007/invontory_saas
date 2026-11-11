/**
 * Client-side Sanctum token storage.
 *
 * The token is a bearer credential issued by `POST /api/login`. We persist it in
 * localStorage so a browser refresh keeps the session, and mirror it in memory
 * for synchronous reads. All access is guarded for SSR (no `window`).
 */

const TOKEN_KEY = "idp.auth.token";

let memoryToken: string | null = null;

/** Read the stored bearer token, or null when unauthenticated / on the server. */
export function getToken(): string | null {
  if (memoryToken !== null) return memoryToken;
  if (typeof window === "undefined") return null;
  memoryToken = window.localStorage.getItem(TOKEN_KEY);
  return memoryToken;
}

/** Persist (or clear) the bearer token. */
export function setToken(token: string | null): void {
  memoryToken = token;
  if (typeof window === "undefined") return;
  if (token) {
    window.localStorage.setItem(TOKEN_KEY, token);
  } else {
    window.localStorage.removeItem(TOKEN_KEY);
  }
}

export function clearToken(): void {
  setToken(null);
}
