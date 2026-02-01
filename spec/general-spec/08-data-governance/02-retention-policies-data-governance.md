# Data Retention Policies

> Version: 1.0.0 | Last Updated: 2026-01-26

## Overview

This document defines data retention standards, lifecycle management, and deletion procedures to ensure compliance with regulatory requirements while optimizing storage costs.

---

## 1. Retention Framework

### 1.1 Retention Categories

```
┌─────────────────────────────────────────────────────────────┐
│  PERMANENT (No Expiration)                                  │
│  • Legal holds, audit trails, financial records             │
│  • Archive to cold storage after 2 years                    │
├─────────────────────────────────────────────────────────────┤
│  LONG-TERM (3-7 Years)                                      │
│  • Contracts, compliance records, tax documents             │
│  • Review annually for continued need                       │
├─────────────────────────────────────────────────────────────┤
│  MEDIUM-TERM (1-3 Years)                                    │
│  • User accounts, transaction history, support tickets      │
│  • Anonymize after retention period                         │
├─────────────────────────────────────────────────────────────┤
│  SHORT-TERM (30-365 Days)                                   │
│  • Session logs, temporary files, cache data                │
│  • Auto-delete after expiration                             │
├─────────────────────────────────────────────────────────────┤
│  EPHEMERAL (<30 Days)                                       │
│  • Debug logs, rate limit counters, temp tokens             │
│  • Delete immediately when no longer needed                 │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Default Retention Matrix

| Data Type | Category | Retention | Post-Retention Action |
|-----------|----------|-----------|----------------------|
| Audit logs | PERMANENT | Unlimited | Archive to cold storage |
| Financial transactions | LONG-TERM | 7 years | Archive then delete |
| Contracts/Agreements | LONG-TERM | 7 years + 1 | Delete after statute |
| User accounts | MEDIUM-TERM | Account lifetime + 2 years | Anonymize |
| User content | MEDIUM-TERM | Account lifetime + 90 days | Delete |
| Support tickets | MEDIUM-TERM | 3 years | Anonymize |
| Session data | SHORT-TERM | 30 days | Delete |
| Access logs | SHORT-TERM | 90 days | Delete |
| Error logs | SHORT-TERM | 180 days | Archive then delete |
| Password reset tokens | EPHEMERAL | 1 hour | Delete |
| Email verification tokens | EPHEMERAL | 24 hours | Delete |
| Rate limit data | EPHEMERAL | 1 hour | Delete |

---

## 2. Regulatory Requirements

### 2.1 Compliance Mapping

| Regulation | Data Type | Minimum Retention | Maximum Retention |
|------------|-----------|-------------------|-------------------|
| **GDPR** | Personal data | Purpose duration | Purpose + reasonable |
| **CCPA** | Consumer data | 12 months (requests) | Business purpose |
| **HIPAA** | Health records | 6 years | State law varies |
| **PCI-DSS** | Cardholder data | 1 year (logs) | Minimize storage |
| **SOX** | Financial records | 7 years | No maximum |
| **Tax (IRS)** | Tax records | 3-7 years | No maximum |
| **Employment** | HR records | 3-7 years post-term | Varies by type |

### 2.2 Right to Erasure (GDPR Article 17)

```typescript
// TypeScript - GDPR erasure request handler
interface ErasureRequest {
  userId: string;
  requestedAt: Date;
  reason: ErasureReason;
  scope: 'all' | 'specific';
  specificData?: string[];
}

enum ErasureReason {
  CONSENT_WITHDRAWN = 'consent_withdrawn',
  DATA_NO_LONGER_NEEDED = 'data_no_longer_needed',
  OBJECTION_TO_PROCESSING = 'objection',
  UNLAWFUL_PROCESSING = 'unlawful',
  LEGAL_OBLIGATION = 'legal_obligation',
  CHILD_DATA = 'child_data',
}

class ErasureService {
  private readonly SLA_DAYS = 30; // GDPR requires response within 30 days
  
