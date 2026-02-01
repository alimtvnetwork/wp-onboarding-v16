# Data Classification Standards

> Version: 1.0.0 | Last Updated: 2026-01-26

## Overview

This document defines data classification standards for categorizing, handling, and protecting data assets based on sensitivity and regulatory requirements.

---

## 1. Classification Levels

### 1.1 Four-Tier Classification

```
┌─────────────────────────────────────────────────────────────┐
│  Level 4: RESTRICTED (Red)                                  │
│  • Payment credentials, encryption keys, auth secrets       │
│  • Impact: Critical - Business failure, legal liability     │
├─────────────────────────────────────────────────────────────┤
│  Level 3: CONFIDENTIAL (Orange)                             │
│  • PII, health data, financial records, passwords          │
│  • Impact: Severe - Regulatory fines, reputation damage    │
├─────────────────────────────────────────────────────────────┤
│  Level 2: INTERNAL (Yellow)                                 │
│  • Internal docs, non-public business data, analytics      │
│  • Impact: Moderate - Competitive disadvantage             │
├─────────────────────────────────────────────────────────────┤
│  Level 1: PUBLIC (Green)                                    │
│  • Marketing materials, public APIs, published content     │
│  • Impact: Minimal - No harm from disclosure               │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Classification Matrix

| Level | Label | Encryption | Access Control | Audit | Examples |
|-------|-------|------------|----------------|-------|----------|
| 4 | RESTRICTED | AES-256 at rest + TLS 1.3 in transit | MFA + Role + Approval | Real-time | API keys, secrets, credit cards |
| 3 | CONFIDENTIAL | AES-256 at rest + TLS 1.3 in transit | Role-based | Daily | Email, SSN, health records |
| 2 | INTERNAL | Optional at rest + TLS in transit | Authenticated users | Weekly | Internal reports, metrics |
| 1 | PUBLIC | TLS in transit only | None | Monthly | Blog posts, docs, marketing |

---

## 2. Data Categories

### 2.1 Personally Identifiable Information (PII)

```typescript
// TypeScript - PII field detection
const PII_FIELDS = {
  // Direct Identifiers (Level 3-4)
  DIRECT: [
    'ssn', 'social_security_number',
    'passport_number', 'drivers_license',
    'national_id', 'tax_id',
    'credit_card_number', 'bank_account',
  ],
  
  // Indirect Identifiers (Level 2-3)
  INDIRECT: [
    'email', 'phone', 'phone_number',
    'address', 'date_of_birth', 'dob',
    'ip_address', 'device_id',
    'geolocation', 'latitude', 'longitude',
  ],
  
  // Sensitive Categories (Level 3)
  SENSITIVE: [
    'race', 'ethnicity', 'religion',
    'political_affiliation', 'sexual_orientation',
    'health_condition', 'disability',
    'biometric_data', 'genetic_data',
  ],
} as const;

function classifyField(fieldName: string): ClassificationLevel {
  const normalizedName = fieldName.toLowerCase().replace(/[-_]/g, '_');
  
  if (PII_FIELDS.DIRECT.some(f => normalizedName.includes(f))) {
    return ClassificationLevel.RESTRICTED;
  }
  
  if (PII_FIELDS.SENSITIVE.some(f => normalizedName.includes(f))) {
    return ClassificationLevel.CONFIDENTIAL;
  }
  
  if (PII_FIELDS.INDIRECT.some(f => normalizedName.includes(f))) {
    return ClassificationLevel.CONFIDENTIAL;
  }
  
  return ClassificationLevel.INTERNAL;
}
```

### 2.2 Financial Data

| Data Type | Classification | Handling Requirements |
|-----------|---------------|----------------------|
| Credit card numbers | RESTRICTED | PCI-DSS compliance, tokenization |
| Bank account numbers | RESTRICTED | Encrypted storage, masked display |
| Transaction history | CONFIDENTIAL | Access logging, retention limits |
| Pricing information | INTERNAL | Version control, approval workflow |
| Public financial reports | PUBLIC | Standard publication |

### 2.3 Authentication Data

| Data Type | Classification | Requirements |
|-----------|---------------|--------------|
| Passwords | RESTRICTED | Never store plaintext, Argon2id hash |
| API keys/secrets | RESTRICTED | Vault storage, rotation policy |
| Session tokens | CONFIDENTIAL | HttpOnly, Secure, short TTL |
| Refresh tokens | CONFIDENTIAL | Encrypted storage, revocation list |
| OAuth tokens | CONFIDENTIAL | Scoped access, expiration |

---

## 3. Labeling Standards

### 3.1 Database Column Annotations

```sql
-- PostgreSQL: Column comments for classification
COMMENT ON COLUMN user.email IS 
  '@classification:CONFIDENTIAL @pii:indirect @retention:account_lifetime';

