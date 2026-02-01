# Backup and Recovery Procedures

> Version: 1.0.0 | Last Updated: 2026-01-26

## Overview

This document defines backup strategies, disaster recovery procedures, and business continuity requirements to ensure data durability and minimize downtime during incidents.

---

## 1. Backup Strategy Framework

### 1.1 The 3-2-1 Rule

```
┌─────────────────────────────────────────────────────────────┐
│                    3-2-1 BACKUP RULE                        │
├─────────────────────────────────────────────────────────────┤
│  3  │  Keep at least THREE copies of data                   │
│     │  (1 primary + 2 backups)                              │
├─────────────────────────────────────────────────────────────┤
│  2  │  Store backups on TWO different media types           │
│     │  (SSD + Object Storage, or different cloud providers) │
├─────────────────────────────────────────────────────────────┤
│  1  │  Keep ONE copy offsite/off-region                     │
│     │  (Different datacenter or cloud region)               │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Backup Types

| Type | Description | Frequency | Use Case |
|------|-------------|-----------|----------|
| **Full** | Complete copy of all data | Weekly | Baseline restore point |
| **Incremental** | Changes since last backup | Hourly | Minimize storage, fast backup |
| **Differential** | Changes since last full | Daily | Balance of speed and storage |
| **Continuous (PITR)** | Transaction log streaming | Real-time | Zero data loss recovery |
| **Snapshot** | Point-in-time volume copy | On-demand | Pre-deployment safety |

### 1.3 Backup Schedule Matrix

| Data Type | Full | Incremental | Retention | Offsite Sync |
|-----------|------|-------------|-----------|--------------|
| Production DB | Sunday 2AM | Hourly | 30 days | 6 hours |
| User uploads | Saturday 3AM | Daily | 90 days | 24 hours |
| Application code | Per deploy | N/A | Unlimited | Immediate |
| Configurations | Daily 4AM | On change | 90 days | 1 hour |
| Audit logs | Weekly | Daily | 7 years | 24 hours |

---

## 2. Recovery Objectives

### 2.1 RTO and RPO Definitions

```
┌─────────────────────────────────────────────────────────────┐
│  RPO (Recovery Point Objective)                             │
│  "How much data can we afford to lose?"                     │
│  ═══════════════════════════════════════════════════════    │
│  Time: [Last Backup]═════════════════════════[Disaster]     │
│                      ←─── Data Loss Window ───→             │
├─────────────────────────────────────────────────────────────┤
│  RTO (Recovery Time Objective)                              │
│  "How long until we're back online?"                        │
│  ═══════════════════════════════════════════════════════    │
│  Time: [Disaster]════════════════════════════[Recovered]    │
│                    ←─── Downtime Window ────→               │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Tier-Based Recovery Objectives

| Tier | Systems | RPO | RTO | Backup Strategy |
|------|---------|-----|-----|-----------------|
| **Tier 1: Critical** | Auth, Payments, Core API | 1 minute | 15 minutes | Multi-region PITR |
| **Tier 2: Important** | User data, Orders | 1 hour | 4 hours | Hourly incremental |
| **Tier 3: Standard** | Analytics, Logs | 24 hours | 24 hours | Daily differential |
| **Tier 4: Low** | Dev/Test envs | 7 days | 72 hours | Weekly full only |

---

## 3. Database Backup Implementation

### 3.1 PostgreSQL Backup Configuration

```sql
-- Enable WAL archiving for PITR
ALTER SYSTEM SET wal_level = 'replica';
ALTER SYSTEM SET archive_mode = 'on';
ALTER SYSTEM SET archive_command = 
  'aws s3 cp %p s3://backups-bucket/wal/%f --sse AES256';

-- Recommended WAL settings
ALTER SYSTEM SET max_wal_size = '2GB';
ALTER SYSTEM SET min_wal_size = '1GB';
ALTER SYSTEM SET wal_keep_size = '1GB';
```

### 3.2 Backup Scripts

