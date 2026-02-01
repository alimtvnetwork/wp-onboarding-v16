# Chat History Branching System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Persistent chat history with multi-path branching support, allowing users to explore alternative AI responses and maintain parallel conversation threads. All history stored in SQLite per project.

**Cross-References:**
- [AI Chat Interface](./20-ai-chat-interface.md) - Parent interface
- [Search Integration](./25-search-integration.md) - Search tracking
- [Project Management](../../03-project-management/00-overview.md) - Project scope

---

## Branching Concept

### Visual Representation

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         CHAT HISTORY TREE                                        │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  [Session: Auth Implementation] Started: 2026-01-29 10:00 AM                    │
│                                                                                  │
│  ● You: Create authentication for my app                                       │
│  │                                                                              │
│  ├─● AI: I'll create JWT-based auth... (Branch 1 - Active)                     │
│  │  │                                                                           │
│  │  ├─● You: Use cookies instead                                               │
│  │  │  │                                                                        │
│  │  │  └─● AI: I'll switch to cookie-based sessions...                         │
│  │  │     │                                                                     │
│  │  │     └─● You: Perfect, continue                                           │
│  │  │        │                                                                  │
│  │  │        └─● AI: [Current message]  ◀ ACTIVE                               │
│  │  │                                                                           │
│  │  └─● You: Add OAuth providers (Branch 1.2)                                  │
│  │     │                                                                        │
│  │     └─● AI: I'll add Google and GitHub OAuth...                             │
│  │                                                                              │
│  └─● AI: Would you prefer session-based or token-based? (Branch 2)             │
│     │                                                                           │
│     └─● You: Session-based                                                      │
│        │                                                                        │
│        └─● AI: Implementing session-based auth...                               │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Branch Selection UI

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  🤖 AI Response                                                    10:30 AM     │
│                                                                                  │
│  I'll create JWT-based authentication with refresh tokens...                    │
│                                                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │                                                                           │  │
│  │  📍 This is the start of Branch 1                                         │  │
│  │                                                                           │  │
│  │  [Fork from here]    [View other branches (1)]    [Make this default]    │  │
│  │                                                                           │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Structures

### TypeScript Interfaces

```typescript
interface ChatSession {
  id: string;
  projectId: string;
  title: string;
  createdAt: Date;
  updatedAt: Date;
  
  // Session metadata
  mode: 'spec' | 'code';
  status: 'active' | 'archived' | 'deleted';
  
  // Branch info
  rootMessageId: string;
  activeBranchId: string;
  totalBranches: number;
  totalMessages: number;
}

interface ChatMessage {
  id: string;
  sessionId: string;
  
  // Content
  role: 'user' | 'assistant' | 'system';
  content: string;
  
  // Branching
  parentId: string | null;      // null for root message
  branchId: string;             // Which branch this belongs to
  branchOrder: number;          // Position within branch
  
  // Alternative responses (for AI messages)
  siblingIds: string[];         // Other AI responses at same point
  isActiveInBranch: boolean;    // Currently shown in branch
  
  // Metadata
  timestamp: Date;
  
  // Attachments
  attachments: Attachment[];
  contextFiles: string[];
  
  // AI-specific
  model?: string;
  tokenCount?: number;
  processingTime?: number;
  
  // Search references
  searchRecordIds: string[];    // Linked search records
}

interface Branch {
  id: string;
  sessionId: string;
  
  // Branch metadata
  name: string;                 // Auto-generated or user-named
  description?: string;
  
  // Tree structure
  parentBranchId: string | null;
  forkPointMessageId: string;   // Where this branch split from parent
  
  // Branch stats
  messageCount: number;
  createdAt: Date;
  lastMessageAt: Date;
  
  // State
  isActive: boolean;
  isDefault: boolean;           // Default branch for session
}

interface ConversationPath {
  id: string;
  sessionId: string;
  
  // Path definition
  messageIds: string[];         // Ordered list of message IDs
  branchIds: string[];          // Branches traversed
  
  // Metadata
  createdAt: Date;
  name?: string;                // Optional user name
}
```

