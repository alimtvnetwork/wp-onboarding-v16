# Escalation Notification System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  

---

## Overview

The Escalation Notification System delivers timely, context-rich notifications to users when AI tasks require human intervention. It supports multiple channels (in-app, email, push), priority-based routing, and intelligent batching to prevent notification fatigue.

**Cross-References:**
- [Resilient Execution System](./12-resilient-execution-system.md) — Escalation triggers
- [Instruction System](./03-instruction-system.md) — Task context
- [AI Chat UI](./08-ai-chat-ui.md) — In-app notification display

---

## 13.1 Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                       ESCALATION NOTIFICATION SYSTEM                                 │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────┐     │
│  │                        Notification Orchestrator                             │     │
│  ├─────────────────────────────────────────────────────────────────────────────┤     │
│  │                                                                               │     │
│  │  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                   │     │
│  │  │  Escalation  │───▶│   Priority   │───▶│   Channel    │                   │     │
│  │  │   Ingestion  │    │    Router    │    │   Selector   │                   │     │
│  │  └──────────────┘    └──────────────┘    └──────────────┘                   │     │
│  │                             │                   │                            │     │
│  │                             ▼                   ▼                            │     │
│  │  ┌──────────────────────────────────────────────────────────────────┐       │     │
│  │  │                      Delivery Channels                            │       │     │
│  │  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐ │       │     │
│  │  │  │ In-App  │  │  Email  │  │  Push   │  │ Webhook │  │  Slack  │ │       │     │
│  │  │  │ Toast   │  │         │  │         │  │         │  │         │ │       │     │
│  │  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘ │       │     │
│  │  └──────────────────────────────────────────────────────────────────┘       │     │
│  │                                                                               │     │
│  └─────────────────────────────────────────────────────────────────────────────┘     │
│                                    │                                                  │
│                    ┌───────────────┴───────────────┐                                 │
│                    ▼                               ▼                                 │
│  ┌─────────────────────────┐         ┌─────────────────────────┐                    │
│  │   Notification Store    │         │   Delivery Tracker      │                    │
│  │   (SQLite + IndexedDB)  │         │   (Status & Analytics)  │                    │
│  └─────────────────────────┘         └─────────────────────────┘                    │
│                                                                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 13.2 Database Schema

```sql
-- Notification preferences per user
CREATE TABLE NotificationPreference (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL UNIQUE,
    
    -- Channel preferences
    InAppEnabled BOOLEAN NOT NULL DEFAULT TRUE,
    EmailEnabled BOOLEAN NOT NULL DEFAULT TRUE,
    PushEnabled BOOLEAN NOT NULL DEFAULT FALSE,
    WebhookEnabled BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Priority thresholds (minimum priority to notify)
    InAppMinPriority TEXT NOT NULL DEFAULT 'low',
    EmailMinPriority TEXT NOT NULL DEFAULT 'medium',
    PushMinPriority TEXT NOT NULL DEFAULT 'high',
    
    -- Batching preferences
    BatchEmails BOOLEAN NOT NULL DEFAULT TRUE,
    BatchWindowMinutes INTEGER NOT NULL DEFAULT 15,
    
    -- Quiet hours (UTC)
    QuietHoursEnabled BOOLEAN NOT NULL DEFAULT FALSE,
    QuietHoursStart TEXT,  -- "22:00"
    QuietHoursEnd TEXT,    -- "08:00"
    
    -- Webhook configuration
    WebhookUrl TEXT,
    WebhookSecret TEXT,
    
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    
    FOREIGN KEY (UserId) REFERENCES User(Id) ON DELETE CASCADE
);

-- Notification log for tracking and analytics
CREATE TABLE NotificationLog (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL,
    EscalationId TEXT,
    
    -- Content
    Title TEXT NOT NULL,
    Body TEXT NOT NULL,
    Priority TEXT NOT NULL CHECK (Priority IN ('low', 'medium', 'high', 'critical')),
    Category TEXT NOT NULL,  -- escalation, reminder, resolution
    
    -- Delivery
    Channels TEXT NOT NULL,  -- JSON array of attempted channels
    DeliveryStatus TEXT NOT NULL CHECK (DeliveryStatus IN ('pending', 'sent', 'delivered', 'failed', 'batched')),
    
    -- Interaction
    ReadAt TEXT,
    ActionedAt TEXT,
    DismissedAt TEXT,
    
    -- Metadata
    ContextJson TEXT,  -- Additional context for rendering
    CreatedAt TEXT NOT NULL,
    ExpiresAt TEXT,
    
    FOREIGN KEY (UserId) REFERENCES User(Id) ON DELETE CASCADE,
    FOREIGN KEY (EscalationId) REFERENCES EscalationRequest(Id) ON DELETE SET NULL
);

CREATE INDEX IX_NotificationLog_User ON NotificationLog(UserId);
CREATE INDEX IX_NotificationLog_Status ON NotificationLog(DeliveryStatus);
CREATE INDEX IX_NotificationLog_Priority ON NotificationLog(Priority);
CREATE INDEX IX_NotificationLog_CreatedAt ON NotificationLog(CreatedAt DESC);

-- Email batch queue
CREATE TABLE EmailBatchQueue (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL,
    NotificationIds TEXT NOT NULL,  -- JSON array of notification IDs
    ScheduledAt TEXT NOT NULL,
    Status TEXT NOT NULL CHECK (Status IN ('pending', 'sent', 'cancelled')),
    SentAt TEXT,
    
    FOREIGN KEY (UserId) REFERENCES User(Id) ON DELETE CASCADE
);

CREATE INDEX IX_EmailBatch_Scheduled ON EmailBatchQueue(ScheduledAt);
CREATE INDEX IX_EmailBatch_Status ON EmailBatchQueue(Status);
```