```typescript
// TypeScript - Backup orchestration service
interface BackupJob {
  id: string;
  type: 'full' | 'incremental' | 'differential';
  database: string;
  startedAt: Date;
  completedAt?: Date;
  status: 'running' | 'completed' | 'failed';
  sizeBytes?: number;
  location?: string;
  encryptionKeyId?: string;
  checksumSha256?: string;
}

class DatabaseBackupService {
  private readonly BACKUP_BUCKET = process.env.BACKUP_BUCKET;
  private readonly ENCRYPTION_KEY_ID = process.env.BACKUP_KEY_ID;
  
  async createFullBackup(database: string): Promise<BackupJob> {
    const job = await this.createBackupJob('full', database);
    
    try {
      // Create pg_dump with compression
      const dumpFile = await this.executePgDump(database, {
        format: 'custom',
        compress: 9,
        jobs: 4, // Parallel dump
      });
      
      // Encrypt backup
      const encryptedFile = await this.encryptBackup(dumpFile);
      
      // Calculate checksum
      const checksum = await this.calculateChecksum(encryptedFile);
      
      // Upload to primary and secondary storage
      const primaryLocation = await this.uploadToS3(
        encryptedFile,
        `${database}/full/${job.id}.backup.enc`
      );
      
      // Replicate to secondary region
      await this.replicateToSecondary(primaryLocation);
      
      // Update job status
      return await this.completeBackupJob(job.id, {
        sizeBytes: await this.getFileSize(encryptedFile),
        location: primaryLocation,
        checksumSha256: checksum,
      });
      
    } catch (error) {
      await this.failBackupJob(job.id, error);
      throw error;
    }
  }
  
  async createIncrementalBackup(database: string): Promise<BackupJob> {
    const lastBackup = await this.getLastBackup(database);
    const job = await this.createBackupJob('incremental', database);
    
    try {
      // Use pg_basebackup with WAL streaming for incremental
      const walFiles = await this.collectWALSince(lastBackup.completedAt);
      
      // Archive WAL files
      for (const wal of walFiles) {
        await this.archiveWAL(wal);
      }
      
      return await this.completeBackupJob(job.id, {
        sizeBytes: walFiles.reduce((sum, w) => sum + w.size, 0),
        location: `${database}/wal/${job.id}/`,
      });
      
    } catch (error) {
      await this.failBackupJob(job.id, error);
      throw error;
    }
  }
  
  private async executePgDump(
    database: string,
    options: PgDumpOptions
  ): Promise<string> {
    const outputFile = `/tmp/backup_${Date.now()}.dump`;
    
    const command = [
      'pg_dump',
      `--dbname=${database}`,
      `--format=${options.format}`,
      `--compress=${options.compress}`,
      `--jobs=${options.jobs}`,
      `--file=${outputFile}`,
      '--verbose',
      '--no-password',
    ];
    
    await exec(command.join(' '));
    return outputFile;
  }
  
  private async encryptBackup(filePath: string): Promise<string> {
    const encryptedPath = `${filePath}.enc`;
    
    // Use AWS KMS envelope encryption
    const dataKey = await this.generateDataKey();
    
    await exec(`openssl enc -aes-256-gcm \
      -in ${filePath} \
      -out ${encryptedPath} \
      -K ${dataKey.plaintext} \
      -iv ${this.generateIV()}`);
    
    // Store encrypted data key with backup
    await this.storeDataKeyMetadata(encryptedPath, dataKey.encrypted);
    
    return encryptedPath;
  }
}
```

### 3.3 Backup Verification

