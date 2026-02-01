# Database Conventions

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines database design conventions, naming standards, and best practices for relational databases (PostgreSQL, MySQL, SQLite).

---

## 1. Naming Conventions

### 1.1 Table Names

```
RULE: Singular, PascalCase
- User (not users, user, USER)
- OrderItem (not order_items, order_item)
- UserRole (not user_roles, userRoles)
```

### 1.2 Column Names

```
RULE: PascalCase, descriptive
- CreatedAt (not created_at, createdAt)
- IsActive (not is_active, isActive)
- UserId (not user_id, userId)
```

### 1.3 Naming Patterns

| Type | Pattern | Examples |
|------|---------|----------|
| Primary Key | `Id` | `Id` |
| Foreign Key | `{Table}Id` | `UserId`, `OrderId` |
| Boolean | `Is`, `Has`, `Can` prefix | `IsActive`, `HasVerified`, `CanEdit` |
| Timestamp | `At` suffix | `CreatedAt`, `UpdatedAt`, `DeletedAt` |
| Count | `Count` suffix | `ViewCount`, `CommentCount` |
| Enum/Status | descriptive name | `Status`, `Type`, `Role` |

### 1.4 Index Names

```
Format: IX_{Table}_{Column(s)}
Format (unique): UQ_{Table}_{Column(s)}
Format (foreign key): FK_{Table}_{ReferencedTable}

Examples:
- IX_User_Email
- IX_Order_UserId_CreatedAt
- UQ_User_Email
- FK_Order_User
```

### 1.5 Constraint Names

```
Format: {ConstraintType}_{Table}_{Column(s)}

Types:
- PK = primary key
- FK = foreign key
- UQ = unique
- CK = check

Examples:
- PK_User_Id
- FK_Order_UserId
- UQ_User_Email
- CK_Order_TotalPositive
```

---

## 2. Column Design

### 2.1 Required Columns

Every table MUST have:

```sql
-- Standard columns
Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
CreatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
UpdatedAt TIMESTAMPTZ NOT NULL DEFAULT now()

-- Soft delete (optional but recommended)
DeletedAt TIMESTAMPTZ DEFAULT NULL
```

### 2.2 Data Types

| Concept | PostgreSQL | MySQL | SQLite |
|---------|------------|-------|--------|
| Primary Key | `UUID` | `CHAR(36)` | `TEXT` |
| Short Text | `VARCHAR(255)` | `VARCHAR(255)` | `TEXT` |
| Long Text | `TEXT` | `TEXT` | `TEXT` |
| Integer | `INTEGER` | `INT` | `INTEGER` |
| Big Integer | `BIGINT` | `BIGINT` | `INTEGER` |
| Decimal | `NUMERIC(19,4)` | `DECIMAL(19,4)` | `REAL` |
| Boolean | `BOOLEAN` | `TINYINT(1)` | `INTEGER` |
| Timestamp | `TIMESTAMPTZ` | `DATETIME` | `TEXT` |
| JSON | `JSONB` | `JSON` | `TEXT` |
| Enum | `CREATE TYPE` | `ENUM` | `TEXT` + CHECK |

### 2.3 Column Modifiers

```sql
-- Prefer NOT NULL with defaults over nullable
Email VARCHAR(255) NOT NULL,
Status VARCHAR(20) NOT NULL DEFAULT 'pending',
IsActive BOOLEAN NOT NULL DEFAULT true,

-- Nullable only when semantically meaningful
DeletedAt TIMESTAMPTZ DEFAULT NULL,  -- NULL = not deleted
ParentId UUID DEFAULT NULL,          -- NULL = no parent
```

---

## 3. Primary Keys

### 3.1 UUID Strategy (Preferred)

```sql
-- PostgreSQL
CREATE TABLE User (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    -- ...
);

-- With extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
Id UUID PRIMARY KEY DEFAULT uuid_generate_v4()
```

**Advantages:**
- No sequential exposure
- Distributed-friendly
- Merge-safe
- Client-side generation possible

### 3.2 Auto-Increment (Alternative)

