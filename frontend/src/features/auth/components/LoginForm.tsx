import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';

import { useLogin } from '@/features/auth/hooks/useLogin';
import { loginSchema, type LoginFormValues } from '@/features/auth/schemas/loginSchema';

function extractErrorMessage(error: unknown): string {
  const fallbackMessage = 'Login failed. Please verify your credentials.';

  if (!error || typeof error !== 'object') {
    return fallbackMessage;
  }

  const maybeAxiosError = error as {
    response?: {
      data?: {
        message?: string;
        errors?: Record<string, string[]>;
      };
    };
  };

  const firstValidationError = maybeAxiosError.response?.data?.errors
    ? Object.values(maybeAxiosError.response.data.errors)[0]?.[0]
    : undefined;

  return firstValidationError ?? maybeAxiosError.response?.data?.message ?? fallbackMessage;
}

export function LoginForm() {
  const navigate = useNavigate();
  const { mutateAsync: login, isPending, error, reset } = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    reset();
    await login(values);
    // Requests page is the first protected destination needed by current MVP flow.
    navigate('/requests', { replace: true });
  });

  return (
    <form className="space-y-4" onSubmit={onSubmit}>
      <div>
        <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="email">
          Email
        </label>
        <input
          id="email"
          type="email"
          autoComplete="email"
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-slate-500"
          disabled={isPending}
          {...register('email')}
        />
        {errors.email ? <p className="mt-1 text-sm text-red-600">{errors.email.message}</p> : null}
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="password">
          Password
        </label>
        <input
          id="password"
          type="password"
          autoComplete="current-password"
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-slate-500"
          disabled={isPending}
          {...register('password')}
        />
        {errors.password ? <p className="mt-1 text-sm text-red-600">{errors.password.message}</p> : null}
      </div>

      {error ? <p className="text-sm text-red-600">{extractErrorMessage(error)}</p> : null}

      <button
        type="submit"
        disabled={isPending}
        className="w-full rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
      >
        {isPending ? 'Signing in...' : 'Sign in'}
      </button>
    </form>
  );
}