```typescript
// TypeScript - Backup verification service
class BackupVerificationService {
  async verifyBackup(backupJob: BackupJob): Promise<VerificationResult> {
    const checks: VerificationCheck[] = [];
    
    // 1. Verify file exists
    const fileExists = await this.checkFileExists(backupJob.location);
    checks.push({
      name: 'file_exists',
      passed: fileExists,
      message: fileExists ? 'Backup file found' : 'Backup file missing',
    });
    
    // 2. Verify checksum
    const currentChecksum = await this.calculateRemoteChecksum(backupJob.location);
    const checksumValid = currentChecksum === backupJob.checksumSha256;
    checks.push({
      name: 'checksum_valid',
      passed: checksumValid,
      message: checksumValid ? 'Checksum matches' : 'Checksum mismatch - corrupted',
    });
    
    // 3. Verify decryption works
    const decryptable = await this.testDecryption(backupJob.location);
    checks.push({
      name: 'decryptable',
      passed: decryptable,
      message: decryptable ? 'Decryption successful' : 'Decryption failed',
    });
    
    // 4. Verify restore to test database (weekly)
    if (this.shouldRunRestoreTest()) {
      const restoreResult = await this.testRestore(backupJob);
      checks.push({
        name: 'restore_test',
        passed: restoreResult.success,
        message: restoreResult.message,
        duration: restoreResult.durationMs,
      });
    }
    
    // 5. Verify offsite replication
    const replicaExists = await this.checkReplicaExists(backupJob);
    checks.push({
      name: 'offsite_replica',
      passed: replicaExists,
      message: replicaExists ? 'Offsite replica verified' : 'Offsite replica missing',
    });
    
    const allPassed = checks.every(c => c.passed);
    
    // Alert on failures
    if (isNotEqual(allPassed, true)) {
      await this.alertBackupFailure(backupJob, checks);
    }
    
    return {
      backupId: backupJob.id,
      verifiedAt: new Date(),
      allChecksPassed: allPassed,
      checks,
    };
  }
  
  private async testRestore(backupJob: BackupJob): Promise<RestoreTestResult> {
    const testDb = `restore_test_${Date.now()}`;
    const startTime = Date.now();
    
    try {
      // Create isolated test database
      await this.createTestDatabase(testDb);
      
      // Download and decrypt backup
      const localFile = await this.downloadAndDecrypt(backupJob.location);
      
      // Restore to test database
      await exec(`pg_restore \
        --dbname=${testDb} \
        --jobs=4 \
        --verbose \
        ${localFile}`);
      
      // Run integrity checks
      const integrityOk = await this.runIntegrityChecks(testDb);
      
      // Cleanup
      await this.dropTestDatabase(testDb);
      
      return {
        success: integrityOk,
        message: integrityOk ? 'Restore test passed' : 'Integrity check failed',
        durationMs: Date.now() - startTime,
      };
      
    } catch (error) {
      await this.dropTestDatabase(testDb);
      return {
        success: false,
        message: `Restore failed: ${error.message}`,
        durationMs: Date.now() - startTime,
      };
    }
  }
}
```

---

## 4. Disaster Recovery Procedures

### 4.1 Recovery Runbook

```markdown
# Database Recovery Runbook

## Pre-Recovery Checklist
- [ ] Identify incident severity (Tier 1-4)
- [ ] Notify incident commander and stakeholders
- [ ] Determine recovery point (latest valid backup)
- [ ] Prepare target infrastructure
- [ ] Verify backup integrity before restoration

## Recovery Steps

### Step 1: Prepare Environment
1. Create new database instance (or verify existing standby)
2. Ensure network connectivity
3. Prepare storage with sufficient capacity
4. Gather backup encryption keys

### Step 2: Download Backup
```bash
aws s3 cp s3://backups-bucket/production/full/latest.backup.enc ./
```

### Step 3: Decrypt Backup
```bash
./decrypt-backup.sh latest.backup.enc latest.backup
```

### Step 4: Restore Database
```bash
pg_restore \
  --dbname=production_restored \
  --jobs=4 \
  --verbose \
  --clean \
  --if-exists \
  latest.backup
```

### Step 5: Apply WAL for PITR (if needed)
```bash
# Restore to specific point in time
pg_restore --target-time="2026-01-26 14:30:00 UTC" ...
```

### Step 6: Verify Data Integrity
```sql
-- Run integrity checks
SELECT COUNT(*) FROM critical_table;
SELECT MAX(created_at) FROM audit_log;
-- Compare with expected values
```

### Step 7: Switch Traffic
1. Update DNS/Load balancer to new database
2. Restart application servers
3. Clear caches
4. Monitor for errors

## Post-Recovery
- [ ] Document incident timeline
- [ ] Update backup verification schedule
- [ ] Review and improve procedures
- [ ] Notify stakeholders of resolution
```