  async processErasureRequest(request: ErasureRequest): Promise<ErasureResult> {
    // Check for legal holds
    const legalHolds = await this.checkLegalHolds(request.userId);
    if (legalHolds.length > 0) {
      return {
        status: 'partial',
        message: 'Some data retained due to legal obligations',
        retainedData: legalHolds.map(h => h.dataType),
        deletedData: [],
      };
    }
    
    // Get all user data locations
    const dataLocations = await this.findAllUserData(request.userId);
    
    // Categorize by retention requirements
    const { deletable, mustRetain, canAnonymize } = 
      this.categorizeData(dataLocations);
    
    // Process deletions
    const deleted: string[] = [];
    const anonymized: string[] = [];
    
    for (const location of deletable) {
      await this.deleteData(location);
      deleted.push(location.table);
    }
    
    for (const location of canAnonymize) {
      await this.anonymizeData(location);
      anonymized.push(location.table);
    }
    
    // Log the erasure action
    await this.logErasure(request, deleted, anonymized, mustRetain);
    
    return {
      status: mustRetain.length > 0 ? 'partial' : 'complete',
      deletedData: deleted,
      anonymizedData: anonymized,
      retainedData: mustRetain.map(d => d.table),
      completedAt: new Date(),
    };
  }
  
  private categorizeData(locations: DataLocation[]): CategorizedData {
    const deletable: DataLocation[] = [];
    const mustRetain: DataLocation[] = [];
    const canAnonymize: DataLocation[] = [];
    
    for (const location of locations) {
      const policy = this.getRetentionPolicy(location.table);
      
      if (policy.legalRetention) {
        mustRetain.push(location);
      } else if (policy.allowAnonymization) {
        canAnonymize.push(location);
      } else {
        deletable.push(location);
      }
    }
    
    return { deletable, mustRetain, canAnonymize };
  }
}
```

---

## 3. Data Lifecycle Management

### 3.1 Lifecycle States

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  ACTIVE  │───▶│ ARCHIVED │───▶│  EXPIRED │───▶│ DELETED  │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
     │               │               │
     │               │               ▼
     │               │         ┌──────────┐
     └───────────────┴────────▶│ANONYMIZED│
                               └──────────┘
```

### 3.2 Lifecycle Configuration

```typescript
// TypeScript - Retention policy configuration
interface RetentionPolicy {
  table: string;
  category: RetentionCategory;
  retentionDays: number | null; // null = permanent
  archiveAfterDays?: number;
  postRetentionAction: 'delete' | 'anonymize' | 'archive';
  legalHoldExempt: boolean;
  gdprRelevant: boolean;
}

const RETENTION_POLICIES: RetentionPolicy[] = [
  {
    table: 'audit_log',
    category: 'PERMANENT',
    retentionDays: null,
    archiveAfterDays: 730, // 2 years
    postRetentionAction: 'archive',
    legalHoldExempt: false,
    gdprRelevant: false, // System logs, not personal data
  },
  {
    table: 'user',
    category: 'MEDIUM_TERM',
    retentionDays: 730, // 2 years after account deletion
    postRetentionAction: 'anonymize',
    legalHoldExempt: false,
    gdprRelevant: true,
  },
  {
    table: 'transaction',
    category: 'LONG_TERM',
    retentionDays: 2555, // 7 years
    archiveAfterDays: 365,
    postRetentionAction: 'archive',
    legalHoldExempt: false,
    gdprRelevant: true,
  },
  {
    table: 'session',
    category: 'SHORT_TERM',
    retentionDays: 30,
    postRetentionAction: 'delete',
    legalHoldExempt: true,
    gdprRelevant: false,
  },
  {
    table: 'password_reset_token',
    category: 'EPHEMERAL',
    retentionDays: 0, // Delete after use or 1 hour
    postRetentionAction: 'delete',
    legalHoldExempt: true,
    gdprRelevant: false,
  },
];
```

### 3.3 Database Schema for Retention

```sql
-- PostgreSQL: Retention tracking columns
ALTER TABLE user ADD COLUMN IF NOT EXISTS 
  deleted_at TIMESTAMPTZ DEFAULT NULL;

ALTER TABLE user ADD COLUMN IF NOT EXISTS 
  retention_expires_at TIMESTAMPTZ DEFAULT NULL;

ALTER TABLE user ADD COLUMN IF NOT EXISTS 
  anonymized_at TIMESTAMPTZ DEFAULT NULL;

-- Retention metadata table
CREATE TABLE retention_metadata (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    table_name VARCHAR(100) NOT NULL,
    record_id UUID NOT NULL,
    retention_category VARCHAR(20) NOT NULL,
    expires_at TIMESTAMPTZ,
    legal_hold BOOLEAN DEFAULT false,
    legal_hold_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    
    UNIQUE (table_name, record_id)
);

-- Index for retention queries
CREATE INDEX idx_retention_expires 
  ON retention_metadata(expires_at) 
  WHERE expires_at IS NOT NULL AND legal_hold = false;

-- Legal hold tracking
CREATE TABLE legal_hold (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    hold_name VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    affected_tables TEXT[] NOT NULL,
    affected_user_ids UUID[],
    start_date TIMESTAMPTZ NOT NULL DEFAULT now(),
    end_date TIMESTAMPTZ,
    created_by UUID REFERENCES user(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

---

## 4. Automated Retention Jobs

### 4.1 Retention Processor

```typescript
// TypeScript - Automated retention processor
class RetentionProcessor {
  private readonly BATCH_SIZE = 1000;
  
