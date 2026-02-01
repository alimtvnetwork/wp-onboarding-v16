# 20. On-Call & Runbooks

> **Version**: 1.0.0  
> **Last Updated**: 2026-01-26  
> **Applies To**: All Teams

## Overview

Standards for on-call rotations, responsibilities, and runbook documentation to ensure effective incident response and sustainable on-call practices.

---

## 20.1 On-Call Structure

### Rotation Design

```
┌─────────────────────────────────────────────────────────────┐
│                    ON-CALL STRUCTURE                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  PRIMARY ON-CALL          SECONDARY ON-CALL                 │
│  ┌────────────────┐       ┌────────────────┐                │
│  │ First Response │       │ Backup/Escalate│                │
│  │ 5 min SLA      │       │ 15 min SLA     │                │
│  └───────┬────────┘       └───────┬────────┘                │
│          │                        │                          │
│          └────────────┬───────────┘                          │
│                       ▼                                      │
│              ┌────────────────┐                              │
│              │ ESCALATION     │                              │
│              │ Engineering Mgr│                              │
│              │ 30 min SLA     │                              │
│              └────────────────┘                              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Rotation Guidelines

| Aspect | Guideline |
|--------|-----------|
| Shift Length | 1 week maximum |
| Handoff Day | Weekday mornings (avoid Fridays) |
| Team Size | Minimum 4 people for sustainable rotation |
| Time Zones | Follow-the-sun for global teams |
| Compensation | Per company policy (time off, pay, etc.) |

### Handoff Checklist

```markdown
## On-Call Handoff Checklist

### Outgoing On-Call
- [ ] Update shift notes with any ongoing issues
- [ ] Document any temporary workarounds in place
- [ ] List pending action items from incidents
- [ ] Highlight any scheduled changes/deployments
- [ ] Ensure all alerts are acknowledged or resolved

### Incoming On-Call
- [ ] Review shift notes from previous week
- [ ] Verify PagerDuty/alerting access
- [ ] Check recent deployments and changes
- [ ] Review any open incidents
- [ ] Confirm contact info is current
- [ ] Test notification delivery (page self)
```

---

## 20.2 On-Call Responsibilities

### Primary On-Call

```markdown
## Primary On-Call Duties

### Response Requirements
- Acknowledge pages within 5 minutes
- Have laptop and internet access at all times
- Maintain phone signal or alternative notification
- Stay within 30 minutes of full response capability

### During Shift
- Monitor alert channels
- Triage incoming issues
- Escalate appropriately
- Document actions taken
- Coordinate with secondary if needed

### What NOT to Do
- Deploy risky changes during off-hours
- Ignore low-severity alerts (they may escalate)
- Make major architectural decisions alone
- Burn out - escalate if overwhelmed
```

### Secondary On-Call

```markdown
## Secondary On-Call Duties

### Response Requirements
- Acknowledge escalations within 15 minutes
- Available as backup when primary is engaged
- Provide domain expertise when needed

### When Activated
- Primary is overwhelmed
- Incident requires multiple responders
- Primary is unreachable after SLA
- Specific expertise is needed
```

---

## 20.3 Alert Response Workflow

### Triage Decision Tree

```
┌─────────────────────────────────────────────────────────────┐
│                    ALERT TRIAGE FLOW                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Alert Received                                              │
│       │                                                      │
│       ▼                                                      │
│  ┌─────────────────┐                                        │
│  │ Is it actionable?│                                        │
│  └────────┬────────┘                                        │
│       │                                                      │
│   NO ─┴─ YES                                                │
│   │       │                                                  │
│   ▼       ▼                                                  │
│ Tune   ┌──────────────┐                                     │
│ Alert  │ Does runbook │                                     │
│        │ exist?       │                                      │
│        └──────┬───────┘                                     │
│           │                                                  │
│       NO ─┴─ YES                                            │
│       │       │                                              │
│       ▼       ▼                                              │
│   Create   Follow                                           │
│   Runbook  Runbook                                          │
│       │       │                                              │
│       └───────┴──────────┐                                  │
│                          ▼                                   │
│                   ┌──────────────┐                          │
│                   │ Is it fixed? │                          │
│                   └──────┬───────┘                          │
│                      │                                       │
│                  NO ─┴─ YES                                 │
│                  │       │                                   │
│                  ▼       ▼                                   │
│              Escalate  Resolve                              │
│                        & Document                           │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Response Time SLAs

| Alert Severity | Acknowledge | Investigate | Escalate If No Progress |
|----------------|-------------|-------------|------------------------|
| Critical (P1)  | 5 min       | 15 min      | 30 min                 |
| High (P2)      | 15 min      | 30 min      | 1 hour                 |
| Medium (P3)    | 1 hour      | 2 hours     | 4 hours                |
| Low (P4)       | 4 hours     | Next day    | 3 days                 |