```sql
-- PostgreSQL
CREATE TABLE User (
    Id BIGSERIAL PRIMARY KEY,
    -- ...
);

-- MySQL
CREATE TABLE User (
    Id BIGINT AUTO_INCREMENT PRIMARY KEY,
    -- ...
);
```

**Use when:**
- Performance critical (smaller index size)
- Sequential access patterns
- Legacy system compatibility

---

## 4. Foreign Keys

### 4.1 Referential Integrity

```sql
CREATE TABLE Order (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    UserId UUID NOT NULL,
    CreatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    
    CONSTRAINT FK_Order_User 
        FOREIGN KEY (UserId) 
        REFERENCES User(Id) 
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);
```

### 4.2 Delete Behaviors

| Behavior | Use Case |
|----------|----------|
| `RESTRICT` | Prevent delete if children exist (default) |
| `CASCADE` | Delete children with parent |
| `SET NULL` | Orphan children (nullable FK) |
| `SET DEFAULT` | Set to default value |
| `NO ACTION` | Similar to RESTRICT, deferred check |

### 4.3 Guidelines

```
- RESTRICT: User -> Order (can't delete User with Orders)
- CASCADE: User -> UserSession (delete Sessions with User)
- SET NULL: Post -> Author (keep Post if Author deleted)
```

---

## 5. Indexes

### 5.1 Index Strategy

```sql
-- Primary key (automatic)
-- Foreign keys (create explicitly)
CREATE INDEX IX_Order_UserId ON Order(UserId);

-- Frequently filtered columns
CREATE INDEX IX_User_Email ON User(Email);
CREATE INDEX IX_Order_Status ON Order(Status);

-- Composite for common queries
CREATE INDEX IX_Order_UserId_Status ON Order(UserId, Status);

-- Partial index for specific conditions
CREATE INDEX IX_Order_Pending 
    ON Order(CreatedAt) 
    WHERE Status = 'pending';
```

### 5.2 Index Rules

```
DO:
✓ Index all foreign keys
✓ Index columns used in WHERE clauses
✓ Index columns used in ORDER BY
✓ Use composite indexes for multi-column queries
✓ Consider partial indexes for filtered subsets

DON'T:
✗ Index every column
✗ Create redundant indexes
✗ Index low-cardinality columns alone
✗ Ignore index maintenance overhead
```

### 5.3 Unique Constraints

```sql
-- Single column unique
CREATE UNIQUE INDEX UQ_User_Email ON User(Email);

-- Composite unique
CREATE UNIQUE INDEX UQ_UserRole_UserId_Role 
    ON UserRole(UserId, Role);

-- Partial unique (only active records)
CREATE UNIQUE INDEX UQ_User_Email_Active 
    ON User(Email) 
    WHERE DeletedAt IS NULL;
```

---

## 6. Timestamps

### 6.1 Standard Timestamps

```sql
CREATE TABLE User (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Standard timestamps
    CreatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    UpdatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    
    -- Soft delete
    DeletedAt TIMESTAMPTZ DEFAULT NULL
);

-- Auto-update trigger (PostgreSQL)
CREATE OR REPLACE FUNCTION update_UpdatedAt()
RETURNS TRIGGER AS $$
BEGIN
    NEW.UpdatedAt = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER TR_User_UpdatedAt
    BEFORE UPDATE ON User
    FOR EACH ROW
    EXECUTE FUNCTION update_UpdatedAt();
```

### 6.2 Timezone Handling

```
RULE: Always store in UTC
- Use TIMESTAMPTZ (PostgreSQL) or DATETIME (MySQL) with UTC
- Convert to local timezone only at presentation layer
- Store timezone preference in user settings
```

---

## 7. Enums and Status Fields

### 7.1 PostgreSQL Enums

```sql
-- Create enum type
CREATE TYPE OrderStatus AS ENUM (
    'Pending',
    'Confirmed',
    'Processing',
    'Shipped',
    'Delivered',
    'Cancelled'
);

-- Use in table
CREATE TABLE Order (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    Status OrderStatus NOT NULL DEFAULT 'Pending',
    -- ...
);

-- Add value to existing enum
ALTER TYPE OrderStatus ADD VALUE 'Refunded' AFTER 'Cancelled';
```

