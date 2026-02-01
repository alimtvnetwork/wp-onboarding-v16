# 45. Reporting Dashboard

## Overview
Analytics and reporting interface for exam performance and participant metrics.

---

## 45.1 Dashboard Overview

### Key Metrics Cards
- Total exams (active/archived)
- Total participants (by status)
- Average completion rate
- Average time to completion

### Trend Charts
- Participant registrations over time
- Completions over time
- Extension requests trend

### Acceptance Criteria:
- [ ] Dashboard loads within 3 seconds
- [ ] Metrics update daily (cached)
- [ ] Date range selector for trends
- [ ] Export dashboard as PDF

---

## 45.2 Exam Performance Report

### Per-Exam Metrics
- Total participants
- Completion rate
- Average progress
- Average time to complete
- Extension request rate
- Dropout rate

### Visualization
- Progress distribution histogram
- Completion timeline chart
- Status breakdown pie chart

### Acceptance Criteria:
- [ ] Report available per exam
- [ ] Comparison between exams
- [ ] Drill-down to participant list
- [ ] Export as CSV/PDF

---

## 45.3 Participant Analytics

### Individual Metrics
- Time spent on exam
- Checklist completion pattern
- Login frequency
- Device/browser breakdown

### Cohort Analysis
- Compare groups by start date
- Compare by deadline type
- Compare by extension status

### Acceptance Criteria:
- [ ] Privacy-respecting analytics
- [ ] No individual tracking without consent
- [ ] Aggregate data for cohorts
- [ ] Filter by date range

---

## 45.4 Checklist Analytics

### Item-Level Metrics
- Completion rate per item
- Average time to complete
- Most skipped items
- Completion order patterns

### Insights
- Identify difficult items (low completion)
- Identify items completed last
- Correlation with overall success

### Acceptance Criteria:
- [ ] Heatmap of completion patterns
- [ ] Suggestions for improvement
- [ ] Compare across exam versions
- [ ] Export item analytics

---

## 45.5 Deadline Analytics

### Metrics
- On-time completion rate
- Extensions requested rate
- Extensions approved rate
- Average extension days

### Visualization
- Completion vs deadline scatter
- Extension reasons breakdown
- Deadline effectiveness chart

### Acceptance Criteria:
- [ ] Identify deadline patterns
- [ ] Recommend optimal deadline length
- [ ] Alert on high extension rates
- [ ] Historical trend analysis

---

## 45.6 Secret Key Analytics [OPTIONAL]

### Access Metrics
- Total accesses per key
- Unique visitors per key
- Geographic distribution
- Referrer breakdown

### Conversion Tracking
- View to registration rate
- Key sharing detection
- Time to first interaction

### Acceptance Criteria:
- [ ] Analytics respect privacy settings
- [ ] IP-based metrics use hashes only
- [ ] Referrer tracking configurable
- [ ] Clear data option available

---

## 45.7 Custom Reports

### Report Builder
- Select metrics to include
- Choose visualization type
- Set filters and date ranges
- Save report configurations

### Scheduled Reports
- Weekly/monthly email delivery
- PDF attachment option
- Multiple recipients
- Pause/resume schedules

### Acceptance Criteria:
- [ ] Drag-and-drop report builder
- [ ] Save and share report configs
- [ ] Schedule with cron integration
- [ ] Unsubscribe from scheduled reports

---

## 45.8 Data Export

### Export Options
- Full data export (all tables)
- Filtered export (with current filters)
- Anonymized export (for research)

### Formats
- CSV (universal compatibility)
- Excel (formatted with charts)
- JSON (programmatic access)

### Acceptance Criteria:
- [ ] Export respects RBAC permissions
- [ ] Large exports processed in background
- [ ] Download notification when ready
- [ ] Export history tracked