  async processExpiredRecords(): Promise<ProcessingResult> {
    const stats = {
      deleted: 0,
      anonymized: 0,
      archived: 0,
      skipped: 0,
      errors: 0,
    };
    
    for (const policy of RETENTION_POLICIES) {
      if (isNull(policy.retentionDays)) {
        continue; // Permanent retention
      }
      
      try {
        const result = await this.processTable(policy);
        stats.deleted += result.deleted;
        stats.anonymized += result.anonymized;
        stats.archived += result.archived;
        stats.skipped += result.skipped;
      } catch (error) {
        stats.errors++;
        await this.logError(policy.table, error);
      }
    }
    
    await this.logProcessingRun(stats);
    return stats;
  }
  
  private async processTable(policy: RetentionPolicy): Promise<TableResult> {
    const result = { deleted: 0, anonymized: 0, archived: 0, skipped: 0 };
    
    // Find expired records not under legal hold
    const expiredRecords = await this.findExpiredRecords(
      policy.table,
      policy.retentionDays,
      this.BATCH_SIZE
    );
    
    for (const record of expiredRecords) {
      // Check for legal holds
      const hasLegalHold = await this.checkLegalHold(policy.table, record.id);
      
      if (hasLegalHold) {
        result.skipped++;
        continue;
      }
      
      switch (policy.postRetentionAction) {
        case 'delete':
          await this.deleteRecord(policy.table, record.id);
          result.deleted++;
          break;
          
        case 'anonymize':
          await this.anonymizeRecord(policy.table, record.id);
          result.anonymized++;
          break;
          
        case 'archive':
          await this.archiveRecord(policy.table, record);
          result.archived++;
          break;
      }
    }
    
    return result;
  }
  
  private async findExpiredRecords(
    table: string,
    retentionDays: number,
    limit: number
  ): Promise<ExpiredRecord[]> {
    const expirationDate = new Date();
    expirationDate.setDate(expirationDate.getDate() - retentionDays);
    
    // Use the appropriate date column
    const dateColumn = await this.getRetentionDateColumn(table);
    
    return await db
      .selectFrom(table)
      .where(dateColumn, '<', expirationDate)
      .where('deleted_at', 'is not', null) // Only soft-deleted records
      .limit(limit)
      .execute();
  }
}

// Scheduled job configuration
const retentionJob = {
  name: 'retention-processor',
  schedule: '0 2 * * *', // Run at 2 AM daily
  handler: async () => {
    const processor = new RetentionProcessor();
    const result = await processor.processExpiredRecords();
    
    console.log('Retention processing complete:', result);
    
    // Alert if errors occurred
    if (result.errors > 0) {
      await alertService.send({
        severity: 'warning',
        title: 'Retention Processing Errors',
        message: `${result.errors} errors during retention processing`,
      });
    }
  },
};
```

### 4.2 Anonymization Strategies

```typescript
// TypeScript - Field anonymization strategies
interface AnonymizationStrategy {
  field: string;
  strategy: 'hash' | 'randomize' | 'null' | 'constant' | 'aggregate';
  options?: Record<string, any>;
}

const USER_ANONYMIZATION: AnonymizationStrategy[] = [
  { field: 'email', strategy: 'hash' },
  { field: 'first_name', strategy: 'constant', options: { value: 'ANONYMIZED' } },
  { field: 'last_name', strategy: 'constant', options: { value: 'USER' } },
  { field: 'phone', strategy: 'null' },
  { field: 'address', strategy: 'null' },
  { field: 'date_of_birth', strategy: 'aggregate', options: { granularity: 'year' } },
  { field: 'ip_address', strategy: 'hash' },
];

