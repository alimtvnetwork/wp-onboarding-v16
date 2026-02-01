# Sharing System

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Comprehensive sharing system for pipelines supporting multiple distribution methods: direct invitations, share links with configurable access levels, and public/private visibility settings. Integrates with the permissions system for access control.

---

## Database Schema

### ShareLink Table

```sql
CREATE TABLE ShareLink (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Link configuration
  Token           TEXT NOT NULL UNIQUE,     -- Secure random token
  LinkType        TEXT NOT NULL,            -- 'VIEW', 'EXECUTE', 'EDIT', 'TEMPLATE'
  
  -- Access settings
  DefaultRole     TEXT NOT NULL,            -- Role granted on access
  RequireAuth     INTEGER NOT NULL DEFAULT 1, -- Require login to access
  
  -- Limits
  MaxUses         INTEGER,                  -- NULL = unlimited
  UsedCount       INTEGER NOT NULL DEFAULT 0,
  ExpiresAt       TEXT,                     -- NULL = never expires
  
  -- Password protection
  PasswordHash    TEXT,                     -- NULL = no password
  
  -- Metadata
  Name            TEXT,                     -- Optional friendly name
  CreatedBy       TEXT NOT NULL,
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  
  -- Status
  IsActive        INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_share_token ON ShareLink(Token);
CREATE INDEX idx_share_pipeline ON ShareLink(PipelineId);
CREATE INDEX idx_share_active ON ShareLink(IsActive);
```

### ShareInvitation Table

```sql
CREATE TABLE ShareInvitation (
  Id              TEXT PRIMARY KEY,
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Recipient
  Email           TEXT NOT NULL,
  
  -- Invitation details
  Role            TEXT NOT NULL,            -- Granted role
  Message         TEXT,                     -- Personal message
  
  -- Token for accepting
  Token           TEXT NOT NULL UNIQUE,
  
  -- Status
  Status          TEXT NOT NULL DEFAULT 'PENDING', -- 'PENDING', 'ACCEPTED', 'DECLINED', 'EXPIRED'
  
  -- Metadata
  InvitedBy       TEXT NOT NULL,
  InvitedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  ExpiresAt       TEXT NOT NULL,
  RespondedAt     TEXT,
  
  UNIQUE(PipelineId, Email, Status)
);

CREATE INDEX idx_invite_email ON ShareInvitation(Email);
CREATE INDEX idx_invite_token ON ShareInvitation(Token);
CREATE INDEX idx_invite_pipeline ON ShareInvitation(PipelineId);
CREATE INDEX idx_invite_status ON ShareInvitation(Status);
```

### ShareLinkAccess Table

```sql
CREATE TABLE ShareLinkAccess (
  Id              TEXT PRIMARY KEY,
  ShareLinkId     TEXT NOT NULL REFERENCES ShareLink(Id) ON DELETE CASCADE,
  
  -- Who accessed
  UserId          TEXT,                     -- NULL if anonymous (view-only)
  
  -- Access details
  AccessedAt      TEXT NOT NULL DEFAULT (datetime('now')),
  IpAddress       TEXT,
  UserAgent       TEXT,
  
  -- Result
  ResultAction    TEXT NOT NULL             -- 'VIEWED', 'JOINED', 'CLONED'
);

CREATE INDEX idx_access_link ON ShareLinkAccess(ShareLinkId);
CREATE INDEX idx_access_time ON ShareLinkAccess(AccessedAt);
```

### PipelineVisibility Table

```sql
CREATE TABLE PipelineVisibility (
  PipelineId      TEXT PRIMARY KEY REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Visibility settings
  Visibility      TEXT NOT NULL DEFAULT 'PRIVATE', -- 'PRIVATE', 'UNLISTED', 'PUBLIC'
  
  -- Public settings (when PUBLIC)
  AllowFork       INTEGER NOT NULL DEFAULT 0,
  ShowInGallery   INTEGER NOT NULL DEFAULT 0,
  
  -- Discovery
  Tags            TEXT,                     -- JSON array for search
  Category        TEXT,
  
  UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);
```

---

## TypeScript Interfaces

### Share Types