### 4.2 Automated Recovery Service

```typescript
// TypeScript - Disaster recovery orchestrator
interface RecoveryPlan {
  targetRPO: Date;
  targetDatabase: string;
  backupsToApply: BackupJob[];
  estimatedDuration: number;
  status: 'planning' | 'executing' | 'verifying' | 'complete' | 'failed';
}

class DisasterRecoveryService {
  async initiateRecovery(
    database: string,
    targetTime?: Date
  ): Promise<RecoveryPlan> {
    // 1. Find applicable backups
    const backups = await this.findBackupsForRecovery(database, targetTime);
    
    if (backups.length === 0) {
      throw new RecoveryError('No valid backups found for recovery');
    }
    
    // 2. Create recovery plan
    const plan: RecoveryPlan = {
      targetRPO: targetTime ?? new Date(),
      targetDatabase: `${database}_recovered_${Date.now()}`,
      backupsToApply: backups,
      estimatedDuration: this.estimateRecoveryTime(backups),
      status: 'planning',
    };
    
    await this.savePlan(plan);
    await this.notifyStakeholders(plan);
    
    // 3. Execute recovery
    try {
      plan.status = 'executing';
      await this.savePlan(plan);
      
      // Create target database
      await this.createTargetDatabase(plan.targetDatabase);
      
      // Apply full backup first
      const fullBackup = backups.find(b => b.type === 'full');
      await this.applyFullBackup(fullBackup, plan.targetDatabase);
      
      // Apply incremental backups in order
      const incrementals = backups
        .filter(b => b.type === 'incremental')
        .sort((a, b) => a.startedAt.getTime() - b.startedAt.getTime());
      
      for (const backup of incrementals) {
        await this.applyIncrementalBackup(backup, plan.targetDatabase);
      }
      
      // Apply PITR if specific time requested
      if (isNotNullish(targetTime)) {
        await this.applyPITR(plan.targetDatabase, targetTime);
      }
      
      // 4. Verify recovery
      plan.status = 'verifying';
      await this.savePlan(plan);
      
      const verification = await this.verifyRecoveredDatabase(plan.targetDatabase);
      
      if (isFalse(verification.success)) {
        throw new RecoveryError(`Verification failed: ${verification.errors.join(', ')}`);
      }
      
      plan.status = 'complete';
      await this.savePlan(plan);
      
      return plan;
      
    } catch (error) {
      plan.status = 'failed';
      await this.savePlan(plan);
      await this.alertRecoveryFailure(plan, error);
      throw error;
    }
  }
  
  private async findBackupsForRecovery(
    database: string,
    targetTime?: Date
  ): Promise<BackupJob[]> {
    // Find the most recent full backup before target time
    const fullBackup = await db
      .selectFrom('backup_job')
      .where('database', '=', database)
      .where('type', '=', 'full')
      .where('status', '=', 'completed')
      .where('completed_at', '<=', targetTime ?? new Date())
      .orderBy('completed_at', 'desc')
      .executeTakeFirst();
    
    if (isNull(fullBackup)) {
      return [];
    }
    
    // Find incremental backups between full and target
    const incrementals = await db
      .selectFrom('backup_job')
      .where('database', '=', database)
      .where('type', '=', 'incremental')
      .where('status', '=', 'completed')
      .where('completed_at', '>', fullBackup.completedAt)
      .where('completed_at', '<=', targetTime ?? new Date())
      .orderBy('completed_at', 'asc')
      .execute();
    
    return [fullBackup, ...incrementals];
  }
}
```

---

## 5. High Availability Configuration

### 5.1 Database Replication