---

## 13.3 Priority-Based Routing

### Priority Definitions

| Priority | SLA | Channels | Use Case |
|----------|-----|----------|----------|
| **Critical** | Immediate | In-App + Push + Email | Destructive actions, data loss risk |
| **High** | < 5 min | In-App + Email | Repeated failures, blocking tasks |
| **Medium** | < 30 min | In-App (+ batched email) | Ambiguous instructions, low confidence |
| **Low** | < 2 hours | In-App only | Optional clarifications, suggestions |

### Routing Logic

```go
type PriorityRouter struct {
    preferences  PreferenceStore
    channels     map[string]NotificationChannel
    batchQueue   *EmailBatchQueue
}

type NotificationRequest struct {
    UserId       string            `json:"userId"`
    EscalationId string            `json:"escalationId,omitempty"`
    Title        string            `json:"title"`
    Body         string            `json:"body"`
    Priority     Priority          `json:"priority"`
    Category     string            `json:"category"`
    Context      map[string]any    `json:"context"`
    ExpiresAt    *time.Time        `json:"expiresAt,omitempty"`
}

func (r *PriorityRouter) Route(ctx context.Context, req NotificationRequest) error {
    // 1. Get user preferences
    prefs, err := r.preferences.Get(ctx, req.UserId)
    if err != nil {
        prefs = DefaultPreferences()
    }
    
    // 2. Check quiet hours
    if prefs.QuietHoursEnabled && r.isQuietHours(prefs) {
        // Defer non-critical notifications
        if req.Priority != PriorityCritical {
            return r.deferUntilQuietHoursEnd(ctx, req, prefs)
        }
    }
    
    // 3. Select channels based on priority
    channels := r.selectChannels(req.Priority, prefs)
    
    // 4. Create notification log entry
    notification := r.createNotificationLog(req, channels)
    
    // 5. Dispatch to each channel
    var wg sync.WaitGroup
    errors := make(chan error, len(channels))
    
    for _, channelName := range channels {
        wg.Add(1)
        go func(ch string) {
            defer wg.Done()
            
            channel := r.channels[ch]
            if channel == nil {
                return
            }
            
            // Handle email batching
            if ch == "email" && prefs.BatchEmails && req.Priority != PriorityCritical {
                r.batchQueue.Add(ctx, req.UserId, notification.Id)
                return
            }
            
            if err := channel.Send(ctx, notification); err != nil {
                errors <- fmt.Errorf("%s: %w", ch, err)
            }
        }(channelName)
    }
    
    wg.Wait()
    close(errors)
    
    // 6. Update delivery status
    return r.updateDeliveryStatus(ctx, notification.Id, errors)
}

func (r *PriorityRouter) selectChannels(priority Priority, prefs *NotificationPreference) []string {
    channels := []string{}
    
    // In-app always included if enabled and meets threshold
    if prefs.InAppEnabled && priority >= parsePriority(prefs.InAppMinPriority) {
        channels = append(channels, "in_app")
    }
    
    // Email based on priority threshold
    if prefs.EmailEnabled && priority >= parsePriority(prefs.EmailMinPriority) {
        channels = append(channels, "email")
    }
    
    // Push for high priority
    if prefs.PushEnabled && priority >= parsePriority(prefs.PushMinPriority) {
        channels = append(channels, "push")
    }
    
    // Webhook if configured
    if prefs.WebhookEnabled && prefs.WebhookUrl != "" {
        channels = append(channels, "webhook")
    }
    
    return channels
}
```

