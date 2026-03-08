export type UserRole = 'staff' | 'admin' | 'supervisor' | 'manager' | 'finance';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface LoginResponseData {
  token: string;
  user: AuthUser;
}

export interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}