### Go Backend Models

```go
type ChatSession struct {
    ID          string    `gorm:"primaryKey" json:"id"`
    ProjectID   string    `gorm:"index;not null" json:"projectId"`
    Title       string    `json:"title"`
    CreatedAt   time.Time `json:"createdAt"`
    UpdatedAt   time.Time `json:"updatedAt"`
    
    Mode        string    `gorm:"default:spec" json:"mode"`
    Status      string    `gorm:"default:active" json:"status"`
    
    RootMessageID   string `json:"rootMessageId"`
    ActiveBranchID  string `json:"activeBranchId"`
    TotalBranches   int    `gorm:"default:1" json:"totalBranches"`
    TotalMessages   int    `gorm:"default:0" json:"totalMessages"`
    
    Messages []ChatMessage `gorm:"foreignKey:SessionID" json:"-"`
    Branches []Branch      `gorm:"foreignKey:SessionID" json:"-"`
}

type ChatMessage struct {
    ID          string    `gorm:"primaryKey" json:"id"`
    SessionID   string    `gorm:"index;not null" json:"sessionId"`
    
    Role        string    `gorm:"not null" json:"role"`
    Content     string    `gorm:"type:text" json:"content"`
    
    ParentID    *string   `gorm:"index" json:"parentId"`
    BranchID    string    `gorm:"index;not null" json:"branchId"`
    BranchOrder int       `json:"branchOrder"`
    
    SiblingIDs        pq.StringArray `gorm:"type:text" json:"siblingIds"`
    IsActiveInBranch  bool           `gorm:"default:true" json:"isActiveInBranch"`
    
    Timestamp time.Time `json:"timestamp"`
    
    Attachments  JSON   `gorm:"type:jsonb" json:"attachments"`
    ContextFiles JSON   `gorm:"type:jsonb" json:"contextFiles"`
    
    Model          *string `json:"model,omitempty"`
    TokenCount     *int    `json:"tokenCount,omitempty"`
    ProcessingTime *int    `json:"processingTime,omitempty"`
    
    SearchRecordIDs pq.StringArray `gorm:"type:text" json:"searchRecordIds"`
    
    Session ChatSession `gorm:"foreignKey:SessionID" json:"-"`
    Branch  Branch      `gorm:"foreignKey:BranchID" json:"-"`
    Parent  *ChatMessage `gorm:"foreignKey:ParentID" json:"-"`
}

type Branch struct {
    ID          string    `gorm:"primaryKey" json:"id"`
    SessionID   string    `gorm:"index;not null" json:"sessionId"`
    
    Name        string    `json:"name"`
    Description *string   `json:"description,omitempty"`
    
    ParentBranchID     *string `gorm:"index" json:"parentBranchId"`
    ForkPointMessageID string  `json:"forkPointMessageId"`
    
    MessageCount  int       `gorm:"default:0" json:"messageCount"`
    CreatedAt     time.Time `json:"createdAt"`
    LastMessageAt time.Time `json:"lastMessageAt"`
    
    IsActive  bool `gorm:"default:false" json:"isActive"`
    IsDefault bool `gorm:"default:false" json:"isDefault"`
    
    Session      ChatSession  `gorm:"foreignKey:SessionID" json:"-"`
    ParentBranch *Branch      `gorm:"foreignKey:ParentBranchID" json:"-"`
    Messages     []ChatMessage `gorm:"foreignKey:BranchID" json:"-"`
}
```

---

## Branching Operations

### Create Branch (Fork Conversation)