---

## 13.4 In-App Notifications

### Notification Center Component

```typescript
// src/components/notifications/NotificationCenter.tsx
import { useState, useEffect } from 'react';
import { Bell, X, CheckCircle, AlertTriangle, AlertCircle, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { useNotifications } from '@/hooks/useNotifications';

interface NotificationCenterProps {
  readonly className?: string;
}

export function NotificationCenter({ className }: NotificationCenterProps): JSX.Element {
  const { 
    notifications, 
    unreadCount, 
    markAsRead, 
    markAllAsRead,
    dismiss 
  } = useNotifications();

  const [isOpen, setIsOpen] = useState(false);

  // Mark visible notifications as read when opened
  useEffect(() => {
    if (isOpen && unreadCount > 0) {
      const timer = setTimeout(() => {
        notifications
          .filter(n => !n.readAt)
          .slice(0, 5)
          .forEach(n => markAsRead(n.id));
      }, 2000);
      return () => clearTimeout(timer);
    }
  }, [isOpen, notifications, unreadCount, markAsRead]);

  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button 
          variant="ghost" 
          size="icon" 
          className={cn("relative", className)}
          aria-label={`Notifications (${unreadCount} unread)`}
        >
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <Badge 
              variant="destructive" 
              className="absolute -top-1 -right-1 h-5 w-5 p-0 flex items-center justify-center text-xs"
            >
              {unreadCount > 9 ? '9+' : unreadCount}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>
      
      <PopoverContent className="w-96 p-0" align="end">
        <div className="flex items-center justify-between p-4 border-b">
          <h3 className="font-semibold">Notifications</h3>
          {unreadCount > 0 && (
            <Button variant="ghost" size="sm" onClick={markAllAsRead}>
              Mark all read
            </Button>
          )}
        </div>
        
        <ScrollArea className="h-[400px]">
          {notifications.length === 0 ? (
            <div className="p-8 text-center text-muted-foreground">
              <Bell className="h-8 w-8 mx-auto mb-2 opacity-50" />
              <p>No notifications</p>
            </div>
          ) : (
            <div className="divide-y">
              {notifications.map((notification) => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                  onDismiss={() => dismiss(notification.id)}
                  onAction={() => {/* Handle action based on type */}}
                />
              ))}
            </div>
          )}
        </ScrollArea>
      </PopoverContent>
    </Popover>
  );
}

interface NotificationItemProps {
  readonly notification: Notification;
  readonly onDismiss: () => void;
  readonly onAction: () => void;
}

function NotificationItem({ notification, onDismiss, onAction }: NotificationItemProps): JSX.Element {
  const priorityConfig = {
    critical: { icon: AlertCircle, color: 'text-destructive', bg: 'bg-destructive/10' },
    high: { icon: AlertTriangle, color: 'text-orange-500', bg: 'bg-orange-500/10' },
    medium: { icon: Info, color: 'text-blue-500', bg: 'bg-blue-500/10' },
    low: { icon: CheckCircle, color: 'text-muted-foreground', bg: 'bg-muted' },
  };

  const config = priorityConfig[notification.priority];
  const Icon = config.icon;

  return (
    <div 
      className={cn(
        "p-4 hover:bg-muted/50 transition-colors cursor-pointer",
        !notification.readAt && "bg-primary/5"
      )}
      onClick={onAction}
    >
      <div className="flex gap-3">
        <div className={cn("rounded-full p-2 shrink-0", config.bg)}>
          <Icon className={cn("h-4 w-4", config.color)} />
        </div>
        
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <p className={cn(
              "font-medium text-sm truncate",
              !notification.readAt && "text-foreground",
              notification.readAt && "text-muted-foreground"
            )}>
              {notification.title}
            </p>
            <Button
              variant="ghost"
              size="icon"
              className="h-6 w-6 shrink-0"
              onClick={(e) => {
                e.stopPropagation();
                onDismiss();
              }}
            >
              <X className="h-3 w-3" />
            </Button>
          </div>
          
          <p className="text-sm text-muted-foreground line-clamp-2 mt-1">
            {notification.body}
          </p>
          
          <div className="flex items-center gap-2 mt-2">
            <span className="text-xs text-muted-foreground">
              {formatRelativeTime(notification.createdAt)}
            </span>
            {notification.priority === 'critical' && (
              <Badge variant="destructive" className="text-xs">
                Urgent
              </Badge>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
```