### 7.2 Check Constraints (Portable)

```sql
-- SQLite / MySQL compatibility
CREATE TABLE Order (
    Id UUID PRIMARY KEY,
    Status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    
    CONSTRAINT CK_Order_Status CHECK (
        Status IN ('Pending', 'Confirmed', 'Processing', 
                   'Shipped', 'Delivered', 'Cancelled')
    )
);
```

---

## 8. JSON Columns

### 8.1 When to Use JSON

```
USE JSON FOR:
✓ Flexible/dynamic attributes
✓ Nested structures that are read as a whole
✓ Third-party API responses
✓ Configuration/settings

AVOID JSON FOR:
✗ Frequently queried fields
✗ Fields needing referential integrity
✗ Highly structured, stable schemas
```

### 8.2 JSON Column Patterns

```sql
-- PostgreSQL JSONB
CREATE TABLE User (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    Settings JSONB NOT NULL DEFAULT '{}',
    Metadata JSONB DEFAULT NULL
);

-- Index JSON fields
CREATE INDEX IX_User_Settings_Theme 
    ON User USING GIN ((Settings -> 'theme'));

-- Query JSON
SELECT * FROM User 
WHERE Settings ->> 'theme' = 'dark';

SELECT * FROM User 
WHERE Settings @> '{"notifications": {"email": true}}';
```

---

## 9. Soft Deletes

### 9.1 Implementation

```sql
CREATE TABLE User (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    Email VARCHAR(255) NOT NULL,
    DeletedAt TIMESTAMPTZ DEFAULT NULL,
    
    CreatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    UpdatedAt TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Unique constraint on active records only
CREATE UNIQUE INDEX UQ_User_Email_Active 
    ON User(Email) 
    WHERE DeletedAt IS NULL;

-- Soft delete function
CREATE OR REPLACE FUNCTION soft_delete()
RETURNS TRIGGER AS $$
BEGIN
    NEW.DeletedAt = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
```

### 9.2 Query Patterns

```sql
-- Default: exclude deleted
SELECT * FROM User WHERE DeletedAt IS NULL;

-- Include deleted (admin)
SELECT * FROM User;

-- Only deleted (recovery)
SELECT * FROM User WHERE DeletedAt IS NOT NULL;
```

### 9.3 View for Active Records

```sql
CREATE VIEW VW_ActiveUser AS
SELECT * FROM User WHERE DeletedAt IS NULL;
```

---

## 10. Audit Columns

### 10.1 Basic Audit

```sql
CREATE TABLE Order (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Audit columns
    CreatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    CreatedBy UUID REFERENCES User(Id),
    UpdatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    UpdatedBy UUID REFERENCES User(Id),
    
    -- ...
);
```

### 10.2 Full Audit Trail

```sql
CREATE TABLE AuditLog (
    Id BIGSERIAL PRIMARY KEY,
    TableName VARCHAR(100) NOT NULL,
    RecordId UUID NOT NULL,
    Action VARCHAR(10) NOT NULL,  -- INSERT, UPDATE, DELETE
    OldValues JSONB,
    NewValues JSONB,
    ChangedBy UUID REFERENCES User(Id),
    ChangedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    IpAddress INET,
    UserAgent TEXT
);

-- Index for common queries
CREATE INDEX IX_AuditLog_TableName_RecordId 
    ON AuditLog(TableName, RecordId);
CREATE INDEX IX_AuditLog_ChangedAt 
    ON AuditLog(ChangedAt);
```

---

## 11. Table Relationships

### 11.1 One-to-Many

```sql
-- Parent
CREATE TABLE User (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    Name VARCHAR(255) NOT NULL
);

-- Child (many orders per user)
CREATE TABLE Order (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    UserId UUID NOT NULL REFERENCES User(Id),
    Total NUMERIC(19,4) NOT NULL
);

CREATE INDEX IX_Order_UserId ON Order(UserId);
```

### 11.2 Many-to-Many