```typescript
enum LinkType {
  VIEW = 'VIEW',           // View-only access
  EXECUTE = 'EXECUTE',     // Can run the pipeline
  EDIT = 'EDIT',           // Can edit the pipeline
  TEMPLATE = 'TEMPLATE'    // Clone as template
}

enum Visibility {
  PRIVATE = 'PRIVATE',     // Only explicit grants
  UNLISTED = 'UNLISTED',   // Accessible via link, not searchable
  PUBLIC = 'PUBLIC'        // Searchable and accessible
}

interface ShareLink {
  readonly id: string;
  readonly pipelineId: string;
  readonly token: string;
  readonly linkType: LinkType;
  readonly defaultRole: PipelineRole;
  readonly requireAuth: boolean;
  readonly maxUses: number | null;
  readonly usedCount: number;
  readonly expiresAt: Date | null;
  readonly hasPassword: boolean;
  readonly name: string | null;
  readonly createdBy: string;
  readonly createdAt: Date;
  readonly isActive: boolean;
}

interface ShareInvitation {
  readonly id: string;
  readonly pipelineId: string;
  readonly email: string;
  readonly role: PipelineRole;
  readonly message: string | null;
  readonly status: InvitationStatus;
  readonly invitedBy: string;
  readonly invitedAt: Date;
  readonly expiresAt: Date;
  readonly respondedAt: Date | null;
}

enum InvitationStatus {
  PENDING = 'PENDING',
  ACCEPTED = 'ACCEPTED',
  DECLINED = 'DECLINED',
  EXPIRED = 'EXPIRED'
}

interface PipelineVisibility {
  readonly pipelineId: string;
  readonly visibility: Visibility;
  readonly allowFork: boolean;
  readonly showInGallery: boolean;
  readonly tags: readonly string[];
  readonly category: string | null;
}
```

### Share Link Configuration

```typescript
interface CreateShareLinkOptions {
  readonly pipelineId: string;
  readonly linkType: LinkType;
  readonly defaultRole?: PipelineRole;
  readonly requireAuth?: boolean;
  readonly maxUses?: number;
  readonly expiresIn?: Duration;         // e.g., { days: 7 }
  readonly password?: string;
  readonly name?: string;
}

interface ShareLinkUrl {
  readonly fullUrl: string;
  readonly token: string;
  readonly copyText: string;
}

interface ResolvedShareLink extends ShareLink {
  readonly pipeline: PipelineSummary;
  readonly createdByName: string;
  readonly isExpired: boolean;
  readonly isExhausted: boolean;         // Max uses reached
  readonly isUsable: boolean;
}
```

---

## Sharing Service

### ShareService

```typescript
interface ShareService {
  // Share links
  createLink(options: CreateShareLinkOptions): Promise<ShareLink>;
  getLink(linkId: string): Promise<ShareLink>;
  getLinkByToken(token: string): Promise<ResolvedShareLink | null>;
  updateLink(linkId: string, updates: ShareLinkUpdates): Promise<ShareLink>;
  deactivateLink(linkId: string): Promise<void>;
  listLinks(pipelineId: string): Promise<readonly ShareLink[]>;
  
  // Access via link
  accessLink(token: string, options: AccessOptions): Promise<AccessResult>;
  recordAccess(linkId: string, access: AccessRecord): Promise<void>;
  
  // Invitations
  invite(invitation: CreateInvitation): Promise<ShareInvitation>;
  inviteBulk(invitations: readonly CreateInvitation[]): Promise<readonly ShareInvitation[]>;
  getInvitation(token: string): Promise<ShareInvitation | null>;
  respondToInvitation(token: string, accept: boolean): Promise<InvitationResponse>;
  cancelInvitation(invitationId: string): Promise<void>;
  resendInvitation(invitationId: string): Promise<void>;
  listInvitations(pipelineId: string): Promise<readonly ShareInvitation[]>;
  listMyInvitations(): Promise<readonly PendingInvitation[]>;
  
  // Visibility
  getVisibility(pipelineId: string): Promise<PipelineVisibility>;
  setVisibility(pipelineId: string, visibility: Visibility): Promise<void>;
  updateVisibilitySettings(pipelineId: string, settings: VisibilitySettings): Promise<void>;
  
  // Public discovery
  searchPublicPipelines(query: PublicSearchQuery): Promise<PublicSearchResult>;
}

interface AccessOptions {
  readonly password?: string;
  readonly userId?: string;
}

interface AccessResult {
  readonly success: boolean;
  readonly error?: AccessError;
  readonly pipeline?: Pipeline;
  readonly grantedRole?: PipelineRole;
  readonly action: 'VIEWED' | 'JOINED' | 'CLONED';
}

enum AccessError {
  NOT_FOUND = 'NOT_FOUND',
  EXPIRED = 'EXPIRED',
  EXHAUSTED = 'EXHAUSTED',
  WRONG_PASSWORD = 'WRONG_PASSWORD',
  AUTH_REQUIRED = 'AUTH_REQUIRED',
  DEACTIVATED = 'DEACTIVATED'
}

interface CreateInvitation {
  readonly pipelineId: string;
  readonly email: string;
  readonly role: PipelineRole;
  readonly message?: string;
  readonly expiresIn?: Duration;
}

interface PendingInvitation extends ShareInvitation {
  readonly pipelineName: string;
  readonly invitedByName: string;
  readonly invitedByEmail: string;
}
```