```typescript
// TypeScript - HA configuration
interface HAConfig {
  primaryRegion: string;
  replicaRegions: string[];
  replicationMode: 'sync' | 'async';
  failoverPolicy: 'automatic' | 'manual';
  healthCheckInterval: number;
  failoverThreshold: number;
}

const HA_CONFIG: HAConfig = {
  primaryRegion: 'us-east-1',
  replicaRegions: ['us-west-2', 'eu-west-1'],
  replicationMode: 'async', // Sync for zero data loss, async for performance
  failoverPolicy: 'automatic',
  healthCheckInterval: 5000, // 5 seconds
  failoverThreshold: 3, // 3 failed checks before failover
};

class HAManager {
  private failedChecks = 0;
  
  async monitorPrimary(): Promise<void> {
    setInterval(async () => {
      const healthy = await this.checkPrimaryHealth();
      
      if (isFalse(healthy)) {
        this.failedChecks++;
        
        if (this.failedChecks >= HA_CONFIG.failoverThreshold) {
          await this.initiateFailover();
        }
      } else {
        this.failedChecks = 0;
      }
    }, HA_CONFIG.healthCheckInterval);
  }
  
  private async checkPrimaryHealth(): Promise<boolean> {
    try {
      // Check database connectivity
      await db.raw('SELECT 1');
      
      // Check replication lag
      const lag = await this.getReplicationLag();
      if (lag > 60) { // More than 60 seconds behind
        console.warn(`High replication lag: ${lag}s`);
      }
      
      return true;
    } catch (error) {
      console.error('Primary health check failed:', error);
      return false;
    }
  }
  
  private async initiateFailover(): Promise<void> {
    console.log('Initiating automatic failover...');
    
    // 1. Select best replica
    const bestReplica = await this.selectBestReplica();
    
    // 2. Promote replica to primary
    await this.promoteReplica(bestReplica);
    
    // 3. Update connection strings
    await this.updateConnectionStrings(bestReplica);
    
    // 4. Notify all services to reconnect
    await this.broadcastReconnect();
    
    // 5. Alert operations team
    await this.alertFailoverComplete(bestReplica);
  }
  
  private async selectBestReplica(): Promise<string> {
    const replicas = await Promise.all(
      HA_CONFIG.replicaRegions.map(async (region) => {
        const lag = await this.getReplicaLag(region);
        const healthy = await this.checkReplicaHealth(region);
        return { region, lag, healthy };
      })
    );
    
    // Select healthiest replica with lowest lag
    const eligible = replicas
      .filter(r => r.healthy)
      .sort((a, b) => a.lag - b.lag);
    
    if (eligible.length === 0) {
      throw new Error('No healthy replicas available for failover');
    }
    
    return eligible[0].region;
  }
}
```

### 5.2 Multi-Region Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    MULTI-REGION ARCHITECTURE                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   ┌─────────────┐         ┌─────────────┐                  │
│   │  us-east-1  │◄───────►│  us-west-2  │                  │
│   │  (PRIMARY)  │  Sync   │  (STANDBY)  │                  │
│   └──────┬──────┘         └──────┬──────┘                  │
│          │                       │                         │
│          │ Async                 │ Async                   │
│          ▼                       ▼                         │
│   ┌─────────────┐         ┌─────────────┐                  │
│   │  eu-west-1  │         │ Backup S3   │                  │
│   │  (READ)     │         │ (All regions)│                  │
│   └─────────────┘         └─────────────┘                  │
│                                                             │
│   Traffic Flow:                                             │
│   • Writes → us-east-1 (primary)                           │
│   • Reads → Nearest region (latency-based)                 │
│   • Failover → Automatic to us-west-2                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 6. Backup Monitoring

### 6.1 Backup Health Metrics