COMMENT ON COLUMN user.password_hash IS 
  '@classification:RESTRICTED @auth:true @retention:until_changed';

COMMENT ON COLUMN user.created_at IS 
  '@classification:INTERNAL @retention:permanent';

-- Create classification tracking table
CREATE TABLE data_classification (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    table_name VARCHAR(100) NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    classification VARCHAR(20) NOT NULL,
    pii_type VARCHAR(50),
    retention_policy VARCHAR(100),
    last_reviewed_at TIMESTAMPTZ,
    reviewed_by UUID REFERENCES user(id),
    
    CONSTRAINT classification_level CHECK (
        classification IN ('PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED')
    ),
    
    UNIQUE (table_name, column_name)
);
```

### 3.2 Code-Level Annotations

```typescript
// TypeScript - Decorator-based classification
function Classified(level: ClassificationLevel) {
  return function (target: any, propertyKey: string) {
    Reflect.defineMetadata('classification', level, target, propertyKey);
  };
}

class UserProfile {
  @Classified(ClassificationLevel.PUBLIC)
  displayName: string;
  
  @Classified(ClassificationLevel.CONFIDENTIAL)
  email: string;
  
  @Classified(ClassificationLevel.CONFIDENTIAL)
  phoneNumber: string;
  
  @Classified(ClassificationLevel.RESTRICTED)
  ssn: string;
}
```

```php
<?php
// PHP - Attribute-based classification
#[Attribute(Attribute::TARGET_PROPERTY)]
class Classified {
    public function __construct(
        public readonly ClassificationLevel $level,
        public readonly ?string $piiType = null,
        public readonly ?string $retentionPolicy = null
    ) {}
}

class UserProfile {
    #[Classified(ClassificationLevel::PUBLIC)]
    public string $displayName;
    
    #[Classified(ClassificationLevel::CONFIDENTIAL, piiType: 'email')]
    public string $email;
    
    #[Classified(ClassificationLevel::RESTRICTED, piiType: 'ssn')]
    public ?string $ssn = null;
}
```

```python
# Python - Dataclass classification
from dataclasses import dataclass, field
from typing import Optional
from enum import Enum

class ClassificationLevel(Enum):
    PUBLIC = 1
    INTERNAL = 2
    CONFIDENTIAL = 3
    RESTRICTED = 4

def classified(level: ClassificationLevel, pii_type: Optional[str] = None):
    """Field metadata for classification"""
    return field(metadata={
        'classification': level,
        'pii_type': pii_type
    })

@dataclass
class UserProfile:
    display_name: str = classified(ClassificationLevel.PUBLIC)
    email: str = classified(ClassificationLevel.CONFIDENTIAL, pii_type='email')
    phone: str = classified(ClassificationLevel.CONFIDENTIAL, pii_type='phone')
    ssn: Optional[str] = classified(ClassificationLevel.RESTRICTED, pii_type='ssn')
```

---

## 4. Access Control by Classification

### 4.1 Access Matrix

| Classification | Who Can Access | How | Approval |
|---------------|----------------|-----|----------|
| RESTRICTED | Named individuals only | MFA + VPN + Audit | Manager + Security |
| CONFIDENTIAL | Role-based | MFA + Audit | Manager |
| INTERNAL | All employees | Authentication | Self-service |
| PUBLIC | Anyone | None | None |

### 4.2 Role Mappings

```typescript
// TypeScript - Classification-based access control
const CLASSIFICATION_ROLES: Record<ClassificationLevel, Role[]> = {
  [ClassificationLevel.RESTRICTED]: ['security_admin', 'data_protection_officer'],
  [ClassificationLevel.CONFIDENTIAL]: ['admin', 'manager', 'support'],
  [ClassificationLevel.INTERNAL]: ['employee', 'contractor'],
  [ClassificationLevel.PUBLIC]: ['*'], // Anyone
};

function canAccessClassification(
  userRoles: Role[], 
  classification: ClassificationLevel
): boolean {
  const allowedRoles = CLASSIFICATION_ROLES[classification];
  
  if (allowedRoles.includes('*')) {
    return true;
  }
  
  return userRoles.some(role => allowedRoles.includes(role));
}
```

### 4.3 Data Masking Rules

```typescript
// TypeScript - Field masking based on classification
interface MaskingRule {
  pattern: RegExp;
  replacement: (match: string) => string;
}