---

## React Components

### ShareDialog

```typescript
interface ShareDialogProps {
  readonly pipeline: Pipeline;
  readonly open: boolean;
  readonly onOpenChange: (open: boolean) => void;
}

const ShareDialog: React.FC<ShareDialogProps> = ({
  pipeline,
  open,
  onOpenChange
}) => {
  const [activeTab, setActiveTab] = useState<'invite' | 'link' | 'visibility'>('invite');
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Share "{pipeline.name}"</DialogTitle>
        </DialogHeader>
        
        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as typeof activeTab)}>
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="invite">
              <Mail className="h-4 w-4 mr-2" />
              Invite
            </TabsTrigger>
            <TabsTrigger value="link">
              <Link className="h-4 w-4 mr-2" />
              Link
            </TabsTrigger>
            <TabsTrigger value="visibility">
              <Globe className="h-4 w-4 mr-2" />
              Visibility
            </TabsTrigger>
          </TabsList>
          
          <TabsContent value="invite">
            <InviteTab pipelineId={pipeline.id} />
          </TabsContent>
          
          <TabsContent value="link">
            <ShareLinkTab pipelineId={pipeline.id} />
          </TabsContent>
          
          <TabsContent value="visibility">
            <VisibilityTab pipelineId={pipeline.id} />
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
};
```

### InviteTab

```typescript
interface InviteTabProps {
  readonly pipelineId: string;
}

const InviteTab: React.FC<InviteTabProps> = ({ pipelineId }) => {
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<PipelineRole>(PipelineRole.VIEWER);
  const [message, setMessage] = useState('');
  
  // Query existing invitations
  const { data: invitations } = useQuery({
    queryKey: ['share-invitations', pipelineId],
    queryFn: () => fetchInvitations(pipelineId)
  });
  
  // Send invitation
  const inviteMutation = useMutation({
    mutationFn: () => sendInvitation({ pipelineId, email, role, message }),
    onSuccess: () => {
      setEmail('');
      setMessage('');
    }
  });
  
  return (
    <div className="space-y-4 pt-4">
      {/* Email input */}
      <div className="space-y-2">
        <Label>Email address</Label>
        <div className="flex gap-2">
          <Input
            type="email"
            placeholder="colleague@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="flex-1"
          />
          <Select value={role} onValueChange={(v) => setRole(v as PipelineRole)}>
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="VIEWER">Viewer</SelectItem>
              <SelectItem value="EXECUTOR">Executor</SelectItem>
              <SelectItem value="EDITOR">Editor</SelectItem>
              <SelectItem value="ADMIN">Admin</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      
      {/* Optional message */}
      <div className="space-y-2">
        <Label>Message (optional)</Label>
        <Textarea
          placeholder="Add a personal message..."
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          rows={2}
        />
      </div>
      
      <Button
        onClick={() => inviteMutation.mutate()}
        disabled={!email || inviteMutation.isPending}
        className="w-full"
      >
        {inviteMutation.isPending ? 'Sending...' : 'Send Invitation'}
      </Button>
      
      {/* Pending invitations */}
      {invitations && invitations.length > 0 && (
        <div className="space-y-2 pt-4 border-t">
          <Label className="text-muted-foreground">Pending invitations</Label>
          {invitations
            .filter(i => i.status === 'PENDING')
            .map(invitation => (
              <InvitationRow
                key={invitation.id}
                invitation={invitation}
                onCancel={() => cancelInvitation(invitation.id)}
                onResend={() => resendInvitation(invitation.id)}
              />
            ))}
        </div>
      )}
    </div>
  );
};
```

### ShareLinkTab