```typescript
// TypeScript - Backup monitoring metrics
interface BackupMetrics {
  lastFullBackupAge: number;        // Hours since last full backup
  lastIncrementalAge: number;       // Minutes since last incremental
  backupSuccessRate: number;        // Percentage (30-day rolling)
  averageBackupDuration: number;    // Minutes
  totalBackupSize: number;          // GB
  replicationLag: number;           // Seconds
  oldestRecoverablePoint: Date;     // Earliest PITR possible
}

class BackupMonitoringService {
  async collectMetrics(): Promise<BackupMetrics> {
    const now = new Date();
    
    const lastFull = await this.getLastBackup('full');
    const lastIncremental = await this.getLastBackup('incremental');
    
    const metrics: BackupMetrics = {
      lastFullBackupAge: this.hoursSince(lastFull?.completedAt),
      lastIncrementalAge: this.minutesSince(lastIncremental?.completedAt),
      backupSuccessRate: await this.calculateSuccessRate(30),
      averageBackupDuration: await this.calculateAverageDuration(30),
      totalBackupSize: await this.calculateTotalSize(),
      replicationLag: await this.getReplicationLag(),
      oldestRecoverablePoint: await this.getOldestRecoverablePoint(),
    };
    
    // Check thresholds and alert
    await this.checkThresholds(metrics);
    
    return metrics;
  }
  
  private async checkThresholds(metrics: BackupMetrics): Promise<void> {
    const alerts: Alert[] = [];
    
    // Full backup too old (>48 hours)
    if (metrics.lastFullBackupAge > 48) {
      alerts.push({
        severity: 'critical',
        title: 'Full backup overdue',
        message: `Last full backup was ${metrics.lastFullBackupAge} hours ago`,
      });
    }
    
    // Incremental backup too old (>2 hours)
    if (metrics.lastIncrementalAge > 120) {
      alerts.push({
        severity: 'warning',
        title: 'Incremental backup overdue',
        message: `Last incremental backup was ${metrics.lastIncrementalAge} minutes ago`,
      });
    }
    
    // Low success rate
    if (metrics.backupSuccessRate < 95) {
      alerts.push({
        severity: 'warning',
        title: 'Backup success rate degraded',
        message: `30-day success rate is ${metrics.backupSuccessRate}%`,
      });
    }
    
    // High replication lag
    if (metrics.replicationLag > 300) {
      alerts.push({
        severity: 'critical',
        title: 'High replication lag',
        message: `Replication is ${metrics.replicationLag} seconds behind`,
      });
    }
    
    for (const alert of alerts) {
      await this.sendAlert(alert);
    }
  }
}
```

### 6.2 Dashboard Queries

```sql
-- PostgreSQL: Backup health dashboard queries

-- Last successful backup by type
SELECT 
    type,
    MAX(completed_at) as last_backup,
    EXTRACT(EPOCH FROM (NOW() - MAX(completed_at))) / 3600 as hours_ago
FROM backup_job
WHERE status = 'completed'
GROUP BY type;

-- Backup success rate (30 days)
SELECT 
    COUNT(*) FILTER (WHERE status = 'completed') * 100.0 / COUNT(*) as success_rate,
    COUNT(*) FILTER (WHERE status = 'completed') as successful,
    COUNT(*) FILTER (WHERE status = 'failed') as failed
FROM backup_job
WHERE started_at > NOW() - INTERVAL '30 days';

-- Daily backup size trend
SELECT 
    DATE(completed_at) as backup_date,
    SUM(size_bytes) / 1024 / 1024 / 1024 as size_gb,
    COUNT(*) as backup_count
FROM backup_job
WHERE status = 'completed'
    AND completed_at > NOW() - INTERVAL '30 days'
GROUP BY DATE(completed_at)
ORDER BY backup_date;

-- Recovery point coverage
SELECT 
    MIN(completed_at) as oldest_recoverable,
    MAX(completed_at) as newest_recoverable,
    COUNT(*) as total_backups
FROM backup_job
WHERE status = 'completed'
    AND completed_at > NOW() - INTERVAL '30 days';
```

---

## 7. Recovery Testing

### 7.1 Test Schedule