```go
type BranchManager struct {
    db *gorm.DB
}

func (m *BranchManager) CreateBranch(
    sessionID string,
    forkPointMessageID string,
    name string,
) (*Branch, error) {
    // Get fork point message
    var forkPoint ChatMessage
    if err := m.db.First(&forkPoint, "id = ?", forkPointMessageID).Error; err != nil {
        return nil, fmt.Errorf("fork point not found: %w", err)
    }
    
    // Create new branch
    branch := &Branch{
        ID:                 uuid.NewString(),
        SessionID:          sessionID,
        Name:               name,
        ParentBranchID:     &forkPoint.BranchID,
        ForkPointMessageID: forkPointMessageID,
        CreatedAt:          time.Now(),
        LastMessageAt:      time.Now(),
        IsActive:           true,
        IsDefault:          false,
    }
    
    if err := m.db.Create(branch).Error; err != nil {
        return nil, err
    }
    
    // Update session stats
    m.db.Model(&ChatSession{}).Where("id = ?", sessionID).Updates(map[string]interface{}{
        "total_branches":   gorm.Expr("total_branches + 1"),
        "active_branch_id": branch.ID,
    })
    
    return branch, nil
}

func (m *BranchManager) SwitchBranch(sessionID, branchID string) error {
    // Deactivate current branch
    m.db.Model(&Branch{}).Where("session_id = ? AND is_active = ?", sessionID, true).
        Update("is_active", false)
    
    // Activate new branch
    if err := m.db.Model(&Branch{}).Where("id = ?", branchID).
        Update("is_active", true).Error; err != nil {
        return err
    }
    
    // Update session
    return m.db.Model(&ChatSession{}).Where("id = ?", sessionID).
        Update("active_branch_id", branchID).Error
}

func (m *BranchManager) GetBranchMessages(branchID string) ([]ChatMessage, error) {
    var branch Branch
    if err := m.db.First(&branch, "id = ?", branchID).Error; err != nil {
        return nil, err
    }
    
    // Get messages from this branch and all parent branches up to fork point
    messages := []ChatMessage{}
    currentBranchID := branchID
    
    for currentBranchID != "" {
        var branchMessages []ChatMessage
        m.db.Where("branch_id = ? AND is_active_in_branch = ?", currentBranchID, true).
            Order("branch_order ASC").
            Find(&branchMessages)
        
        messages = append(branchMessages, messages...)
        
        var b Branch
        if err := m.db.First(&b, "id = ?", currentBranchID).Error; err != nil {
            break
        }
        
        if b.ParentBranchID == nil {
            break
        }
        currentBranchID = *b.ParentBranchID
    }
    
    return messages, nil
}
```

### Regenerate Response (Create Sibling)

```go
func (m *BranchManager) RegenerateResponse(messageID string) (*ChatMessage, error) {
    var original ChatMessage
    if err := m.db.First(&original, "id = ?", messageID).Error; err != nil {
        return nil, err
    }
    
    if original.Role != "assistant" {
        return nil, fmt.Errorf("can only regenerate assistant messages")
    }
    
    // Get the parent (user message)
    var userMessage ChatMessage
    if err := m.db.First(&userMessage, "id = ?", original.ParentID).Error; err != nil {
        return nil, err
    }
    
    // Create new AI response (will be populated by AI)
    newResponse := &ChatMessage{
        ID:          uuid.NewString(),
        SessionID:   original.SessionID,
        Role:        "assistant",
        Content:     "",  // To be filled by AI
        ParentID:    original.ParentID,
        BranchID:    original.BranchID,
        BranchOrder: original.BranchOrder,
        Timestamp:   time.Now(),
        IsActiveInBranch: true,
    }
    
    // Mark original as inactive
    m.db.Model(&original).Update("is_active_in_branch", false)
    
    // Update sibling references
    siblings := append(original.SiblingIDs, original.ID)
    newResponse.SiblingIDs = siblings
    
    // Update all siblings to include new message
    for _, sibID := range original.SiblingIDs {
        m.db.Model(&ChatMessage{}).Where("id = ?", sibID).
            Update("sibling_ids", append(siblings, newResponse.ID))
    }
    m.db.Model(&original).Update("sibling_ids", append(siblings, newResponse.ID))
    
    if err := m.db.Create(newResponse).Error; err != nil {
        return nil, err
    }
    
    return newResponse, nil
}
```