```typescript
interface ShareLinkTabProps {
  readonly pipelineId: string;
}

const ShareLinkTab: React.FC<ShareLinkTabProps> = ({ pipelineId }) => {
  const [isCreating, setIsCreating] = useState(false);
  
  // Query existing links
  const { data: links } = useQuery({
    queryKey: ['share-links', pipelineId],
    queryFn: () => fetchShareLinks(pipelineId)
  });
  
  const activeLinks = links?.filter(l => l.isActive) ?? [];
  
  return (
    <div className="space-y-4 pt-4">
      {/* Create new link */}
      {!isCreating ? (
        <Button
          variant="outline"
          onClick={() => setIsCreating(true)}
          className="w-full"
        >
          <Plus className="h-4 w-4 mr-2" />
          Create Share Link
        </Button>
      ) : (
        <CreateLinkForm
          pipelineId={pipelineId}
          onCancel={() => setIsCreating(false)}
          onCreated={() => setIsCreating(false)}
        />
      )}
      
      {/* Existing links */}
      {activeLinks.length > 0 && (
        <div className="space-y-2">
          <Label className="text-muted-foreground">Active links</Label>
          {activeLinks.map(link => (
            <ShareLinkRow
              key={link.id}
              link={link}
              onCopy={() => copyLinkToClipboard(link)}
              onDeactivate={() => deactivateLink(link.id)}
            />
          ))}
        </div>
      )}
    </div>
  );
};
```

### ShareLinkRow

```typescript
interface ShareLinkRowProps {
  readonly link: ShareLink;
  readonly onCopy: () => void;
  readonly onDeactivate: () => void;
}

const ShareLinkRow: React.FC<ShareLinkRowProps> = ({
  link,
  onCopy,
  onDeactivate
}) => {
  const isExpired = link.expiresAt && new Date(link.expiresAt) < new Date();
  const isExhausted = link.maxUses && link.usedCount >= link.maxUses;
  
  return (
    <div className={cn(
      "flex items-center justify-between p-3 rounded-lg border",
      (isExpired || isExhausted) && "opacity-50"
    )}>
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <Badge variant="outline">{link.linkType}</Badge>
          {link.name && (
            <span className="text-sm font-medium truncate">{link.name}</span>
          )}
          {link.hasPassword && (
            <Lock className="h-3 w-3 text-muted-foreground" />
          )}
        </div>
        <div className="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
          <span>{link.usedCount} uses</span>
          {link.maxUses && <span>/ {link.maxUses} max</span>}
          {link.expiresAt && (
            <>
              <span>•</span>
              <span>
                {isExpired ? 'Expired' : `Expires ${formatRelativeTime(link.expiresAt)}`}
              </span>
            </>
          )}
        </div>
      </div>
      
      <div className="flex items-center gap-1">
        <Button
          variant="ghost"
          size="icon"
          onClick={onCopy}
          disabled={isExpired || isExhausted}
        >
          <Copy className="h-4 w-4" />
        </Button>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon">
              <MoreVertical className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={onCopy}>
              <Copy className="h-4 w-4 mr-2" />
              Copy Link
            </DropdownMenuItem>
            <DropdownMenuItem>
              <BarChart className="h-4 w-4 mr-2" />
              View Analytics
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              className="text-destructive"
              onClick={onDeactivate}
            >
              <XCircle className="h-4 w-4 mr-2" />
              Deactivate
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  );
};
```

### VisibilityTab

