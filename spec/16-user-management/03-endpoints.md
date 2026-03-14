# User Management — REST API Endpoints

Base: `/{namespace}/v1/`

## Endpoint Summary

| Method | Path                    | Handler                  | Permission        | Description                     |
|--------|-------------------------|--------------------------|--------------------|---------------------------------|
| GET    | `/users`                | `handleListUsers`        | `list_users`       | List users (paginated)          |
| GET    | `/users/(?P<id>\d+)`   | `handleGetUser`          | `edit_users`       | Get single user with all fields |
| POST   | `/users`                | `handleCreateUser`       | `create_users`     | Create new user                 |
| PUT    | `/users/(?P<id>\d+)`   | `handleUpdateUser`       | `edit_users`       | Update user fields              |
| DELETE | `/users/(?P<id>\d+)`   | `handleDeleteUser`       | `delete_users`     | Delete user (with reassign)     |
| POST   | `/users/app-password`  | `handleCreateAppPass`    | `edit_users`       | Create application password     |
| DELETE | `/users/app-password`  | `handleRevokeAppPass`    | `edit_users`       | Revoke application password     |
| GET    | `/users/export`        | `handleExportUsers`      | `list_users`       | Export users as CSV             |
| POST   | `/users/import`        | `handleImportUsers`      | `create_users`     | Import users from CSV           |
| GET    | `/users/export-sqlite` | `handleExportSqlite`     | `list_users`       | Export as SQLite ZIP            |
| POST   | `/users/import-sqlite` | `handleImportSqlite`     | `create_users`     | Import from SQLite ZIP          |

---

## GET /users

List all users with pagination and optional role filter.

### Query Parameters

| Param     | Type   | Default | Description              |
|-----------|--------|---------|--------------------------|
| `page`    | int    | 1       | Page number              |
| `per_page`| int    | 20      | Items per page (max 100) |
| `role`    | string | —       | Filter by role slug      |
| `search`  | string | —       | Search by name/email     |

### Response (200)

```json
{
  "Success": true,
  "Result": [
    {
      "Id": 1,
      "Username": "admin",
      "Email": "admin@example.com",
      "DisplayName": "Site Admin",
      "Role": "administrator",
      "RegisteredAt": "2024-01-15 10:30:00"
    }
  ],
  "Pagination": {
    "Page": 1,
    "PerPage": 20,
    "Total": 45,
    "TotalPages": 3
  }
}
```

---

## GET /users/{id}

Returns full user details including social links and Yoast metadata.

### Response (200)

```json
{
  "Success": true,
  "Result": {
    "Id": 1,
    "Username": "admin",
    "Email": "admin@hire-seoexperts.com",
    "FirstName": "John",
    "LastName": "Doe",
    "DisplayName": "John Doe",
    "Nickname": "johnd",
    "Website": "https://testv2.developers-organism.com",
    "Bio": "This Site Admin, has great experience in SEO writing...",
    "Role": "administrator",
    "RegisteredAt": "2024-01-15 10:30:00",
    "Social": {
      "Facebook": "",
      "Instagram": "",
      "LinkedIn": "",
      "MySpace": "",
      "Pinterest": "",
      "SoundCloud": "",
      "Tumblr": "",
      "Wikipedia": "",
      "X": "",
      "YouTube": "",
      "Mastodon": ""
    },
    "Yoast": {
      "HonorificPrefix": "",
      "HonorificSuffix": "SEO Expert",
      "BirthDate": "1985-04-14",
      "Gender": "male",
      "Awards": "SEO Excellence Award 2022-05-15, Global Search Inno",
      "ExpertiseIn": "Authentic storytelling and authoritative SEO writing ar",
      "LanguagesSpoken": "English, German",
      "JobTitle": "American Native SEO Writer | Rank in Google in notim",
      "EmployerName": "Fox News Inc",
      "AuthorTitle": "Expert SEO Writer with 25 years of Experience",
      "AuthorMetaDescription": "This Site Admin, has great experience in SEO writing...",
      "Pronouns": "he/him"
    }
  }
}
```

**Note:** The `Yoast` object is omitted entirely if Yoast SEO is not active.

---

## POST /users

Create a new WordPress user.

### Request Body

```json
{
  "Username": "newuser",
  "Email": "user@example.com",
  "Password": "secureP@ss123",
  "FirstName": "Jane",
  "LastName": "Smith",
  "DisplayName": "Jane Smith",
  "Nickname": "janes",
  "Website": "https://janesmith.com",
  "Bio": "Content writer with 10 years experience.",
  "Role": "editor",
  "Social": {
    "LinkedIn": "https://linkedin.com/in/janesmith",
    "X": "janesmith"
  },
  "Yoast": {
    "JobTitle": "Senior Editor",
    "EmployerName": "Media Corp",
    "Pronouns": "she/her"
  },
  "CreateAppPassword": true,
  "AppPasswordName": "API Access"
}
```

### Response (201)

```json
{
  "Success": true,
  "Result": {
    "Id": 42,
    "Username": "newuser",
    "Email": "user@example.com",
    "Role": "editor",
    "AppPassword": "xxxx xxxx xxxx xxxx xxxx xxxx"
  }
}
```