const MASKING_RULES: Record<string, MaskingRule> = {
  email: {
    pattern: /^(.{2})(.*)(@.*)$/,
    replacement: (_, start, middle, domain) => 
      `${start}${'*'.repeat(Math.min(middle.length, 5))}${domain}`,
  },
  phone: {
    pattern: /^(.{3})(.*)(.{4})$/,
    replacement: (_, area, middle, last) => 
      `${area}-***-${last}`,
  },
  ssn: {
    pattern: /^.*(.{4})$/,
    replacement: (_, last) => `***-**-${last}`,
  },
  credit_card: {
    pattern: /^.*(.{4})$/,
    replacement: (_, last) => `****-****-****-${last}`,
  },
};

function maskField(fieldType: string, value: string): string {
  const rule = MASKING_RULES[fieldType];
  
  if (isNotNullish(rule)) {
    return value.replace(rule.pattern, rule.replacement);
  }
  
  return '***REDACTED***';
}

// Usage
maskField('email', 'john.doe@example.com');  // 'jo*****@example.com'
maskField('ssn', '123-45-6789');              // '***-**-6789'
maskField('credit_card', '4111111111111234'); // '****-****-****-1234'
```

---

## 5. Encryption Requirements

### 5.1 Encryption by Classification

| Classification | At Rest | In Transit | Key Management |
|---------------|---------|------------|----------------|
| RESTRICTED | AES-256-GCM | TLS 1.3 | HSM, 90-day rotation |
| CONFIDENTIAL | AES-256 | TLS 1.2+ | KMS, 180-day rotation |
| INTERNAL | Optional | TLS 1.2+ | Standard key store |
| PUBLIC | None required | TLS recommended | N/A |

### 5.2 Field-Level Encryption

```typescript
// TypeScript - Field-level encryption for sensitive data
import { createCipheriv, createDecipheriv, randomBytes } from 'crypto';

class FieldEncryption {
  private algorithm = 'aes-256-gcm';
  
  constructor(private masterKey: Buffer) {
    if (masterKey.length !== 32) {
      throw new Error('Master key must be 256 bits');
    }
  }
  
  encrypt(plaintext: string): EncryptedField {
    const iv = randomBytes(16);
    const cipher = createCipheriv(this.algorithm, this.masterKey, iv);
    
    let encrypted = cipher.update(plaintext, 'utf8', 'base64');
    encrypted += cipher.final('base64');
    
    const authTag = cipher.getAuthTag();
    
    return {
      ciphertext: encrypted,
      iv: iv.toString('base64'),
      authTag: authTag.toString('base64'),
      algorithm: this.algorithm,
      version: 1,
    };
  }
  
  decrypt(encrypted: EncryptedField): string {
    const iv = Buffer.from(encrypted.iv, 'base64');
    const authTag = Buffer.from(encrypted.authTag, 'base64');
    
    const decipher = createDecipheriv(this.algorithm, this.masterKey, iv);
    decipher.setAuthTag(authTag);
    
    let decrypted = decipher.update(encrypted.ciphertext, 'base64', 'utf8');
    decrypted += decipher.final('utf8');
    
    return decrypted;
  }
}

interface EncryptedField {
  ciphertext: string;
  iv: string;
  authTag: string;
  algorithm: string;
  version: number;
}
```

---

## 6. Data Discovery

### 6.1 Automated Classification Scanning

```typescript
// TypeScript - Data discovery service
interface DiscoveryResult {
  table: string;
  column: string;
  suggestedClassification: ClassificationLevel;
  reason: string;
  sampleMatches: number;
  confidence: number;
}

class DataDiscoveryService {
  private patterns: Map<RegExp, PatternMatch> = new Map([
    // SSN patterns
    [/^\d{3}-\d{2}-\d{4}$/, { 
      classification: ClassificationLevel.RESTRICTED, 
      piiType: 'ssn',
      confidence: 0.95 
    }],
    
    // Email patterns
    [/^[^\s@]+@[^\s@]+\.[^\s@]+$/, { 
      classification: ClassificationLevel.CONFIDENTIAL, 
      piiType: 'email',
      confidence: 0.9 
    }],
    
    // Credit card patterns (Luhn-valid)
    [/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14})$/, { 
      classification: ClassificationLevel.RESTRICTED, 
      piiType: 'credit_card',
      confidence: 0.95 
    }],
    
