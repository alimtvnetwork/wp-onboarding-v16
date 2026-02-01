# 19. Incident Management

> **Version**: 1.0.0  
> **Last Updated**: 2026-01-26  
> **Applies To**: All Teams

## Overview

Standardized procedures for detecting, responding to, mitigating, and learning from production incidents to minimize impact and prevent recurrence.

---

## 19.1 Incident Severity Levels

### Severity Classification

| Severity | Name     | Impact                                    | Response Time | Examples                           |
|----------|----------|------------------------------------------|---------------|-----------------------------------|
| SEV-1    | Critical | Complete outage, data loss, security breach | 5 minutes    | Service down, data corruption     |
| SEV-2    | High     | Major feature broken, >50% users affected | 15 minutes   | Auth failing, payments broken     |
| SEV-3    | Medium   | Feature degraded, <50% users affected    | 1 hour       | Slow responses, partial failures  |
| SEV-4    | Low      | Minor issue, workaround available        | 4 hours      | UI bug, non-critical feature      |

### Severity Decision Tree

```
┌─────────────────────────────────────────────────────────┐
│               INCIDENT SEVERITY DECISION                │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Is there data loss or security breach?                 │
│  ├── YES → SEV-1 (Critical)                            │
│  └── NO ↓                                              │
│                                                         │
│  Is the service completely unavailable?                 │
│  ├── YES → SEV-1 (Critical)                            │
│  └── NO ↓                                              │
│                                                         │
│  Are >50% of users or core features affected?          │
│  ├── YES → SEV-2 (High)                                │
│  └── NO ↓                                              │
│                                                         │
│  Is functionality degraded but usable?                  │
│  ├── YES → SEV-3 (Medium)                              │
│  └── NO → SEV-4 (Low)                                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 19.2 Incident Lifecycle

### Phases

```
┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐
│ DETECT   │ → │ RESPOND  │ → │ MITIGATE │ → │ RESOLVE  │ → │ LEARN    │
│          │   │          │   │          │   │          │   │          │
│ • Alert  │   │ • Triage │   │ • Contain│   │ • Fix    │   │ • Review │
│ • Report │   │ • Assign │   │ • Reduce │   │ • Verify │   │ • Action │
│ • Verify │   │ • Notify │   │ • Impact │   │ • Close  │   │ • Share  │
└──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘
```

### Phase Details

#### 1. Detection (Target: <5 minutes)
- Automated alerting triggers
- User reports received
- Monitoring anomalies identified
- Initial verification of issue

#### 2. Response (Target: <15 minutes for SEV-1/2)
- Incident commander assigned
- Communication channels established
- Stakeholders notified
- Initial assessment documented

#### 3. Mitigation (Target: ASAP)
- Contain the blast radius
- Implement temporary fixes
- Redirect traffic if needed
- Reduce user impact

#### 4. Resolution (Variable)
- Root cause identified
- Permanent fix deployed
- Systems restored to normal
- All-clear communicated

#### 5. Learning (Within 5 business days)
- Post-incident review conducted
- Action items identified
- Documentation updated
- Knowledge shared

---

## 19.3 Incident Roles

### Required Roles

| Role                  | Responsibility                              | Required For |
|-----------------------|---------------------------------------------|--------------|
| Incident Commander    | Overall coordination, decisions             | SEV-1, SEV-2 |
| Technical Lead        | Investigation, technical decisions          | All          |
| Communications Lead   | Stakeholder updates, status page            | SEV-1, SEV-2 |
| Scribe                | Documentation, timeline tracking            | SEV-1, SEV-2 |
| Subject Matter Expert | Domain-specific knowledge                   | As needed    |

### Incident Commander Responsibilities

```markdown
## Incident Commander Checklist

