import axios from 'axios';

import { clearStoredToken, getStoredToken } from '@/features/auth/utils/tokenStorage';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = getStoredToken();

  // All authenticated API calls read token from one shared source
  // so request modules do not need to handle auth headers themselves.
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // If backend marks token invalid, clear local token immediately to keep
    // client auth state aligned with server-side session validity.
    if (error.response?.status === 401) {
      clearStoredToken();
    }

    return Promise.reject(error);
  },
);