---

## 20.4 Runbook Standards

### Runbook Structure

```markdown
# Runbook: [Alert Name]

## Overview
- **Alert**: [Alert name as it appears in monitoring]
- **Severity**: [P1/P2/P3/P4]
- **Service**: [Affected service]
- **Last Updated**: [Date]
- **Owner**: [Team or individual]

## Description
[What does this alert mean? Why does it fire?]

## Impact
[What is the user/business impact when this fires?]

## Investigation Steps

### Step 1: Verify the Alert
```bash
# Commands to verify the issue
kubectl get pods -n production
```

### Step 2: Check Related Systems
- [ ] Database connectivity
- [ ] Upstream dependencies
- [ ] Recent deployments

### Step 3: Gather Information
```bash
# Commands to gather diagnostic info
kubectl logs deployment/api -n production --tail=100
```

## Mitigation Steps

### Quick Fix (Temporary)
[Steps to quickly reduce impact]

### Permanent Fix
[Steps for full resolution]

## Escalation
- If unresolved after 30 minutes: Page secondary on-call
- If data loss suspected: Page database team
- If security related: Page security team

## Related Links
- [Dashboard](link)
- [Logs](link)
- [Previous Incidents](link)
```

### Runbook Checklist

```markdown
## Runbook Quality Checklist

### Completeness
- [ ] Clear description of what triggered the alert
- [ ] Step-by-step investigation guide
- [ ] Copy-pasteable commands
- [ ] Escalation path defined
- [ ] Related links included

### Usability
- [ ] Can be followed by any on-call engineer
- [ ] No tribal knowledge required
- [ ] Commands are environment-aware
- [ ] Screenshots/diagrams where helpful

### Maintenance
- [ ] Last updated date is recent (<6 months)
- [ ] Tested during last incident
- [ ] Owner assigned and active
```

---

## 20.5 Common Runbook Templates

### High CPU Usage

```markdown
# Runbook: High CPU Usage

## Overview
- **Alert**: `HighCPUUsage`
- **Severity**: P2
- **Service**: All
- **Threshold**: CPU > 85% for 5 minutes

## Investigation Steps

### 1. Identify the Process
```bash
# Top processes by CPU
top -b -n 1 | head -20

# For containers
kubectl top pods -n production --sort-by=cpu
```

### 2. Check for Anomalies
- [ ] Recent deployment?
- [ ] Traffic spike?
- [ ] Memory pressure causing swap?
- [ ] Runaway process?

### 3. Profile if Needed
```bash
# Capture CPU profile (if app supports)
curl localhost:8080/debug/pprof/profile?seconds=30 > cpu.prof
```

## Mitigation

### Immediate
- Scale horizontally if possible
- Restart problematic pods/processes
- Rate limit if traffic related

### Long-term
- Optimize hot code paths
- Add caching
- Right-size resources
```

### Database Connection Exhaustion

```markdown
# Runbook: Database Connection Pool Exhausted

## Overview
- **Alert**: `DatabaseConnectionsHigh`
- **Severity**: P1
- **Service**: API, Workers
- **Threshold**: Active connections > 90% of max

## Investigation Steps

### 1. Check Current Connections
```sql
-- PostgreSQL
SELECT count(*), state, wait_event_type 
FROM pg_stat_activity 
GROUP BY state, wait_event_type;

-- MySQL
SHOW PROCESSLIST;
```

### 2. Find Connection Leaks
```sql
-- Long-running queries
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE (now() - pg_stat_activity.query_start) > interval '5 minutes'
ORDER BY duration DESC;
```

### 3. Check Application Logs
```bash
kubectl logs deployment/api -n production | grep -i "connection"
```

## Mitigation

### Immediate
```sql
-- Kill long-running queries (carefully!)
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE duration > interval '10 minutes' 
AND state != 'idle';
```

### Temporary
- Increase connection pool size
- Restart application pods

### Long-term
- Fix connection leak in code
- Add connection timeout
- Implement connection pooler (PgBouncer)
```

### Memory Leak

```markdown
# Runbook: High Memory Usage

## Overview
- **Alert**: `HighMemoryUsage`
- **Severity**: P2
- **Service**: All
- **Threshold**: Memory > 90% for 5 minutes

## Investigation Steps

### 1. Check Memory Usage
```bash
# System level
free -h
cat /proc/meminfo

# Container level
kubectl top pods -n production --sort-by=memory
```

### 2. Identify Growth Pattern
```bash
# Check if memory is growing over time
# Look at metrics dashboard for trend
```

### 3. Capture Heap Dump
```bash
# Node.js
kill -USR2 <pid>

# Java
jmap -dump:format=b,file=heap.hprof <pid>

# Go
curl localhost:8080/debug/pprof/heap > heap.prof
```

## Mitigation

### Immediate
- Rolling restart of affected pods
- Scale horizontally to distribute load

### Long-term
- Analyze heap dump for leaks
- Add memory limits to containers
- Implement graceful memory pressure handling
```