### On Declaration
- [ ] Acknowledge incident
- [ ] Assess initial severity
- [ ] Open incident channel (e.g., #incident-2024-01-15)
- [ ] Assign roles (Tech Lead, Comms, Scribe)
- [ ] Set initial status update cadence

### During Incident
- [ ] Coordinate investigation efforts
- [ ] Make escalation decisions
- [ ] Approve mitigation actions
- [ ] Ensure regular status updates
- [ ] Manage external communications

### On Resolution
- [ ] Verify fix effectiveness
- [ ] Communicate all-clear
- [ ] Schedule post-incident review
- [ ] Ensure documentation complete
- [ ] Hand off to next commander if needed
```

---

## 19.4 Communication Standards

### Internal Communication

```markdown
## Status Update Template

**Incident**: [INC-2024-0042] Service Degradation
**Severity**: SEV-2
**Status**: Investigating / Mitigating / Monitoring / Resolved
**Time**: 2024-01-15 14:30 UTC

**Current State**:
[Brief description of current impact]

**What We Know**:
- [Fact 1]
- [Fact 2]

**Current Actions**:
- [Action being taken]

**Next Update**: [Time] or when status changes
```

### Update Cadence

| Severity | Update Frequency        |
|----------|------------------------|
| SEV-1    | Every 15 minutes       |
| SEV-2    | Every 30 minutes       |
| SEV-3    | Every 2 hours          |
| SEV-4    | Daily or on resolution |

### External Communication (Status Page)

```markdown
## Status Page Update Template

### Investigating
We are currently investigating issues with [SERVICE]. 
Some users may experience [SYMPTOM]. We will provide 
updates as we learn more.

### Identified
We have identified the issue affecting [SERVICE]. 
Our team is implementing a fix. [WORKAROUND if available]

### Monitoring
A fix has been implemented and we are monitoring the 
results. [SERVICE] should be operating normally.

### Resolved
This incident has been resolved. [SERVICE] is fully 
operational. We apologize for any inconvenience.
```

---

## 19.5 Escalation Procedures

### Escalation Matrix

| Trigger                           | Escalate To              |
|-----------------------------------|-------------------------|
| SEV-1 declared                    | Engineering Manager, VP |
| Incident >30 min without progress | Additional SMEs         |
| Customer data affected            | Legal, Security, DPO    |
| Financial impact >$X              | Finance, Executive      |
| Security breach suspected         | Security Team, CISO     |
| External dependency issue         | Vendor contacts         |

### Escalation Template

```markdown
## Escalation Request

**From**: [Your Name] - Incident Commander
**To**: [Escalation Target]
**Incident**: INC-2024-0042
**Severity**: SEV-1
**Duration**: 45 minutes

**Summary**:
[Brief description]

**Why Escalating**:
[Specific reason for escalation]

**What We Need**:
[Specific ask - decision, resource, expertise]

**Current Status**:
[Link to incident channel/doc]
```

---

## 19.6 Post-Incident Review (PIR)

### PIR Requirements

| Severity | PIR Required | Timeline       | Attendees              |
|----------|-------------|----------------|------------------------|
| SEV-1    | Mandatory   | Within 3 days  | All responders + leads |
| SEV-2    | Mandatory   | Within 5 days  | Key responders + lead  |
| SEV-3    | Optional    | Within 1 week  | Tech lead + optional   |
| SEV-4    | No          | N/A            | N/A                    |

### PIR Document Template

```markdown
# Post-Incident Review: INC-2024-0042

## Incident Summary
- **Date/Time**: 2024-01-15 14:00 - 15:30 UTC
- **Duration**: 90 minutes
- **Severity**: SEV-2
- **Services Affected**: User Authentication
- **Impact**: ~5,000 users unable to log in

## Timeline
| Time (UTC) | Event |
|------------|-------|
| 14:00 | First alert triggered |
| 14:05 | On-call engineer acknowledged |
| 14:10 | Incident declared, commander assigned |
| 14:25 | Root cause identified (DB connection exhaustion) |
| 14:40 | Mitigation applied (connection pool increased) |
| 15:00 | Fix deployed (connection leak fixed) |
| 15:30 | All-clear declared |

## Root Cause
[Detailed technical explanation]

## Contributing Factors
1. [Factor 1 - e.g., Missing connection pool monitoring]
2. [Factor 2 - e.g., Lack of connection limit alerts]
3. [Factor 3 - e.g., Recent code change introduced leak]

## What Went Well
- Fast detection (5 minutes)
- Clear communication throughout
- Effective collaboration between teams

## What Could Be Improved
- Alert for connection pool usage needed
- Runbook was outdated
- No automated rollback available

## Action Items
| ID | Action | Owner | Due Date | Status |
|----|--------|-------|----------|--------|
| 1 | Add connection pool alert | @jane | 2024-01-22 | Open |
| 2 | Update auth runbook | @bob | 2024-01-20 | Open |
| 3 | Implement auto-rollback | @team | 2024-02-15 | Open |

## Lessons Learned
[Key takeaways for the organization]
```

### Blameless Culture

```markdown
## PIR Ground Rules

1. **Focus on systems, not individuals**
   - Ask "What allowed this to happen?" not "Who caused this?"
   
2. **Assume good intentions**
   - Everyone was trying to do their best with available information
   
3. **Seek to understand**
   - Why did the action seem reasonable at the time?
   
4. **Improve the system**
   - What guardrails would prevent this?
   
5. **Share openly**
   - Transparency helps everyone learn
```

---

## 19.7 Incident Metrics

### Key Metrics to Track

| Metric | Definition | Target |
|--------|------------|--------|
| MTTD (Mean Time To Detect) | Alert to acknowledgment | <5 min |
| MTTA (Mean Time To Acknowledge) | Alert to first response | <10 min |
| MTTM (Mean Time To Mitigate) | Start to impact reduced | <30 min |
| MTTR (Mean Time To Resolve) | Start to full resolution | <2 hours |
| Incident Count | Total incidents per period | Trending down |
| Recurring Incidents | Same root cause incidents | 0 |

### Incident Dashboard

```
┌─────────────────────────────────────────────────────────┐
│  INCIDENT METRICS (Last 30 Days)                       │
├─────────────────────────────────────────────────────────┤
│  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌─────────┐ │
│  │ Incidents │ │   MTTD    │ │   MTTR    │ │ Action  │ │
│  │    12     │ │   3 min   │ │  45 min   │ │  Items  │ │
│  │ (↓ 25%)   │ │ (↓ 40%)   │ │ (↓ 20%)   │ │   8     │ │
│  └───────────┘ └───────────┘ └───────────┘ └─────────┘ │
├─────────────────────────────────────────────────────────┤
│  By Severity                  By Service               │
│  SEV-1: ██ 2                  Auth: ████ 4             │
│  SEV-2: ████ 4                API: ███ 3               │
│  SEV-3: ██████ 6              DB: ██ 2                 │
│                               Other: ███ 3             │
└─────────────────────────────────────────────────────────┘
```

---

## 19.8 Tools & Infrastructure

### Required Tooling

| Category | Purpose | Examples |
|----------|---------|----------|
| Alerting | Incident detection | PagerDuty, OpsGenie, VictorOps |
| Communication | Team coordination | Slack, Teams, Discord |
| Status Page | External communication | Statuspage, Cachet, Instatus |
| Documentation | Incident tracking | Confluence, Notion, GitHub Issues |
| Video | War room calls | Zoom, Meet, Teams |
| Runbooks | Response procedures | Wiki, GitHub, internal docs |

### Incident Channel Naming

```
Format: #incident-YYYY-MM-DD[-optional-slug]

Examples:
#incident-2024-01-15
#incident-2024-01-15-auth-outage
#incident-2024-01-15-eu-latency
```

---

## Related Specifications

- [01-monitoring-observability.md](./01-monitoring-observability.md) - Alerting standards
- [03-oncall-runbooks-observability.md](./03-oncall-runbooks-observability.md) - On-call procedures
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Log analysis during incidents
- [03-deployment-cicd-devops.md](../06-devops/03-deployment-cicd-devops.md) - Rollback procedures
