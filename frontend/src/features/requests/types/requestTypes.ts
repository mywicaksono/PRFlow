export type RequestStatus = 'draft' | 'submitted' | 'approved' | 'rejected';

export interface RequestItem {
  id: number;
  request_number: string;
  department_id: number;
  user_id: number;
  amount: string | number;
  description: string;
  status: RequestStatus;
  current_level: number;
  submitted_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  links: PaginationLink[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface ListRequestsParams {
  page?: number;
  status?: RequestStatus | '';
  search?: string;
  sort_by?: 'created_at' | 'amount' | 'status';
  sort_direction?: 'asc' | 'desc';
}