---

## Database Schema

```sql
-- Chat sessions table
CREATE TABLE chat_sessions (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL,
    title TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    mode TEXT DEFAULT 'spec',         -- 'spec' or 'code'
    status TEXT DEFAULT 'active',     -- 'active', 'archived', 'deleted'
    
    root_message_id TEXT,
    active_branch_id TEXT,
    total_branches INTEGER DEFAULT 1,
    total_messages INTEGER DEFAULT 0,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Branches table
CREATE TABLE branches (
    id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    
    name TEXT NOT NULL,
    description TEXT,
    
    parent_branch_id TEXT,
    fork_point_message_id TEXT NOT NULL,
    
    message_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    is_active BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_branch_id) REFERENCES branches(id)
);

-- Chat messages table
CREATE TABLE chat_messages (
    id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    
    role TEXT NOT NULL,               -- 'user', 'assistant', 'system'
    content TEXT NOT NULL,
    
    parent_id TEXT,                   -- NULL for root
    branch_id TEXT NOT NULL,
    branch_order INTEGER NOT NULL,
    
    sibling_ids TEXT,                 -- JSON array
    is_active_in_branch BOOLEAN DEFAULT TRUE,
    
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    attachments TEXT,                 -- JSON
    context_files TEXT,               -- JSON array
    
    model TEXT,
    token_count INTEGER,
    processing_time INTEGER,
    
    search_record_ids TEXT,           -- JSON array
    
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (parent_id) REFERENCES chat_messages(id)
);

-- Indexes
CREATE INDEX idx_chat_sessions_project ON chat_sessions(project_id);
CREATE INDEX idx_branches_session ON branches(session_id);
CREATE INDEX idx_chat_messages_session ON chat_messages(session_id);
CREATE INDEX idx_chat_messages_branch ON chat_messages(branch_id);
CREATE INDEX idx_chat_messages_parent ON chat_messages(parent_id);
CREATE INDEX idx_chat_messages_active ON chat_messages(branch_id, is_active_in_branch);
```

---

## Component Structure

```
ChatHistory/
├── components/
│   ├── ChatSessionList.tsx         # List of sessions
│   ├── ChatHistory.tsx             # Main chat display
│   ├── BranchSelector.tsx          # Branch dropdown/tree
│   ├── BranchTreeView.tsx          # Visual branch tree
│   ├── MessageBubble.tsx           # Single message
│   ├── MessageSiblings.tsx         # Sibling navigation
│   ├── ForkButton.tsx              # Create branch action
│   ├── RegenerateButton.tsx        # Regenerate response
│   └── BranchIndicator.tsx         # Branch position marker
│
├── hooks/
│   ├── useChatSession.ts           # Session management
│   ├── useBranches.ts              # Branch operations
│   ├── useMessageHistory.ts        # Message loading
│   └── useBranchNavigation.ts      # Navigate between branches
│
└── types/
    └── chat.ts                     # TypeScript interfaces
```

---

## UI Components

### Branch Selector Dropdown