```sql
-- Junction table
CREATE TABLE UserRole (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    UserId UUID NOT NULL REFERENCES User(Id) ON DELETE CASCADE,
    RoleId UUID NOT NULL REFERENCES Role(Id) ON DELETE CASCADE,
    GrantedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
    GrantedBy UUID REFERENCES User(Id),
    
    UNIQUE(UserId, RoleId)
);

CREATE INDEX IX_UserRole_UserId ON UserRole(UserId);
CREATE INDEX IX_UserRole_RoleId ON UserRole(RoleId);
```

### 11.3 Self-Referential

```sql
-- Hierarchical data (e.g., categories)
CREATE TABLE Category (
    Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    Name VARCHAR(255) NOT NULL,
    ParentId UUID REFERENCES Category(Id) ON DELETE SET NULL,
    Level INTEGER NOT NULL DEFAULT 0,
    Path TEXT NOT NULL DEFAULT ''  -- Materialized path
);

CREATE INDEX IX_Category_ParentId ON Category(ParentId);
CREATE INDEX IX_Category_Path ON Category(Path);
```

---

## 12. Migration Patterns

### 12.1 Migration File Naming

```
Format: {timestamp}_{Description}.sql

Examples:
- 20250126_CreateUserTable.sql
- 20250126_AddUserEmailIndex.sql
- 20250126_AlterOrderAddDiscount.sql
```

### 12.2 Safe Migration Practices

```sql
-- Adding column (safe)
ALTER TABLE User ADD COLUMN Phone VARCHAR(20);

-- Adding NOT NULL column (with default)
ALTER TABLE User ADD COLUMN IsVerified BOOLEAN NOT NULL DEFAULT false;

-- Renaming column (PostgreSQL 9.6+)
ALTER TABLE User RENAME COLUMN Name TO FullName;

-- Adding index concurrently (no lock)
CREATE INDEX CONCURRENTLY IX_User_Phone ON User(Phone);

-- Dropping column (safe, but be careful)
ALTER TABLE User DROP COLUMN LegacyField;
```

### 12.3 Dangerous Operations

```sql
-- ⚠ Locking operations - schedule during maintenance window

-- Adding NOT NULL without default (requires table rewrite)
ALTER TABLE User ALTER COLUMN Phone SET NOT NULL;

-- Changing column type
ALTER TABLE User ALTER COLUMN Age TYPE INTEGER;

-- Creating index without CONCURRENTLY
CREATE INDEX IX_LargeTable_Column ON LargeTable(Column);
```

---

## 13. Query Patterns

### 13.1 Pagination

```sql
-- Offset pagination (simple, not scalable)
SELECT * FROM User
ORDER BY CreatedAt DESC
LIMIT 20 OFFSET 40;

-- Cursor pagination (scalable)
SELECT * FROM User
WHERE CreatedAt < $1  -- cursor value
ORDER BY CreatedAt DESC
LIMIT 20;
```

### 13.2 Locking

```sql
-- Pessimistic locking
SELECT * FROM Order WHERE Id = $1 FOR UPDATE;

-- Skip locked rows (queue processing)
SELECT * FROM Job
WHERE Status = 'Pending'
ORDER BY CreatedAt
LIMIT 1
FOR UPDATE SKIP LOCKED;
```

---

## Database Design Checklist

| Category | Requirement | Priority |
|----------|-------------|----------|
| Naming | PascalCase, singular tables | Required |
| Keys | UUID or BIGSERIAL primary keys | Required |
| Columns | NOT NULL with defaults preferred | Required |
| Timestamps | CreatedAt, UpdatedAt on all tables | Required |
| Foreign Keys | Explicit FK constraints | Required |
| Indexes | Index all foreign keys | Required |
| Indexes | Index frequently filtered columns | High |
| Soft Delete | DeletedAt pattern | Recommended |
| Audit | CreatedBy, UpdatedBy | Recommended |
| Enums | Use enum types or CHECK constraints | Required |

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - camelCase for code, PascalCase for DB
- [03-api-conventions-quality.md](../03-quality/03-api-conventions-quality.md) - API field naming
- [01-security-patterns-advanced.md](./01-security-patterns-advanced.md) - SQL injection prevention
- [02-caching-patterns-advanced.md](./02-caching-patterns-advanced.md) - Query result caching
