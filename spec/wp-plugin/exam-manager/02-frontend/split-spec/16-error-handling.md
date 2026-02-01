# 16. Error Handling

## Overview
Client-side error handling for API errors, network failures, validation errors, and user feedback.

---

## 16.1 Error Categories

| Category | Examples | Handling |
|----------|----------|----------|
| Validation | Invalid email, password too short | Inline field errors |
| Authentication | 401 Unauthorized | Redirect to login |
| Authorization | 403 Forbidden | Show access denied |
| Not Found | 404 Resource missing | Show not found page |
| Rate Limiting | 429 Too Many Requests | Show retry message |
| Network | No connection | Show retry option |
| Server | 500 Internal Error | Show generic error |

---

## 16.2 Error Display Patterns

### Inline Validation
- Show error message below field
- Red border on invalid field
- Clear when field becomes valid

### Toast Notifications
- Brief error messages (3-5 seconds)
- Dismissible
- Stacked if multiple

### Error Modal
- For critical errors requiring action
- Clear message and action buttons
- Cannot be dismissed without action

### Error Page
- Full page for 404, 500 errors
- Clear message and navigation options

---

## 16.3 API Error Handling

```javascript
async function apiCall(endpoint, options) {
  try {
    const response = await fetch(endpoint, options);
    
    if (response.status === 401) {
      handleSessionExpired();
      return;
    }
    
    if (response.status === 403) {
      handleForbidden(await response.json());
      return;
    }
    
    if (response.status === 429) {
      handleRateLimited(response.headers.get('Retry-After'));
      return;
    }
    
    if (!response.ok) {
      const error = await response.json();
      showError(error.message || 'An error occurred');
      return;
    }
    
    return await response.json();
  } catch (err) {
    handleNetworkError(err);
  }
}
```

---

## 16.4 Acceptance Criteria

- [ ] All API errors show user-friendly messages
- [ ] 401 redirects to login with return URL
- [ ] Network errors show retry option
- [ ] Rate limiting shows wait time
- [ ] Validation errors display inline

---

*Related: [02-error-management](../../01-admin-backend/split-spec/02-error-management.md), [66-shared-constants](../../66-shared-constants.md) (Error codes)*