```typescript
interface VisibilityTabProps {
  readonly pipelineId: string;
}

const VisibilityTab: React.FC<VisibilityTabProps> = ({ pipelineId }) => {
  const { data: visibility, isLoading } = useQuery({
    queryKey: ['pipeline-visibility', pipelineId],
    queryFn: () => fetchVisibility(pipelineId)
  });
  
  const updateMutation = useMutation({
    mutationFn: (updates: Partial<PipelineVisibility>) =>
      updateVisibility(pipelineId, updates)
  });
  
  if (isLoading) return <Skeleton className="h-40" />;
  
  return (
    <div className="space-y-4 pt-4">
      {/* Visibility level */}
      <RadioGroup
        value={visibility?.visibility}
        onValueChange={(v) => updateMutation.mutate({ visibility: v as Visibility })}
      >
        <div className="flex items-start space-x-3 p-3 rounded-lg border">
          <RadioGroupItem value="PRIVATE" id="private" className="mt-1" />
          <div>
            <Label htmlFor="private" className="flex items-center gap-2">
              <Lock className="h-4 w-4" />
              Private
            </Label>
            <p className="text-sm text-muted-foreground">
              Only people you invite can access
            </p>
          </div>
        </div>
        
        <div className="flex items-start space-x-3 p-3 rounded-lg border">
          <RadioGroupItem value="UNLISTED" id="unlisted" className="mt-1" />
          <div>
            <Label htmlFor="unlisted" className="flex items-center gap-2">
              <LinkIcon className="h-4 w-4" />
              Unlisted
            </Label>
            <p className="text-sm text-muted-foreground">
              Anyone with the link can access, but not searchable
            </p>
          </div>
        </div>
        
        <div className="flex items-start space-x-3 p-3 rounded-lg border">
          <RadioGroupItem value="PUBLIC" id="public" className="mt-1" />
          <div>
            <Label htmlFor="public" className="flex items-center gap-2">
              <Globe className="h-4 w-4" />
              Public
            </Label>
            <p className="text-sm text-muted-foreground">
              Visible to everyone and searchable in gallery
            </p>
          </div>
        </div>
      </RadioGroup>
      
      {/* Public options */}
      {visibility?.visibility === 'PUBLIC' && (
        <div className="space-y-3 pt-2">
          <div className="flex items-center justify-between">
            <div>
              <Label>Allow forking</Label>
              <p className="text-xs text-muted-foreground">
                Let others create their own copy
              </p>
            </div>
            <Switch
              checked={visibility.allowFork}
              onCheckedChange={(checked) =>
                updateMutation.mutate({ allowFork: checked })
              }
            />
          </div>
          
          <div className="flex items-center justify-between">
            <div>
              <Label>Show in gallery</Label>
              <p className="text-xs text-muted-foreground">
                Feature in public pipeline gallery
              </p>
            </div>
            <Switch
              checked={visibility.showInGallery}
              onCheckedChange={(checked) =>
                updateMutation.mutate({ showInGallery: checked })
              }
            />
          </div>
        </div>
      )}
    </div>
  );
};
```

---

## Email Notifications

### Invitation Email

```typescript
interface InvitationEmailData {
  readonly recipientEmail: string;
  readonly inviterName: string;
  readonly inviterEmail: string;
  readonly pipelineName: string;
  readonly role: PipelineRole;
  readonly message: string | null;
  readonly acceptUrl: string;
  readonly expiresAt: Date;
}

// Email template components
const InvitationEmail: React.FC<InvitationEmailData> = ({
  inviterName,
  pipelineName,
  role,
  message,
  acceptUrl,
  expiresAt
}) => (
  <EmailTemplate>
    <EmailHeading>
      {inviterName} invited you to collaborate
    </EmailHeading>
    <EmailText>
      You've been invited to access <strong>{pipelineName}</strong> as a {formatRole(role)}.
    </EmailText>
    {message && (
      <EmailQuote>{message}</EmailQuote>
    )}
    <EmailButton href={acceptUrl}>
      Accept Invitation
    </EmailButton>
    <EmailFooter>
      This invitation expires on {formatDate(expiresAt)}.
    </EmailFooter>
  </EmailTemplate>
);
```

---

## API Endpoints

```typescript
// Share links
GET    /api/pipelines/:id/share-links       // List share links
POST   /api/pipelines/:id/share-links       // Create share link
PUT    /api/share-links/:id                 // Update share link
DELETE /api/share-links/:id                 // Deactivate share link
GET    /api/share-links/:id/analytics       // Link usage analytics

// Access via link
GET    /api/share/:token                    // Get link info (public)
POST   /api/share/:token/access             // Access pipeline via link

// Invitations
GET    /api/pipelines/:id/invitations       // List sent invitations
POST   /api/pipelines/:id/invitations       // Send invitation
DELETE /api/invitations/:id                 // Cancel invitation
POST   /api/invitations/:id/resend          // Resend invitation

// Accept/decline
GET    /api/invitations/mine                // List received invitations
POST   /api/invitations/:token/respond      // Accept or decline

// Visibility
GET    /api/pipelines/:id/visibility        // Get visibility settings
PUT    /api/pipelines/:id/visibility        // Update visibility

// Public gallery
GET    /api/gallery/pipelines               // Browse public pipelines
```

---

## See Also

- [Permissions](./22-permissions.md) — Role-based access control
- [Collaboration](./24-collaboration.md) — Real-time editing
- [Pipeline Templates](./19-pipeline-templates.md) — Template sharing