### Real-Time Toast Notifications

```typescript
// src/components/notifications/EscalationToast.tsx
import { useEffect } from 'react';
import { toast } from '@/hooks/use-toast';
import { Button } from '@/components/ui/button';
import { AlertTriangle, ExternalLink } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface EscalationToastProps {
  readonly escalation: EscalationRequest;
}

export function showEscalationToast(escalation: EscalationRequest): void {
  const priorityDuration = {
    critical: Infinity,  // Stays until dismissed
    high: 30000,         // 30 seconds
    medium: 15000,       // 15 seconds
    low: 5000,           // 5 seconds
  };

  toast({
    title: getEscalationTitle(escalation),
    description: (
      <div className="flex flex-col gap-2">
        <p className="text-sm">{escalation.context.aiRecommendation}</p>
        <div className="flex gap-2 mt-2">
          <Button 
            size="sm" 
            onClick={() => window.location.href = `/escalations/${escalation.id}`}
          >
            Review Now
          </Button>
          <Button size="sm" variant="outline">
            Remind Later
          </Button>
        </div>
      </div>
    ),
    duration: priorityDuration[escalation.priority],
    variant: escalation.priority === 'critical' ? 'destructive' : 'default',
  });
}

function getEscalationTitle(escalation: EscalationRequest): string {
  const titles = {
    ambiguous_instruction: 'Clarification Needed',
    low_confidence: 'AI Uncertain - Your Input Required',
    conflicting_consensus: 'Models Disagree - Please Decide',
    repeated_failure: 'Task Failed Multiple Times',
    destructive_action: 'Approval Required for Destructive Action',
    permission_required: 'Permission Needed',
  };
  return titles[escalation.escalationType] || 'Action Required';
}
```

### useNotifications Hook

```typescript
// src/hooks/useNotifications.ts
import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

interface Notification {
  readonly id: string;
  readonly title: string;
  readonly body: string;
  readonly priority: 'low' | 'medium' | 'high' | 'critical';
  readonly category: string;
  readonly escalationId?: string;
  readonly readAt: string | null;
  readonly actionedAt: string | null;
  readonly createdAt: string;
  readonly context?: Record<string, unknown>;
}

interface UseNotificationsReturn {
  readonly notifications: readonly Notification[];
  readonly unreadCount: number;
  readonly isLoading: boolean;
  readonly error: Error | null;
  readonly markAsRead: (id: string) => void;
  readonly markAllAsRead: () => void;
  readonly dismiss: (id: string) => void;
}

export function useNotifications(): UseNotificationsReturn {
  const queryClient = useQueryClient();
  
  // Fetch notifications
  const { data: notifications = [], isLoading, error } = useQuery({
    queryKey: ['notifications'],
    queryFn: fetchNotifications,
    refetchInterval: 30000, // Poll every 30s
  });

  // Real-time updates via WebSocket
  useEffect(() => {
    const ws = new WebSocket(`${import.meta.env.VITE_WS_URL}/notifications`);
    
    ws.onmessage = (event) => {
      const notification = JSON.parse(event.data);
      
      // Add to cache
      queryClient.setQueryData(['notifications'], (old: Notification[]) => 
        [notification, ...old].slice(0, 50)
      );
      
      // Show toast for high priority
      if (notification.priority === 'critical' || notification.priority === 'high') {
        showEscalationToast(notification);
      }
    };
    
    return () => ws.close();
  }, [queryClient]);

  // Mutations
  const markAsReadMutation = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onMutate: async (id) => {
      await queryClient.cancelQueries({ queryKey: ['notifications'] });
      queryClient.setQueryData(['notifications'], (old: Notification[]) =>
        old.map(n => n.id === id ? { ...n, readAt: new Date().toISOString() } : n)
      );
    },
  });

  const markAllAsReadMutation = useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  });

  const dismissMutation = useMutation({
    mutationFn: (id: string) => dismissNotification(id),
    onMutate: async (id) => {
      await queryClient.cancelQueries({ queryKey: ['notifications'] });
      queryClient.setQueryData(['notifications'], (old: Notification[]) =>
        old.filter(n => n.id !== id)
      );
    },
  });

  const unreadCount = notifications.filter(n => !n.readAt).length;

  return {
    notifications,
    unreadCount,
    isLoading,
    error: error as Error | null,
    markAsRead: markAsReadMutation.mutate,
    markAllAsRead: markAllAsReadMutation.mutate,
    dismiss: dismissMutation.mutate,
  };
}
```

