import { api } from '@/lib/api';
import type { ApiEnvelope, AuthUser, LoginPayload, LoginResponseData } from '@/features/auth/types/authTypes';

export async function loginRequest(payload: LoginPayload): Promise<LoginResponseData> {
  const response = await api.post<ApiEnvelope<LoginResponseData>>('/auth/login', payload);
  return response.data.data;
}

export async function fetchCurrentUser(): Promise<AuthUser> {
  const response = await api.get<ApiEnvelope<AuthUser>>('/auth/me');
  return response.data.data;
}

export async function logoutRequest(): Promise<void> {
  await api.post('/auth/logout');
}