```tsx
interface BranchSelectorProps {
  sessionId: string;
  activeBranchId: string;
  onBranchChange: (branchId: string) => void;
}

export const BranchSelector: React.FC<BranchSelectorProps> = ({
  sessionId,
  activeBranchId,
  onBranchChange
}) => {
  const { branches, isLoading } = useBranches(sessionId);
  
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm">
          <GitBranch className="h-4 w-4 mr-2" />
          {branches.find(b => b.id === activeBranchId)?.name || 'Main'}
          <ChevronDown className="h-4 w-4 ml-2" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel>Conversation Branches</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {branches.map(branch => (
          <DropdownMenuItem
            key={branch.id}
            onClick={() => onBranchChange(branch.id)}
            className={cn(
              branch.id === activeBranchId && "bg-accent"
            )}
          >
            <div className="flex flex-col">
              <span className="font-medium">{branch.name}</span>
              <span className="text-xs text-muted-foreground">
                {branch.messageCount} messages · {formatRelative(branch.lastMessageAt)}
              </span>
            </div>
            {branch.isDefault && (
              <Badge variant="secondary" className="ml-auto">Default</Badge>
            )}
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={() => {}}>
          <GitBranch className="h-4 w-4 mr-2" />
          Create new branch
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
};
```

### Message with Siblings Navigation

```tsx
interface MessageWithSiblingsProps {
  message: ChatMessage;
  onSelectSibling: (messageId: string) => void;
}

export const MessageWithSiblings: React.FC<MessageWithSiblingsProps> = ({
  message,
  onSelectSibling
}) => {
  const siblings = [message.id, ...message.siblingIds];
  const currentIndex = siblings.indexOf(message.id);
  
  if (siblings.length <= 1) {
    return <MessageBubble message={message} />;
  }
  
  return (
    <div className="relative">
      <MessageBubble message={message} />
      
      {/* Sibling navigation */}
      <div className="absolute top-2 right-2 flex items-center gap-1 bg-background/80 rounded-md px-2 py-1">
        <Button
          variant="ghost"
          size="icon"
          className="h-6 w-6"
          disabled={currentIndex === 0}
          onClick={() => onSelectSibling(siblings[currentIndex - 1])}
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <span className="text-xs text-muted-foreground">
          {currentIndex + 1}/{siblings.length}
        </span>
        <Button
          variant="ghost"
          size="icon"
          className="h-6 w-6"
          disabled={currentIndex === siblings.length - 1}
          onClick={() => onSelectSibling(siblings[currentIndex + 1])}
        >
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
};
```

---

## API Endpoints

### Session Management

```
POST   /api/v1/projects/{projectId}/sessions     Create session
GET    /api/v1/projects/{projectId}/sessions     List sessions
GET    /api/v1/sessions/{sessionId}              Get session
DELETE /api/v1/sessions/{sessionId}              Archive session
```

### Branch Operations

```
POST   /api/v1/sessions/{sessionId}/branches           Create branch
GET    /api/v1/sessions/{sessionId}/branches           List branches
GET    /api/v1/branches/{branchId}                     Get branch
PUT    /api/v1/branches/{branchId}                     Update branch
POST   /api/v1/sessions/{sessionId}/branches/switch    Switch active branch
DELETE /api/v1/branches/{branchId}                     Delete branch
```

### Message Operations

```
POST   /api/v1/sessions/{sessionId}/messages           Add message
GET    /api/v1/branches/{branchId}/messages           Get branch messages
GET    /api/v1/messages/{messageId}                    Get message
POST   /api/v1/messages/{messageId}/regenerate         Regenerate AI response
POST   /api/v1/messages/{messageId}/fork               Fork from message
PUT    /api/v1/messages/{messageId}/sibling            Switch to sibling
```

---

## Configuration Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `chat.maxBranches` | int | 20 | Max branches per session |
| `chat.maxMessagesPerBranch` | int | 500 | Max messages per branch |
| `chat.autoNameBranches` | bool | true | Auto-generate branch names |
| `chat.showBranchTree` | bool | false | Show visual tree by default |
| `chat.historyRetention` | int | 90 | Days to keep archived sessions |

---

## Error Codes

| Code | Description |
|------|-------------|
| 12830 | Session not found |
| 12831 | Branch not found |
| 12832 | Message not found |
| 12833 | Cannot fork from this message |
| 12834 | Max branches exceeded |
| 12835 | Cannot delete default branch |
| 12836 | Invalid parent message |
| 12837 | Branch already exists at fork point |