---

## 13.5 Email Templates

### Base Email Template

```html
<!-- templates/email/base.html -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{.Subject}}</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      line-height: 1.6;
      color: #1a1a1a;
      background-color: #f5f5f5;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .header {
      background: {{.HeaderColor}};
      color: #ffffff;
      padding: 24px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      font-size: 24px;
    }
    .content {
      padding: 32px;
    }
    .priority-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    .priority-critical { background: #dc2626; color: #fff; }
    .priority-high { background: #f97316; color: #fff; }
    .priority-medium { background: #3b82f6; color: #fff; }
    .priority-low { background: #6b7280; color: #fff; }
    .button {
      display: inline-block;
      padding: 12px 24px;
      background: #2563eb;
      color: #ffffff;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 500;
      margin-top: 16px;
    }
    .button:hover {
      background: #1d4ed8;
    }
    .context-box {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 16px;
      margin: 16px 0;
    }
    .footer {
      padding: 24px;
      text-align: center;
      color: #6b7280;
      font-size: 14px;
      border-top: 1px solid #e5e7eb;
    }
    .options-list {
      margin: 16px 0;
    }
    .option-item {
      display: flex;
      align-items: center;
      padding: 12px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      margin-bottom: 8px;
    }
    .option-item:hover {
      background: #f3f4f6;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header" style="background: {{.HeaderColor}}">
      <h1>{{.Title}}</h1>
    </div>
    <div class="content">
      {{.Content}}
    </div>
    <div class="footer">
      <p>This notification was sent from Spec Management Software</p>
      <p><a href="{{.UnsubscribeUrl}}">Manage notification preferences</a></p>
    </div>
  </div>
</body>
</html>
```

### Escalation Email Template

```go
type EscalationEmailData struct {
    UserName        string
    TaskTitle       string
    Priority        string
    EscalationType  string
    Context         EscalationContext
    Options         []EscalationOption
    ActionUrl       string
    ExpiresAt       string
    UnsubscribeUrl  string
}

const escalationEmailTemplate = `
<span class="priority-badge priority-{{.Priority}}">{{.Priority}} Priority</span>

<h2>Action Required: {{.TaskTitle}}</h2>

<p>Hi {{.UserName}},</p>

<p>The AI system needs your input to proceed with a task. {{.GetReasonText}}</p>

<div class="context-box">
  <h3>Task Details</h3>
  <p><strong>Original Instruction:</strong> {{.Context.OriginalInstruction}}</p>
  <p><strong>Attempts Made:</strong> {{.Context.AttemptCount}}</p>
  {{if .Context.FailureReasons}}
  <p><strong>Issues Encountered:</strong></p>
  <ul>
    {{range .Context.FailureReasons}}
    <li>{{.}}</li>
    {{end}}
  </ul>
  {{end}}
</div>

<h3>AI Recommendation</h3>
<p>{{.Context.AiRecommendation}}</p>

<h3>Your Options</h3>
<div class="options-list">
  {{range .Options}}
  <div class="option-item">
    <div>
      <strong>{{.Label}}</strong>
      <p style="margin: 4px 0 0 0; color: #6b7280;">{{.Description}}</p>
    </div>
  </div>
  {{end}}
</div>

<p>
  <a href="{{.ActionUrl}}" class="button">Review & Decide</a>
</p>

{{if .ExpiresAt}}
<p style="color: #6b7280; font-size: 14px;">
  ⏰ This request expires {{.ExpiresAt}}. If not resolved, the task will be skipped.
</p>
{{end}}
`

func (e *EscalationEmailData) GetReasonText() string {
    reasons := map[string]string{
        "ambiguous_instruction": "The instruction is ambiguous and requires clarification.",
        "low_confidence": "The AI has low confidence in its approach and wants your guidance.",
        "conflicting_consensus": "Multiple AI models produced different results and need your decision.",
        "repeated_failure": "The task has failed multiple times and needs a different approach.",
        "destructive_action": "This action may delete or modify important data and requires your approval.",
        "permission_required": "This action requires explicit permission to proceed.",
    }
    return reasons[e.EscalationType]
}
```