class AnonymizationService {
  async anonymizeRecord(table: string, recordId: string): Promise<void> {
    const strategies = this.getStrategies(table);
    const updates: Record<string, any> = {};
    
    for (const strategy of strategies) {
      updates[strategy.field] = await this.applyStrategy(
        strategy,
        await this.getFieldValue(table, recordId, strategy.field)
      );
    }
    
    updates['anonymized_at'] = new Date();
    
    await db
      .updateTable(table)
      .set(updates)
      .where('id', '=', recordId)
      .execute();
  }
  
  private async applyStrategy(
    strategy: AnonymizationStrategy,
    value: any
  ): Promise<any> {
    switch (strategy.strategy) {
      case 'hash':
        return this.hashValue(value);
        
      case 'randomize':
        return this.randomize(strategy.options?.type);
        
      case 'null':
        return null;
        
      case 'constant':
        return strategy.options?.value;
        
      case 'aggregate':
        return this.aggregate(value, strategy.options?.granularity);
        
      default:
        throw new Error(`Unknown strategy: ${strategy.strategy}`);
    }
  }
  
  private hashValue(value: string): string {
    // One-way hash - cannot be reversed
    return crypto
      .createHash('sha256')
      .update(value + process.env.ANONYMIZATION_SALT)
      .digest('hex')
      .substring(0, 16);
  }
  
  private aggregate(date: Date, granularity: string): Date {
    const result = new Date(date);
    
    switch (granularity) {
      case 'year':
        result.setMonth(0, 1);
        result.setHours(0, 0, 0, 0);
        break;
      case 'month':
        result.setDate(1);
        result.setHours(0, 0, 0, 0);
        break;
    }
    
    return result;
  }
}
```

---

## 5. Archival Strategies

### 5.1 Archive Configuration

```typescript
// TypeScript - Archive service configuration
interface ArchiveConfig {
  table: string;
  archiveAfterDays: number;
  archiveDestination: 'cold_storage' | 's3_glacier' | 'separate_db';
  compressionEnabled: boolean;
  encryptionRequired: boolean;
  indexesPreserved: string[];
}

const ARCHIVE_CONFIGS: ArchiveConfig[] = [
  {
    table: 'audit_log',
    archiveAfterDays: 730,
    archiveDestination: 's3_glacier',
    compressionEnabled: true,
    encryptionRequired: true,
    indexesPreserved: ['user_id', 'action', 'created_at'],
  },
  {
    table: 'transaction',
    archiveAfterDays: 365,
    archiveDestination: 'cold_storage',
    compressionEnabled: true,
    encryptionRequired: true,
    indexesPreserved: ['user_id', 'created_at', 'status'],
  },
];

class ArchiveService {
  async archiveOldRecords(config: ArchiveConfig): Promise<ArchiveResult> {
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - config.archiveAfterDays);
    
    // Select records to archive
    const records = await db
      .selectFrom(config.table)
      .where('created_at', '<', cutoffDate)
      .where('archived_at', 'is', null)
      .limit(10000)
      .execute();
    
    if (records.length === 0) {
      return { archived: 0 };
    }
    
    // Create archive batch
    const batch = {
      table: config.table,
      recordCount: records.length,
      dateRange: {
        from: records[records.length - 1].created_at,
        to: records[0].created_at,
      },
      archivedAt: new Date(),
    };
    
    // Compress and encrypt
    let data = JSON.stringify(records);
    
    if (config.compressionEnabled) {
      data = await this.compress(data);
    }
    
    if (config.encryptionRequired) {
      data = await this.encrypt(data);
    }
    
    // Store in archive destination
    const archivePath = await this.storeArchive(
      config.archiveDestination,
      config.table,
      batch,
      data
    );
    
    // Mark records as archived (or delete from hot storage)
    await this.markArchived(config.table, records.map(r => r.id), archivePath);
    