**Note:** `AppPassword` is only included when `CreateAppPassword: true`. This
is the only time the app password is returned in plaintext.

---

## PUT /users/{id}

Update user fields. Only provided fields are modified (partial update).

### Request Body

```json
{
  "Email": "updated@example.com",
  "Bio": "Updated biographical info.",
  "Role": "author",
  "Social": {
    "YouTube": "https://youtube.com/@channel"
  },
  "Yoast": {
    "Awards": "Best Writer 2025",
    "LanguagesSpoken": "English, French, Dutch"
  }
}
```

### Response (200)

```json
{
  "Success": true,
  "Result": {
    "Id": 42,
    "Updated": true,
    "FieldsModified": ["Email", "Bio", "Role", "Social.YouTube", "Yoast.Awards", "Yoast.LanguagesSpoken"]
  }
}
```

---

## DELETE /users/{id}

Delete a user. Optionally reassign their content to another user.

### Query Parameters

| Param      | Type | Default | Description                              |
|------------|------|---------|------------------------------------------|
| `reassign` | int  | —       | User ID to reassign content to           |

### Response (200)

```json
{
  "Success": true,
  "Result": {
    "Deleted": true,
    "ReassignedTo": 1
  }
}
```

---

## POST /users/app-password

Create an application password for a user.

### Request Body

```json
{
  "UserId": 42,
  "Name": "Deployment Key"
}
```

### Response (201)

```json
{
  "Success": true,
  "Result": {
    "UserId": 42,
    "Name": "Deployment Key",
    "Password": "xxxx xxxx xxxx xxxx xxxx xxxx",
    "Uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  }
}
```

---

## DELETE /users/app-password

Revoke an application password.

### Request Body

```json
{
  "UserId": 42,
  "Uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

### Response (200)

```json
{
  "Success": true,
  "Result": {
    "Revoked": true
  }
}
```

---

## GET /users/export

Export all users as CSV. Passwords are exported as WordPress PHPass bcrypt
hashes — **never plaintext**.

### Query Parameters

| Param       | Type   | Default | Description              |
|-------------|--------|---------|--------------------------|
| `role`      | string | —       | Filter by role           |
| `format`    | string | `csv`   | Only `csv` for this endpoint |

### Response

Returns `Content-Type: text/csv` with `Content-Disposition: attachment`.

CSV columns match the field reference in [02-fields.md](./02-fields.md) in
order: `Id, Username, Email, PasswordHash, FirstName, LastName, DisplayName,
Nickname, Website, Bio, Role, RegisteredAt, Social.Facebook, Social.Instagram,
...` (all social fields), then all Yoast fields if Yoast is active.

---

## POST /users/import

Import users from CSV upload. Creates new users or updates existing (matched by
`Username` or `Email`).

### Request

`Content-Type: multipart/form-data` with a `file` field containing the CSV.

### Password Handling on Import

| CSV `PasswordHash` Value       | Behavior                                    |
|--------------------------------|---------------------------------------------|
| Starts with `$P$` or `$2y$`   | Stored directly (pre-hashed)                |
| Any other non-empty string     | Treated as plaintext, hashed by `wp_hash_password()` |
| Empty                          | Random password generated (user must reset) |

### Response (200)

```json
{
  "Success": true,
  "Result": {
    "Created": 15,
    "Updated": 3,
    "Skipped": 1,
    "Errors": [
      { "Row": 7, "Username": "bad_user", "Error": "Invalid email format" }
    ]
  }
}
```

---

## GET /users/export-sqlite

Export all users as a SQLite database bundled in a ZIP file.

### SQLite Schema

```sql
CREATE TABLE users (
    id              INTEGER PRIMARY KEY,
    username        TEXT NOT NULL UNIQUE,
    email           TEXT NOT NULL,
    password_hash   TEXT NOT NULL,
    first_name      TEXT DEFAULT '',
    last_name       TEXT DEFAULT '',
    display_name    TEXT DEFAULT '',
    nickname        TEXT DEFAULT '',
    website         TEXT DEFAULT '',
    bio             TEXT DEFAULT '',
    role            TEXT DEFAULT 'subscriber',
    registered_at   TEXT DEFAULT ''
);

CREATE TABLE user_social (
    user_id   INTEGER NOT NULL REFERENCES users(id),
    platform  TEXT NOT NULL,
    url       TEXT DEFAULT '',
    PRIMARY KEY (user_id, platform)
);

CREATE TABLE user_yoast (
    user_id   INTEGER NOT NULL REFERENCES users(id),
    meta_key  TEXT NOT NULL,
    value     TEXT DEFAULT '',
    PRIMARY KEY (user_id, meta_key)
);
```

### Response

Returns `Content-Type: application/zip` with `Content-Disposition: attachment`.

---

## POST /users/import-sqlite

Import users from a SQLite ZIP backup (same schema as export).

### Request

`Content-Type: multipart/form-data` with a `file` field containing the ZIP.

### Response (200)

Same structure as CSV import response.