### Batch Email Template

```go
const batchEmailTemplate = `
<h2>Pending AI Decisions</h2>

<p>Hi {{.UserName}},</p>

<p>You have {{.Count}} pending escalation{{if gt .Count 1}}s{{end}} that require your attention:</p>

{{range .Escalations}}
<div class="context-box">
  <div style="display: flex; justify-content: space-between; align-items: center;">
    <span class="priority-badge priority-{{.Priority}}">{{.Priority}}</span>
    <span style="color: #6b7280; font-size: 14px;">{{.TimeAgo}}</span>
  </div>
  <h3 style="margin-top: 12px;">{{.TaskTitle}}</h3>
  <p>{{.Summary}}</p>
  <a href="{{.ActionUrl}}" style="color: #2563eb;">Review →</a>
</div>
{{end}}

<p style="margin-top: 24px;">
  <a href="{{.DashboardUrl}}" class="button">View All Escalations</a>
</p>
`
```

---

## 13.6 Email Service Integration

### Resend Integration

```go
package notifications

import (
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "html/template"
    "net/http"
)

type ResendEmailService struct {
    apiKey      string
    fromAddress string
    templates   *template.Template
}

type ResendEmailRequest struct {
    From    string   `json:"from"`
    To      []string `json:"to"`
    Subject string   `json:"subject"`
    Html    string   `json:"html"`
    ReplyTo string   `json:"reply_to,omitempty"`
    Tags    []Tag    `json:"tags,omitempty"`
}

type Tag struct {
    Name  string `json:"name"`
    Value string `json:"value"`
}

func NewResendEmailService(apiKey, fromAddress string) (*ResendEmailService, error) {
    templates, err := template.ParseGlob("templates/email/*.html")
    if err != nil {
        return nil, err
    }
    
    return &ResendEmailService{
        apiKey:      apiKey,
        fromAddress: fromAddress,
        templates:   templates,
    }, nil
}

func (s *ResendEmailService) SendEscalationEmail(ctx context.Context, data EscalationEmailData) error {
    // Render template
    var body bytes.Buffer
    if err := s.templates.ExecuteTemplate(&body, "escalation.html", data); err != nil {
        return fmt.Errorf("template render failed: %w", err)
    }
    
    // Build request
    req := ResendEmailRequest{
        From:    s.fromAddress,
        To:      []string{data.UserEmail},
        Subject: fmt.Sprintf("[%s] Action Required: %s", strings.ToUpper(data.Priority), data.TaskTitle),
        Html:    body.String(),
        Tags: []Tag{
            {Name: "type", Value: "escalation"},
            {Name: "priority", Value: data.Priority},
            {Name: "escalation_id", Value: data.EscalationId},
        },
    }
    
    return s.send(ctx, req)
}

func (s *ResendEmailService) SendBatchEmail(ctx context.Context, data BatchEmailData) error {
    var body bytes.Buffer
    if err := s.templates.ExecuteTemplate(&body, "batch.html", data); err != nil {
        return fmt.Errorf("template render failed: %w", err)
    }
    
    req := ResendEmailRequest{
        From:    s.fromAddress,
        To:      []string{data.UserEmail},
        Subject: fmt.Sprintf("You have %d pending AI decisions", data.Count),
        Html:    body.String(),
        Tags: []Tag{
            {Name: "type", Value: "batch"},
        },
    }
    
    return s.send(ctx, req)
}

func (s *ResendEmailService) send(ctx context.Context, req ResendEmailRequest) error {
    body, err := json.Marshal(req)
    if err != nil {
        return err
    }
    
    httpReq, err := http.NewRequestWithContext(ctx, "POST", "https://api.resend.com/emails", bytes.NewReader(body))
    if err != nil {
        return err
    }
    
    httpReq.Header.Set("Authorization", "Bearer "+s.apiKey)
    httpReq.Header.Set("Content-Type", "application/json")
    
    resp, err := http.DefaultClient.Do(httpReq)
    if err != nil {
        return err
    }
    defer resp.Body.Close()
    
    if resp.StatusCode >= 400 {
        return fmt.Errorf("resend API error: %d", resp.StatusCode)
    }
    
    return nil
}
```

### Email Batch Processor

