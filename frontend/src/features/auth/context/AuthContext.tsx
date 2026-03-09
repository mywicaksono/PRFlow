import { createContext, useCallback, useContext, useEffect, useMemo, useState, type PropsWithChildren } from 'react';

import { fetchCurrentUser, loginRequest, logoutRequest } from '@/features/auth/api/authApi';
import type { AuthUser, LoginPayload } from '@/features/auth/types/authTypes';
import { clearStoredToken, getStoredToken, setStoredToken } from '@/features/auth/utils/tokenStorage';

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  isAuthenticated: boolean;
  isAuthLoading: boolean;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(() => getStoredToken());
  const [isAuthLoading, setIsAuthLoading] = useState(true);

  const logout = useCallback(async () => {
    try {
      if (getStoredToken()) {
        await logoutRequest();
      }
    } finally {
      // Local cleanup in finally ensures stale sessions are always cleared,
      // even when backend token revocation fails.
      clearStoredToken();
      setToken(null);
      setUser(null);
    }
  }, []);

  const login = useCallback(async (payload: LoginPayload) => {
    const loginResult = await loginRequest(payload);
    setStoredToken(loginResult.token);
    setToken(loginResult.token);

    // We fetch /auth/me after login to normalize user state from a single
    // source of truth, so future UI does not depend on login payload shape.
    const authenticatedUser = await fetchCurrentUser();
    setUser(authenticatedUser);
  }, []);

  useEffect(() => {
    async function restoreSession() {
      const existingToken = getStoredToken();

      // On app bootstrap we restore session once. Protected routes wait for this
      // flag, preventing false redirects before token validation completes.
      if (!existingToken) {
        setIsAuthLoading(false);
        return;
      }

      try {
        const authenticatedUser = await fetchCurrentUser();
        setToken(existingToken);
        setUser(authenticatedUser);
      } catch {
        clearStoredToken();
        setToken(null);
        setUser(null);
      } finally {
        setIsAuthLoading(false);
      }
    }

    void restoreSession();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isAuthenticated: Boolean(user && token),
      isAuthLoading,
      login,
      logout,
    }),
    [isAuthLoading, login, logout, token, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within AuthProvider.');
  }

  return context;
}