| Test Type | Frequency | Scope | Success Criteria |
|-----------|-----------|-------|------------------|
| Backup verification | Daily | Checksum, decryption | 100% pass |
| Table restore | Weekly | Single table | <15 min, data intact |
| Full restore | Monthly | Complete database | Meets RTO, data intact |
| Failover drill | Quarterly | HA failover | <15 min, zero data loss |
| DR simulation | Annually | Full disaster scenario | Meets RPO/RTO |

### 7.2 Automated DR Testing

```typescript
// TypeScript - Automated DR test runner
class DRTestRunner {
  async runMonthlyDRTest(): Promise<DRTestResult> {
    const testId = `dr_test_${Date.now()}`;
    const results: TestStep[] = [];
    
    try {
      // 1. Create isolated test environment
      results.push(await this.runStep('create_test_env', async () => {
        await this.createTestEnvironment(testId);
      }));
      
      // 2. Simulate primary failure
      results.push(await this.runStep('simulate_failure', async () => {
        await this.simulatePrimaryFailure();
      }));
      
      // 3. Execute recovery procedure
      const recoveryStart = Date.now();
      results.push(await this.runStep('execute_recovery', async () => {
        await this.executeRecoveryProcedure(testId);
      }));
      const recoveryDuration = Date.now() - recoveryStart;
      
      // 4. Verify data integrity
      results.push(await this.runStep('verify_integrity', async () => {
        await this.verifyDataIntegrity(testId);
      }));
      
      // 5. Verify application connectivity
      results.push(await this.runStep('verify_app_connectivity', async () => {
        await this.verifyApplicationConnectivity(testId);
      }));
      
      // 6. Calculate metrics
      const meetsRTO = recoveryDuration < this.getRTOTarget() * 60 * 1000;
      const dataLoss = await this.calculateDataLoss(testId);
      const meetsRPO = dataLoss < this.getRPOTarget();
      
      // 7. Cleanup
      results.push(await this.runStep('cleanup', async () => {
        await this.cleanupTestEnvironment(testId);
      }));
      
      const testResult: DRTestResult = {
        testId,
        executedAt: new Date(),
        allStepsPassed: results.every(r => r.passed),
        steps: results,
        recoveryDurationMs: recoveryDuration,
        meetsRTO,
        dataLossSeconds: dataLoss,
        meetsRPO,
        recommendations: this.generateRecommendations(results, recoveryDuration, dataLoss),
      };
      
      await this.saveTestResult(testResult);
      await this.notifyTestComplete(testResult);
      
      return testResult;
      
    } catch (error) {
      // Ensure cleanup even on failure
      await this.cleanupTestEnvironment(testId);
      throw error;
    }
  }
}
```

---

## 8. Anti-Patterns

### ❌ INCORRECT - Inadequate Backup Strategy

```typescript
// Single backup location
await backup.save('/local/backups/db.sql');

// No encryption
await s3.upload({ Body: rawBackupFile });

// No verification
await backup.create();
// Hope it worked...

// Manual recovery only
// "We'll figure it out when we need it"
```

### ✅ CORRECT - Comprehensive Backup Strategy

```typescript
// 3-2-1 backup with encryption
const backup = await backupService.createFullBackup(database);

// Verify backup integrity
const verification = await verificationService.verifyBackup(backup);
if (isFalse(verification.allChecksPassed)) {
  await alertService.send({ severity: 'critical', message: 'Backup verification failed' });
}

// Replicate to secondary region
await backupService.replicateToSecondary(backup);

// Test recovery monthly
await drTestRunner.runMonthlyDRTest();
```

---

## 9. Mandatory Checklist

- [ ] 3-2-1 backup strategy implemented
- [ ] Encryption enabled for all backups
- [ ] Automated backup schedule configured
- [ ] Backup verification running daily
- [ ] Recovery runbook documented and tested
- [ ] RPO/RTO targets defined and met
- [ ] Offsite/cross-region replication active
- [ ] Monthly recovery test passing
- [ ] Alerting configured for backup failures
- [ ] DR simulation completed (annual)

---

*Backup and recovery procedures must be tested monthly and fully simulated annually.*