```go
type EmailBatchProcessor struct {
    queue        EmailBatchStore
    emailService *ResendEmailService
    interval     time.Duration
}

func (p *EmailBatchProcessor) Start(ctx context.Context) {
    ticker := time.NewTicker(p.interval)
    defer ticker.Stop()
    
    for {
        select {
        case <-ctx.Done():
            return
        case <-ticker.C:
            p.processPendingBatches(ctx)
        }
    }
}

func (p *EmailBatchProcessor) processPendingBatches(ctx context.Context) {
    batches, err := p.queue.GetDueBatches(ctx, time.Now())
    if err != nil {
        log.Error("failed to get due batches", "error", err)
        return
    }
    
    for _, batch := range batches {
        if err := p.processBatch(ctx, batch); err != nil {
            log.Error("batch processing failed", "batchId", batch.Id, "error", err)
        }
    }
}

func (p *EmailBatchProcessor) processBatch(ctx context.Context, batch *EmailBatch) error {
    // Get all notifications in batch
    notifications, err := p.queue.GetBatchNotifications(ctx, batch.Id)
    if err != nil {
        return err
    }
    
    if len(notifications) == 0 {
        return p.queue.MarkBatchCancelled(ctx, batch.Id)
    }
    
    // Build batch email data
    user, _ := p.userService.Get(ctx, batch.UserId)
    
    data := BatchEmailData{
        UserName:      user.Name,
        UserEmail:     user.Email,
        Count:         len(notifications),
        Escalations:   p.buildEscalationSummaries(notifications),
        DashboardUrl:  fmt.Sprintf("%s/escalations", p.baseUrl),
    }
    
    // Send batch email
    if err := p.emailService.SendBatchEmail(ctx, data); err != nil {
        return err
    }
    
    // Mark batch as sent
    return p.queue.MarkBatchSent(ctx, batch.Id)
}
```

---

## 13.7 Webhook Integration

### Webhook Delivery

```go
type WebhookChannel struct {
    httpClient *http.Client
    signer     *WebhookSigner
}

type WebhookPayload struct {
    Event       string            `json:"event"`
    Timestamp   string            `json:"timestamp"`
    Notification NotificationData `json:"notification"`
}

func (w *WebhookChannel) Send(ctx context.Context, notification *NotificationLog, prefs *NotificationPreference) error {
    payload := WebhookPayload{
        Event:       "notification.created",
        Timestamp:   time.Now().UTC().Format(time.RFC3339),
        Notification: NotificationData{
            Id:        notification.Id,
            Title:     notification.Title,
            Body:      notification.Body,
            Priority:  notification.Priority,
            Category:  notification.Category,
            Context:   notification.Context,
            CreatedAt: notification.CreatedAt,
        },
    }
    
    body, err := json.Marshal(payload)
    if err != nil {
        return err
    }
    
    // Sign payload
    signature := w.signer.Sign(body, prefs.WebhookSecret)
    
    req, err := http.NewRequestWithContext(ctx, "POST", prefs.WebhookUrl, bytes.NewReader(body))
    if err != nil {
        return err
    }
    
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("X-Webhook-Signature", signature)
    req.Header.Set("X-Webhook-Timestamp", payload.Timestamp)
    
    resp, err := w.httpClient.Do(req)
    if err != nil {
        return fmt.Errorf("webhook delivery failed: %w", err)
    }
    defer resp.Body.Close()
    
    if resp.StatusCode >= 400 {
        return fmt.Errorf("webhook returned error: %d", resp.StatusCode)
    }
    
    return nil
}

type WebhookSigner struct{}

func (s *WebhookSigner) Sign(payload []byte, secret string) string {
    mac := hmac.New(sha256.New, []byte(secret))
    mac.Write(payload)
    return "sha256=" + hex.EncodeToString(mac.Sum(nil))
}
```

---

## 13.8 Notification Preferences UI