    return {
      archived: records.length,
      archivePath,
      compressedSize: data.length,
    };
  }
}
```

### 5.2 Archive Retrieval

```typescript
// TypeScript - Archive retrieval for compliance requests
class ArchiveRetrievalService {
  async retrieveArchivedData(
    table: string,
    userId: string,
    dateRange?: DateRange
  ): Promise<ArchivedData[]> {
    // Find relevant archive batches
    const batches = await db
      .selectFrom('archive_batch')
      .where('table_name', '=', table)
      .where(qb => {
        if (isNotNullish(dateRange)) {
          return qb
            .where('date_from', '<=', dateRange.to)
            .where('date_to', '>=', dateRange.from);
        }
        return qb;
      })
      .execute();
    
    const results: ArchivedData[] = [];
    
    for (const batch of batches) {
      // Request retrieval from cold storage (may take hours for Glacier)
      const data = await this.requestRetrieval(batch.archive_path);
      
      // Decrypt and decompress
      let decrypted = await this.decrypt(data);
      let decompressed = await this.decompress(decrypted);
      
      // Parse and filter for user
      const records = JSON.parse(decompressed);
      const userRecords = records.filter(
        (r: any) => r.user_id === userId
      );
      
      results.push(...userRecords);
    }
    
    return results;
  }
}
```

---

## 6. Retention Reporting

### 6.1 Compliance Dashboard

```typescript
// TypeScript - Retention compliance metrics
interface RetentionMetrics {
  table: string;
  totalRecords: number;
  activeRecords: number;
  expiredPendingDeletion: number;
  archivedRecords: number;
  anonymizedRecords: number;
  underLegalHold: number;
  complianceStatus: 'compliant' | 'at_risk' | 'non_compliant';
}

class RetentionReportingService {
  async generateComplianceReport(): Promise<ComplianceReport> {
    const metrics: RetentionMetrics[] = [];
    
    for (const policy of RETENTION_POLICIES) {
      const tableMetrics = await this.getTableMetrics(policy);
      metrics.push(tableMetrics);
    }
    
    // Calculate overall compliance
    const nonCompliantTables = metrics.filter(
      m => m.complianceStatus === 'non_compliant'
    );
    
    return {
      generatedAt: new Date(),
      overallStatus: nonCompliantTables.length === 0 ? 'compliant' : 'non_compliant',
      tableMetrics: metrics,
      recommendations: this.generateRecommendations(metrics),
      nextReviewDate: this.calculateNextReview(),
    };
  }
  
  private async getTableMetrics(policy: RetentionPolicy): Promise<RetentionMetrics> {
    const stats = await db
      .selectFrom(policy.table)
      .select([
        db.fn.count('id').as('total'),
        db.fn.count(db.case()
          .when('deleted_at', 'is', null).then(1)
          .end()
        ).as('active'),
        db.fn.count(db.case()
          .when('retention_expires_at', '<', new Date())
          .when('deleted_at', 'is not', null)
          .then(1)
          .end()
        ).as('expired_pending'),
      ])
      .executeTakeFirst();
    
    const legalHoldCount = await this.countLegalHolds(policy.table);
    
    // Determine compliance status
    let status: 'compliant' | 'at_risk' | 'non_compliant' = 'compliant';
    
    if (stats.expired_pending > 1000) {
      status = 'non_compliant';
    } else if (stats.expired_pending > 100) {
      status = 'at_risk';
    }
    
    return {
      table: policy.table,
      totalRecords: stats.total,
      activeRecords: stats.active,
      expiredPendingDeletion: stats.expired_pending,
      archivedRecords: 0, // Query archive metadata
      anonymizedRecords: 0, // Query anonymized count
      underLegalHold: legalHoldCount,
      complianceStatus: status,
    };
  }
}
```

---

## 7. Anti-Patterns

### ❌ INCORRECT - No Retention Strategy

```typescript
// Storing data indefinitely
await db.users.insert(userData);
// Never deleted, never archived, grows forever

// Manual, ad-hoc deletion
await db.users.deleteMany({ 
  createdAt: { lt: oneYearAgo } 
});
// No audit trail, no legal hold check
```

### ✅ CORRECT - Proper Retention Management

```typescript
// Insert with retention metadata
await db.users.insert({
  ...userData,
  retention_category: 'MEDIUM_TERM',
  retention_expires_at: addYears(new Date(), 2),
});

// Automated deletion with proper checks
const processor = new RetentionProcessor();
await processor.processExpiredRecords();
// Respects legal holds, logs all actions, anonymizes where required
```

---

## 8. Mandatory Checklist

- [ ] Retention policies defined for all tables
- [ ] Legal hold mechanism implemented
- [ ] GDPR erasure request handler implemented
- [ ] Automated retention processor scheduled
- [ ] Anonymization strategies defined for PII
- [ ] Archive strategy for long-term data
- [ ] Compliance reporting dashboard available
- [ ] Retention policies reviewed (annually)

---

*Retention policies must be reviewed annually and updated for regulatory changes.*
