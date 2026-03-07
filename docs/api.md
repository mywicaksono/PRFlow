# PRFlow API v1

Base URL: `/api/v1`

## Response Contract

### Success envelope

```json
{
  "success": true,
  "message": "...",
  "data": {}
}
```

### Error envelope

```json
{
  "success": false,
  "message": "...",
  "errors": {}
}
```

### Standard error examples

#### 401 Unauthenticated

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": null
}
```

#### 403 Unauthorized

```json
{
  "success": false,
  "message": "Unauthorized.",
  "errors": null
}
```

#### 404 Resource not found

```json
{
  "success": false,
  "message": "Resource not found.",
  "errors": null
}
```

#### 422 Validation failed

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["..."]
  }
}
```

---

## Auth

### POST `/auth/login`
- Auth: public
- Roles: all
- Payload:

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

### POST `/auth/logout`
- Auth: `auth:sanctum`
- Roles: authenticated user

### GET `/auth/me`
- Auth: `auth:sanctum`
- Roles: authenticated user

---

## Requests

### POST `/requests`
- Auth: `auth:sanctum`
- Roles: `staff`, `admin`
- Creates draft request (`status=draft`, `current_level=1`)
- Payload:

```json
{
  "amount": 1500000,
  "description": "Laptop procurement",
  "department_id": 1
}
```

### GET `/requests`
- Auth: `auth:sanctum`
- Roles: authenticated
- Staff: own requests only
- Admin: all requests

### GET `/requests/{id}`
- Auth: `auth:sanctum`
- Roles: owner, assigned approver, admin

### POST `/requests/{id}/submit`
- Auth: `auth:sanctum`
- Roles: `staff`, `admin`
- Allowed only for draft request
- Creates approval records based on amount matrix

---

## Approvals

### GET `/approvals/pending`
- Auth: `auth:sanctum`
- Roles: `supervisor`, `manager`, `finance`, `admin`
- Returns pending approvals at current request level
- Admin can view globally

### POST `/approvals/{purchaseRequest}/approve`
- Auth: `auth:sanctum`
- Roles: `supervisor`, `manager`, `finance`
- Only assigned current-level approver can approve
- Admin is intentionally not allowed unless assigned approver role/account

### POST `/approvals/{purchaseRequest}/reject`
- Auth: `auth:sanctum`
- Roles: `supervisor`, `manager`, `finance`
- Payload:

```json
{
  "reason": "Budget not sufficient"
}
```

- Only assigned current-level approver can reject

---

## Request History

### GET `/requests/{id}/history`
- Auth: `auth:sanctum`
- Roles: owner, assigned approver, admin
- Returns request summary + approval timeline ordered by level and created time

---

## Request Activities

### GET `/requests/{id}/activities`
- Auth: `auth:sanctum`
- Roles: owner, assigned approver, admin
- Returns structured activity timeline (action, actor, meta, timestamp)

---

## Attachments

### GET `/requests/{id}/attachments`
- Auth: `auth:sanctum`
- Roles: owner, assigned approver, admin

### POST `/requests/{id}/attachments`
- Auth: `auth:sanctum`
- Roles: `staff`, `admin` (owner/admin checks still enforced)
- Rules:
  - request must be `draft`
  - max file size 2MB
  - allowed: `pdf`, `jpg`, `jpeg`, `png`
- Multipart field: `file`

### DELETE `/attachments/{id}`
- Auth: `auth:sanctum`
- Roles: `staff`, `admin` (owner/admin checks still enforced)
- Rules:
  - request must be `draft`

---

## Notifications

### GET `/notifications`
- Auth: `auth:sanctum`
- Roles: authenticated
- Returns paginated notifications for current user only

### POST `/notifications/{id}/read`
- Auth: `auth:sanctum`
- Roles: authenticated
- Marks current user's notification as read

---

## Dashboard

### GET `/dashboard/summary`
- Auth: `auth:sanctum`
- Roles: authenticated
- Role-aware metrics:
  - staff: request counts by status
  - approver roles: pending/approved/rejected-by-me
  - admin: global request and pending approval totals

### GET `/dashboard/recent-requests`
- Auth: `auth:sanctum`
- Roles: authenticated
- staff: latest 5 own
- approver: latest 5 related to own approvals (pending current-level prioritized)
- admin: latest 5 global

### GET `/dashboard/recent-notifications`
- Auth: `auth:sanctum`
- Roles: authenticated
- latest 5 notifications for current user

---

## Workflow Rules (Business Notes)

### Request statuses
- `draft`
- `submitted`
- `approved`
- `rejected`
- `completed` (reserved)

### Approval statuses
- `pending`
- `approved`
- `rejected`

### Approval routing by amount
- `< 5,000,000` => supervisor
- `5,000,000 - 20,000,000` => supervisor -> manager
- `> 20,000,000` => supervisor -> manager -> finance

### Visibility rules
- Owner can view own request data
- Assigned approver can view assigned request data
- Admin can view all requests, pending approvals, activities, and attachments

### Admin approval behavior
- Admin can list pending approvals globally
- Admin cannot approve/reject unless assigned as current approver (action endpoints are role-restricted)

### Attachment rules
- Upload/delete only while request is `draft`
- Upload/delete actor must be owner or admin
- Approvers may list if assigned to request

### Notification behavior
- Submit: notify first current approver
- Intermediate approval: notify next approver
- Final approval: notify request owner
- Reject: notify request owner