---

## 20.6 On-Call Health

### Burnout Prevention

```markdown
## On-Call Wellness Guidelines

### Team Level
- Minimum 4 engineers per rotation
- Maximum 1 week shifts
- Mandatory handoff meetings
- Regular rotation retrospectives

### Individual Level
- Right to escalate when overwhelmed
- Comp time for heavy shifts
- No penalty for escalating
- Mental health support available

### Alert Hygiene
- Review noisy alerts monthly
- Delete alerts that never result in action
- Tune thresholds based on data
- Every alert must have a runbook
```

### On-Call Metrics

| Metric | Target | Red Flag |
|--------|--------|----------|
| Pages per week | <10 | >20 |
| Night pages | <2 | >5 |
| False positive rate | <10% | >25% |
| Runbook coverage | >90% | <70% |
| MTTA (acknowledge) | <5 min | >15 min |

### Weekly On-Call Report

```markdown
## On-Call Weekly Report

**Week**: 2024-01-08 to 2024-01-15
**On-Call**: @engineer

### Summary
- Total pages: 8
- After-hours pages: 2
- False positives: 1
- Incidents declared: 1

### Page Breakdown
| Alert | Count | Actionable | Runbook |
|-------|-------|------------|---------|
| HighCPU | 3 | Yes | ✓ |
| DiskSpace | 2 | Yes | ✓ |
| SlowQuery | 2 | Yes | ✓ |
| FlakyTest | 1 | No | ✗ |

### Action Items
- [ ] Tune FlakyTest alert (false positive)
- [ ] Create runbook for new AlertX
- [ ] Increase disk space on server-03

### Handoff Notes
- Deployment scheduled for Tuesday
- Known issue with cache warming on restart
```

---

## 20.7 Runbook Maintenance

### Review Cadence

| Trigger | Action |
|---------|--------|
| After every incident | Update runbook with learnings |
| Monthly | Review top 5 most-used runbooks |
| Quarterly | Full runbook audit |
| Yearly | Archive unused runbooks |

### Runbook Ownership

```markdown
## Runbook Ownership Matrix

| Service Area | Owner Team | Primary Contact |
|--------------|------------|-----------------|
| API/Backend  | Platform   | @alice          |
| Database     | Data       | @bob            |
| Frontend     | Web        | @carol          |
| Infrastructure | SRE     | @dave           |
| Security     | SecOps     | @eve            |
```

### Testing Runbooks

```markdown
## Runbook Testing Procedure

### Game Days
- Schedule quarterly chaos engineering sessions
- Test runbooks in staging environment
- Rotate who follows the runbook (fresh eyes)
- Document gaps and improvements

### Post-Incident Validation
- After each incident, verify runbook was helpful
- Update any outdated commands
- Add missing investigation steps
- Remove unnecessary steps
```

---

## 20.8 Tools & Automation

### Runbook Automation

```typescript
// TypeScript - Automated Runbook Execution
interface RunbookStep {
  name: string;
  command: string;
  expectedOutput?: RegExp;
  failureAction: 'continue' | 'stop' | 'escalate';
}

interface Runbook {
  alert: string;
  steps: RunbookStep[];
}

async function executeRunbook(runbook: Runbook): Promise<RunbookResult> {
  const results: StepResult[] = [];
  
  for (const step of runbook.steps) {
    console.log(`Executing: ${step.name}`);
    
    try {
      const output = await executeCommand(step.command);
      const success = step.expectedOutput 
        ? step.expectedOutput.test(output)
        : true;
      
      results.push({ step: step.name, success, output });
      
      if (!success && step.failureAction === 'stop') {
        break;
      }
      if (!success && step.failureAction === 'escalate') {
        await escalateToOnCall(runbook.alert, step.name, output);
        break;
      }
    } catch (error) {
      results.push({ step: step.name, success: false, error: error.message });
      if (step.failureAction !== 'continue') {
        break;
      }
    }
  }
  
  return { runbook: runbook.alert, results, completedAt: new Date() };
}
```

### ChatOps Integration

```markdown
## Slack/Chat Commands

| Command | Action |
|---------|--------|
| `/oncall` | Show current on-call |
| `/page @user` | Page specific person |
| `/runbook <alert>` | Link to runbook |
| `/incident new` | Declare incident |
| `/incident status` | Current incidents |
| `/silence <alert> 1h` | Silence alert |
```

---

## Related Specifications

- [01-monitoring-observability.md](./01-monitoring-observability.md) - Alerting configuration
- [02-incident-management-observability.md](./02-incident-management-observability.md) - Incident response
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Log access during on-call
- [03-deployment-cicd-devops.md](../06-devops/03-deployment-cicd-devops.md) - Deployment rollback