    // Phone patterns
    [/^\+?1?[-.\s]?\(?[0-9]{3}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}$/, { 
      classification: ClassificationLevel.CONFIDENTIAL, 
      piiType: 'phone',
      confidence: 0.85 
    }],
  ]);
  
  async scanTable(tableName: string): Promise<DiscoveryResult[]> {
    const results: DiscoveryResult[] = [];
    const columns = await this.getTableColumns(tableName);
    
    for (const column of columns) {
      const samples = await this.getSampleValues(tableName, column, 100);
      const classification = this.classifyColumn(column, samples);
      
      if (isNotNull(classification)) {
        results.push({
          table: tableName,
          column: column,
          ...classification,
        });
      }
    }
    
    return results;
  }
  
  private classifyColumn(
    columnName: string, 
    samples: string[]
  ): Partial<DiscoveryResult> | null {
    // Check column name first
    const nameClassification = classifyField(columnName);
    
    // Then check sample data patterns
    let bestMatch: PatternMatch | null = null;
    let matchCount = 0;
    
    for (const sample of samples) {
      for (const [pattern, match] of this.patterns) {
        if (pattern.test(sample)) {
          matchCount++;
          if (isNull(bestMatch) || match.confidence > bestMatch.confidence) {
            bestMatch = match;
          }
        }
      }
    }
    
    if (isNotNull(bestMatch) && matchCount > samples.length * 0.1) {
      return {
        suggestedClassification: bestMatch.classification,
        reason: `Pattern match: ${bestMatch.piiType}`,
        sampleMatches: matchCount,
        confidence: bestMatch.confidence,
      };
    }
    
    if (nameClassification !== ClassificationLevel.INTERNAL) {
      return {
        suggestedClassification: nameClassification,
        reason: `Column name suggests sensitive data`,
        sampleMatches: 0,
        confidence: 0.7,
      };
    }
    
    return null;
  }
}
```

---

## 7. Compliance Mapping

### 7.1 Regulatory Requirements

| Regulation | Data Types | Classification | Key Requirements |
|------------|-----------|----------------|-----------------|
| **GDPR** | EU personal data | CONFIDENTIAL+ | Consent, right to erasure, DPO |
| **CCPA** | CA consumer data | CONFIDENTIAL+ | Opt-out, disclosure, deletion |
| **HIPAA** | Protected health info | RESTRICTED | BAA, encryption, access controls |
| **PCI-DSS** | Payment card data | RESTRICTED | Network segmentation, quarterly scans |
| **SOX** | Financial records | CONFIDENTIAL | Audit trails, access controls |
| **FERPA** | Student records | CONFIDENTIAL | Consent, limited disclosure |

### 7.2 Cross-Border Transfer Rules

```typescript
// TypeScript - Data residency enforcement
interface DataResidencyRule {
  classification: ClassificationLevel;
  allowedRegions: string[];
  transferRequirements: string[];
}

const RESIDENCY_RULES: DataResidencyRule[] = [
  {
    classification: ClassificationLevel.RESTRICTED,
    allowedRegions: ['us-east-1', 'us-west-2'], // Primary regions only
    transferRequirements: ['encryption', 'legal_review', 'dpo_approval'],
  },
  {
    classification: ClassificationLevel.CONFIDENTIAL,
    allowedRegions: ['us-*', 'eu-*'], // US and EU
    transferRequirements: ['encryption', 'scc_agreement'],
  },
  {
    classification: ClassificationLevel.INTERNAL,
    allowedRegions: ['*'], // Any region
    transferRequirements: ['encryption'],
  },
];
```

---

## 8. Anti-Patterns

### ❌ INCORRECT - No Classification

```typescript
// Treating all data the same
interface User {
  id: string;
  name: string;
  email: string;      // PII - needs classification
  ssn: string;        // Highly sensitive - needs classification
  preferences: object;
}

// Storing without encryption
await db.users.insert({
  email: user.email,
  ssn: user.ssn,  // NEVER store SSN in plaintext
});
```

### ✅ CORRECT - Proper Classification

```typescript
// Classified data model
interface User {
  id: string;                                    // INTERNAL
  
  @Classified(ClassificationLevel.PUBLIC)
  name: string;
  
  @Classified(ClassificationLevel.CONFIDENTIAL)
  email: string;
  
  @Classified(ClassificationLevel.RESTRICTED)
  encryptedSsn: EncryptedField;                 // Encrypted at rest
  
  @Classified(ClassificationLevel.INTERNAL)
  preferences: object;
}

// Proper storage with encryption
const encryptedSsn = fieldEncryption.encrypt(user.ssn);
await db.users.insert({
  email: user.email,
  encrypted_ssn: encryptedSsn,
});
```

---

## 9. Mandatory Checklist

- [ ] All database columns classified and documented
- [ ] PII fields identified and labeled
- [ ] RESTRICTED data encrypted at rest
- [ ] Access controls match classification levels
- [ ] Data discovery scan completed
- [ ] Masking rules defined for sensitive fields
- [ ] Compliance requirements mapped
- [ ] Classification review scheduled (quarterly)

---

*Classification standards must be reviewed quarterly and updated for new data types.*