```typescript
// src/components/settings/NotificationSettings.tsx
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel } from '@/components/ui/form';

const preferencesSchema = z.object({
  inAppEnabled: z.boolean(),
  emailEnabled: z.boolean(),
  pushEnabled: z.boolean(),
  webhookEnabled: z.boolean(),
  inAppMinPriority: z.enum(['low', 'medium', 'high', 'critical']),
  emailMinPriority: z.enum(['low', 'medium', 'high', 'critical']),
  pushMinPriority: z.enum(['low', 'medium', 'high', 'critical']),
  batchEmails: z.boolean(),
  batchWindowMinutes: z.number().min(5).max(120),
  quietHoursEnabled: z.boolean(),
  quietHoursStart: z.string().optional(),
  quietHoursEnd: z.string().optional(),
  webhookUrl: z.string().url().optional().or(z.literal('')),
});

type PreferencesFormData = z.infer<typeof preferencesSchema>;

export function NotificationSettings(): JSX.Element {
  const form = useForm<PreferencesFormData>({
    resolver: zodResolver(preferencesSchema),
    defaultValues: async () => fetchNotificationPreferences(),
  });

  const onSubmit = async (data: PreferencesFormData) => {
    await updateNotificationPreferences(data);
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
        {/* Channel Settings */}
        <Card>
          <CardHeader>
            <CardTitle>Notification Channels</CardTitle>
            <CardDescription>Choose how you want to receive notifications</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <FormField
              control={form.control}
              name="inAppEnabled"
              render={({ field }) => (
                <FormItem className="flex items-center justify-between">
                  <div>
                    <FormLabel>In-App Notifications</FormLabel>
                    <FormDescription>Show notifications in the app</FormDescription>
                  </div>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />
            
            <FormField
              control={form.control}
              name="emailEnabled"
              render={({ field }) => (
                <FormItem className="flex items-center justify-between">
                  <div>
                    <FormLabel>Email Notifications</FormLabel>
                    <FormDescription>Receive notifications via email</FormDescription>
                  </div>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />
            
            {/* More channel toggles... */}
          </CardContent>
        </Card>

        {/* Priority Thresholds */}
        <Card>
          <CardHeader>
            <CardTitle>Priority Thresholds</CardTitle>
            <CardDescription>Set minimum priority for each channel</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <FormField
              control={form.control}
              name="emailMinPriority"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email Minimum Priority</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select priority" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="low">Low (all notifications)</SelectItem>
                      <SelectItem value="medium">Medium and above</SelectItem>
                      <SelectItem value="high">High and critical only</SelectItem>
                      <SelectItem value="critical">Critical only</SelectItem>
                    </SelectContent>
                  </Select>
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        {/* Batching & Quiet Hours */}
        <Card>
          <CardHeader>
            <CardTitle>Delivery Settings</CardTitle>
            <CardDescription>Control when and how notifications are sent</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <FormField
              control={form.control}
              name="batchEmails"
              render={({ field }) => (
                <FormItem className="flex items-center justify-between">
                  <div>
                    <FormLabel>Batch Email Notifications</FormLabel>
                    <FormDescription>Combine multiple notifications into digest emails</FormDescription>
                  </div>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />
            
            {form.watch('batchEmails') && (
              <FormField
                control={form.control}
                name="batchWindowMinutes"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Batch Window (minutes)</FormLabel>
                    <FormControl>
                      <Input 
                        type="number" 
                        {...field} 
                        onChange={e => field.onChange(parseInt(e.target.value))}
                      />
                    </FormControl>
                  </FormItem>
                )}
              />
            )}
            
            <FormField
              control={form.control}
              name="quietHoursEnabled"
              render={({ field }) => (
                <FormItem className="flex items-center justify-between">
                  <div>
                    <FormLabel>Quiet Hours</FormLabel>
                    <FormDescription>Pause non-critical notifications during set hours</FormDescription>
                  </div>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <Button type="submit">Save Preferences</Button>
      </form>
    </Form>
  );
}
```

---

## 13.9 Acceptance Criteria

### Priority Routing
- [ ] Critical notifications delivered immediately to all enabled channels
- [ ] High priority notifications delivered within 5 minutes
- [ ] Medium priority notifications batched appropriately
- [ ] Low priority notifications only appear in-app

### In-App Notifications
- [ ] Notification center shows unread count
- [ ] Real-time updates via WebSocket
- [ ] Toast notifications for high priority items
- [ ] Mark as read on view/action

### Email Notifications
- [ ] Templates render correctly across email clients
- [ ] Batch emails combine multiple notifications
- [ ] Action links work correctly
- [ ] Unsubscribe links function properly

### User Preferences
- [ ] All channels can be toggled independently
- [ ] Priority thresholds are respected
- [ ] Quiet hours defer non-critical notifications
- [ ] Webhook delivery with signature verification

---

## 13.10 Related Specifications

- [Resilient Execution System](./12-resilient-execution-system.md) — Escalation triggers
- [AI Chat UI](./08-ai-chat-ui.md) — Notification display integration
