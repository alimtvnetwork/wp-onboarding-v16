# Git Integration

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Git Integration module manages local and remote repository operations for generated code. It handles repository initialization, automatic commits with descriptive messages, GitHub/GitLab OAuth connections, and synchronization workflows.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Repository Structure](./10-repository-structure.md)
- [Project Settings](../02-project-management/03-project-settings.md)

---

## Repository Lifecycle

```
┌─────────────────────────────────────────────────────────────────────┐
│                    REPOSITORY LIFECYCLE                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  1. INITIALIZATION                                                   │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ Project Created → Create Directory → git init → Initial     │ │
│     │                   (under code-repos/)          Commit       │ │
│     └─────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  2. CODE GENERATION CYCLE                                           │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ Pre-check → Generate → Consistency → Build → Commit → Push  │ │
│     │ (git pull)   (write    Check         Check   (local)  (if   │ │
│     │              files)                                  remote)│ │
│     └─────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  3. REMOTE CONNECTION                                               │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ OAuth → Create/Link → git remote add → git push → Update    │ │
│     │ Auth    Remote Repo    origin           --all     README    │ │
│     └─────────────────────────────────────────────────────────────┘ │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Data Models

### RepositoryConnection

```go
type RepositoryConnection struct {
    ID            string    `gorm:"primaryKey;type:text"`
    ProjectID     string    `gorm:"type:text;not null;uniqueIndex"`
    Provider      string    `gorm:"type:text;not null"`  // github, gitlab
    RemoteURL     string    `gorm:"type:text"`
    DefaultBranch string    `gorm:"type:text;default:main"`
    IsConnected   bool      `gorm:"type:boolean;default:false"`
    LastSyncAt    time.Time
    CreatedAt     time.Time
    UpdatedAt     time.Time
    
    // Relationships
    Project       Project `gorm:"foreignKey:ProjectID"`
}
```

### OAuthConnection

```go
type OAuthConnection struct {
    ID           string    `gorm:"primaryKey;type:text"`
    UserID       string    `gorm:"type:text;not null;index"`
    Provider     string    `gorm:"type:text;not null"`  // github, gitlab
    AccessToken  string    `gorm:"type:text;not null"`  // Encrypted
    RefreshToken string    `gorm:"type:text"`           // Encrypted
    ExpiresAt    time.Time
    Scopes       string    `gorm:"type:text"`           // Comma-separated
    Username     string    `gorm:"type:text"`
    AvatarURL    string    `gorm:"type:text"`
    CreatedAt    time.Time
    UpdatedAt    time.Time
    
    // Relationships
    User         User `gorm:"foreignKey:UserID"`
}
```

### CommitRecord

```go
type CommitRecord struct {
    ID               string    `gorm:"primaryKey;type:text"`
    ProjectID        string    `gorm:"type:text;not null;index"`
    CommitHash       string    `gorm:"type:text;not null"`
    Message          string    `gorm:"type:text;not null"`
    Author           string    `gorm:"type:text"`
    FilesChanged     int       `gorm:"type:integer"`
    Insertions       int       `gorm:"type:integer"`
    Deletions        int       `gorm:"type:integer"`
    SpecReferences   string    `gorm:"type:text"`           // JSON array
    GenerationRunID  string    `gorm:"type:text;index"`     // Link to generation
    PushedAt         *time.Time
    CreatedAt        time.Time
}
```

---

## Git Manager

### Core Operations

```go
type GitManager struct {
    repoRoot       string
    localOps       *LocalGitOperations
    remoteOps      *RemoteGitOperations
    oauthManager   *OAuthManager
    commitBuilder  *CommitMessageBuilder
}

// Initialize a new repository for a project
func (g *GitManager) InitRepository(projectID string, projectName string) error {
    repoPath := g.getRepoPath(projectID)
    
    // Create directory structure
    if err := os.MkdirAll(repoPath, 0755); err != nil {
        return fmt.Errorf("failed to create repo directory: %w", err)
    }
    
    // Initialize git
    cmd := exec.Command("git", "init")
    cmd.Dir = repoPath
    if err := cmd.Run(); err != nil {
        return fmt.Errorf("git init failed: %w", err)
    }
    
    // Create initial structure
    if err := g.createInitialStructure(repoPath, projectName); err != nil {
        return err
    }
    
    // Initial commit
    return g.Commit(projectID, "Initial commit: Project structure created", nil)
}

func (g *GitManager) getRepoPath(projectID string) string {
    return filepath.Join(g.repoRoot, projectID)
}

func (g *GitManager) createInitialStructure(repoPath, projectName string) error {
    // Create directories
    dirs := []string{"spec", "BE", "FE"}
    for _, dir := range dirs {
        if err := os.MkdirAll(filepath.Join(repoPath, dir), 0755); err != nil {
            return err
        }
        // Create .gitkeep
        gitkeep := filepath.Join(repoPath, dir, ".gitkeep")
        if err := os.WriteFile(gitkeep, []byte{}, 0644); err != nil {
            return err
        }
    }
    
    // Create README
    readme := g.generateInitialReadme(projectName)
    return os.WriteFile(filepath.Join(repoPath, "README.md"), []byte(readme), 0644)
}
```

### Pre-Commit Workflow

```go
// Always pull before committing to avoid conflicts
func (g *GitManager) PreCommitSync(projectID string) error {
    repoPath := g.getRepoPath(projectID)
    
    // Check if remote is configured
    hasRemote, err := g.hasRemote(repoPath)
    if err != nil {
        return err
    }
    
    if !hasRemote {
        return nil  // No remote, nothing to pull
    }
    
    // Stash local changes
    if err := g.stash(repoPath); err != nil {
        return err
    }
    
    // Pull from remote
    if err := g.pull(repoPath); err != nil {
        // Try to resolve conflicts
        if isConflictError(err) {
            if err := g.resolveConflicts(repoPath); err != nil {
                return fmt.Errorf("failed to resolve conflicts: %w", err)
            }
        } else {
            return err
        }
    }
    
    // Apply stashed changes
    return g.stashPop(repoPath)
}

func (g *GitManager) hasRemote(repoPath string) (bool, error) {
    cmd := exec.Command("git", "remote", "-v")
    cmd.Dir = repoPath
    output, err := cmd.Output()
    if err != nil {
        return false, err
    }
    return len(strings.TrimSpace(string(output))) > 0, nil
}

func (g *GitManager) pull(repoPath string) error {
    cmd := exec.Command("git", "pull", "--rebase", "origin", "main")
    cmd.Dir = repoPath
    return cmd.Run()
}
```

### Commit with Descriptive Messages

```go
type CommitMessageBuilder struct{}

type CommitContext struct {
    GenerationRunID string
    FilesChanged    []string
    SpecReferences  []string
    Phase           string  // writing, consistency, build_fix
}

func (b *CommitMessageBuilder) Build(ctx CommitContext) string {
    var sb strings.Builder
    
    // Title line (50 chars max)
    switch ctx.Phase {
    case "writing":
        sb.WriteString("feat: Generate code from specifications\n\n")
    case "consistency":
        sb.WriteString("fix: Apply consistency corrections\n\n")
    case "build_fix":
        sb.WriteString("fix: Apply build error corrections\n\n")
    }
    
    // Body: Files changed
    sb.WriteString("Files changed:\n")
    for _, file := range ctx.FilesChanged {
        sb.WriteString(fmt.Sprintf("  - %s\n", file))
    }
    sb.WriteString("\n")
    
    // Body: Spec references
    if len(ctx.SpecReferences) > 0 {
        sb.WriteString("Specifications implemented:\n")
        for _, spec := range ctx.SpecReferences {
            sb.WriteString(fmt.Sprintf("  - %s\n", spec))
        }
        sb.WriteString("\n")
    }
    
    // Footer: Generation run ID
    sb.WriteString(fmt.Sprintf("Generation-Run: %s\n", ctx.GenerationRunID))
    
    return sb.String()
}

func (g *GitManager) Commit(projectID string, message string, files []string) error {
    repoPath := g.getRepoPath(projectID)
    
    // Stage files
    if len(files) == 0 {
        // Stage all
        cmd := exec.Command("git", "add", "-A")
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return err
        }
    } else {
        // Stage specific files
        args := append([]string{"add"}, files...)
        cmd := exec.Command("git", args...)
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return err
        }
    }
    
    // Commit
    cmd := exec.Command("git", "commit", "-m", message)
    cmd.Dir = repoPath
    return cmd.Run()
}
```

---

## OAuth Integration

### GitHub OAuth

```go
type GitHubOAuthClient struct {
    clientID     string
    clientSecret string
    redirectURL  string
    httpClient   *http.Client
}

type GitHubAuthConfig struct {
    ClientID     string
    ClientSecret string
    RedirectURL  string
    Scopes       []string  // repo, read:user
}

func (c *GitHubOAuthClient) GetAuthURL(state string) string {
    params := url.Values{
        "client_id":    {c.clientID},
        "redirect_uri": {c.redirectURL},
        "scope":        {"repo,read:user"},
        "state":        {state},
    }
    return "https://github.com/login/oauth/authorize?" + params.Encode()
}

func (c *GitHubOAuthClient) ExchangeCode(code string) (*OAuthTokens, error) {
    data := url.Values{
        "client_id":     {c.clientID},
        "client_secret": {c.clientSecret},
        "code":          {code},
    }
    
    resp, err := c.httpClient.PostForm(
        "https://github.com/login/oauth/access_token",
        data,
    )
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()
    
    // Parse response
    body, _ := io.ReadAll(resp.Body)
    values, _ := url.ParseQuery(string(body))
    
    return &OAuthTokens{
        AccessToken:  values.Get("access_token"),
        TokenType:    values.Get("token_type"),
        Scope:        values.Get("scope"),
    }, nil
}

func (c *GitHubOAuthClient) CreateRepository(
    token string,
    name string,
    private bool,
) (*RepositoryInfo, error) {
    reqBody := map[string]interface{}{
        "name":    name,
        "private": private,
        "auto_init": false,
    }
    
    jsonBody, _ := json.Marshal(reqBody)
    req, _ := http.NewRequest("POST",
        "https://api.github.com/user/repos",
        bytes.NewBuffer(jsonBody),
    )
    req.Header.Set("Authorization", "Bearer "+token)
    req.Header.Set("Accept", "application/vnd.github+json")
    
    resp, err := c.httpClient.Do(req)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()
    
    var repoInfo RepositoryInfo
    json.NewDecoder(resp.Body).Decode(&repoInfo)
    
    return &repoInfo, nil
}
```

### GitLab OAuth

```go
type GitLabOAuthClient struct {
    clientID     string
    clientSecret string
    redirectURL  string
    baseURL      string  // For self-hosted GitLab
    httpClient   *http.Client
}

func (c *GitLabOAuthClient) GetAuthURL(state string) string {
    params := url.Values{
        "client_id":     {c.clientID},
        "redirect_uri":  {c.redirectURL},
        "response_type": {"code"},
        "scope":         {"api read_user"},
        "state":         {state},
    }
    return c.baseURL + "/oauth/authorize?" + params.Encode()
}
```

---

## Remote Operations

### Push Workflow

```go
func (g *GitManager) Push(projectID string, force bool) error {
    repoPath := g.getRepoPath(projectID)
    
    // Get connection
    conn, err := g.getConnection(projectID)
    if err != nil {
        return err
    }
    
    if !conn.IsConnected {
        return errors.New("no remote connection configured")
    }
    
    // Set up credentials
    if err := g.setupCredentials(projectID, conn); err != nil {
        return err
    }
    
    // Push
    args := []string{"push", "origin", conn.DefaultBranch}
    if force {
        args = append(args, "--force")
    }
    
    cmd := exec.Command("git", args...)
    cmd.Dir = repoPath
    output, err := cmd.CombinedOutput()
    
    if err != nil {
        return fmt.Errorf("push failed: %s", string(output))
    }
    
    // Update sync time
    g.updateLastSync(projectID)
    
    return nil
}

func (g *GitManager) setupCredentials(projectID string, conn *RepositoryConnection) error {
    repoPath := g.getRepoPath(projectID)
    
    // Get OAuth token
    oauth, err := g.oauthManager.GetConnection(conn.UserID, conn.Provider)
    if err != nil {
        return err
    }
    
    // Configure credential helper
    // Use git credential store with token
    credURL := fmt.Sprintf("https://%s@%s",
        oauth.AccessToken,
        strings.TrimPrefix(conn.RemoteURL, "https://"),
    )
    
    cmd := exec.Command("git", "remote", "set-url", "origin", credURL)
    cmd.Dir = repoPath
    return cmd.Run()
}
```

### Connect to Remote

```go
func (g *GitManager) ConnectToRemote(
    projectID string,
    userID string,
    provider string,
    createNew bool,
    repoName string,
) (*RepositoryConnection, error) {
    
    // Get OAuth connection
    oauth, err := g.oauthManager.GetConnection(userID, provider)
    if err != nil {
        return nil, fmt.Errorf("no OAuth connection for %s", provider)
    }
    
    var remoteURL string
    
    if createNew {
        // Create new repository
        switch provider {
        case "github":
            repo, err := g.githubClient.CreateRepository(oauth.AccessToken, repoName, true)
            if err != nil {
                return nil, err
            }
            remoteURL = repo.CloneURL
            
        case "gitlab":
            repo, err := g.gitlabClient.CreateRepository(oauth.AccessToken, repoName, true)
            if err != nil {
                return nil, err
            }
            remoteURL = repo.HTTPURLToRepo
        }
    } else {
        // Use provided URL
        remoteURL = repoName  // In this case, repoName is the URL
    }
    
    // Add remote to local repo
    repoPath := g.getRepoPath(projectID)
    cmd := exec.Command("git", "remote", "add", "origin", remoteURL)
    cmd.Dir = repoPath
    if err := cmd.Run(); err != nil {
        // Remote might already exist, try to update
        cmd = exec.Command("git", "remote", "set-url", "origin", remoteURL)
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return nil, err
        }
    }
    
    // Push existing commits
    if err := g.Push(projectID, true); err != nil {
        return nil, err
    }
    
    // Update README with clone instructions
    if err := g.updateReadmeWithRemote(projectID, remoteURL); err != nil {
        // Non-fatal
        log.Printf("Warning: failed to update README: %v", err)
    }
    
    // Save connection
    conn := &RepositoryConnection{
        ID:            uuid.New().String(),
        ProjectID:     projectID,
        Provider:      provider,
        RemoteURL:     remoteURL,
        DefaultBranch: "main",
        IsConnected:   true,
        LastSyncAt:    time.Now(),
    }
    
    return conn, g.db.Save(conn).Error
}
```

---

## Conflict Resolution

```go
type ConflictResolver struct {
    gitManager *GitManager
}

type ConflictInfo struct {
    FilePath    string
    OurChanges  string
    TheirChanges string
    Merged      string
}

func (r *ConflictResolver) DetectConflicts(repoPath string) ([]ConflictInfo, error) {
    cmd := exec.Command("git", "diff", "--name-only", "--diff-filter=U")
    cmd.Dir = repoPath
    output, err := cmd.Output()
    if err != nil {
        return nil, err
    }
    
    files := strings.Split(strings.TrimSpace(string(output)), "\n")
    conflicts := make([]ConflictInfo, 0, len(files))
    
    for _, file := range files {
        if file == "" {
            continue
        }
        conflicts = append(conflicts, ConflictInfo{FilePath: file})
    }
    
    return conflicts, nil
}

func (r *ConflictResolver) ResolveWithOurs(repoPath string, files []string) error {
    for _, file := range files {
        cmd := exec.Command("git", "checkout", "--ours", file)
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return err
        }
        
        cmd = exec.Command("git", "add", file)
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return err
        }
    }
    return nil
}

func (r *ConflictResolver) ResolveWithTheirs(repoPath string, files []string) error {
    for _, file := range files {
        cmd := exec.Command("git", "checkout", "--theirs", file)
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return err
        }
        
        cmd = exec.Command("git", "add", file)
        cmd.Dir = repoPath
        if err := cmd.Run(); err != nil {
            return err
        }
    }
    return nil
}
```

---

## README Generation

```go
func (g *GitManager) generateInitialReadme(projectName string) string {
    return fmt.Sprintf(`# %s

Generated by Spec Management Software

## Project Structure

` + "```" + `
├── spec/          # Specification documents
├── BE/            # Backend code (Go)
├── FE/            # Frontend code (React)
└── README.md      # This file
` + "```" + `

## Local Repository

This is currently a local repository. To connect to GitHub or GitLab:

1. Go to Project Settings > Git Integration
2. Connect your GitHub/GitLab account
3. Create or link a remote repository

## Getting Started

*Instructions will be updated once the repository is connected to a remote.*

---

*Generated on %s*
`, projectName, time.Now().Format("2006-01-02"))
}

func (g *GitManager) updateReadmeWithRemote(projectID, remoteURL string) error {
    repoPath := g.getRepoPath(projectID)
    readmePath := filepath.Join(repoPath, "README.md")
    
    // Read current README
    content, err := os.ReadFile(readmePath)
    if err != nil {
        return err
    }
    
    // Add clone instructions
    cloneSection := fmt.Sprintf(`
## Clone Instructions

` + "```" + `bash
git clone %s
cd %s
` + "```" + `

`, remoteURL, filepath.Base(remoteURL))
    
    // Insert after project structure section
    newContent := strings.Replace(
        string(content),
        "## Local Repository",
        cloneSection+"## Remote Repository\n\nThis repository is connected to: "+remoteURL,
        1,
    )
    
    // Write updated README
    if err := os.WriteFile(readmePath, []byte(newContent), 0644); err != nil {
        return err
    }
    
    // Commit the change
    return g.Commit(projectID, "docs: Update README with clone instructions", []string{"README.md"})
}
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8400 | ERR_GIT_INIT_FAILED | Failed to initialize repository |
| 8401 | ERR_GIT_COMMIT_FAILED | Failed to commit changes |
| 8402 | ERR_GIT_PUSH_FAILED | Failed to push to remote |
| 8403 | ERR_GIT_PULL_FAILED | Failed to pull from remote |
| 8404 | ERR_GIT_CONFLICT | Merge conflict detected |
| 8405 | ERR_GIT_NO_REMOTE | No remote configured |
| 8406 | ERR_OAUTH_NOT_CONNECTED | OAuth not connected for provider |
| 8407 | ERR_OAUTH_TOKEN_EXPIRED | OAuth token expired |
| 8408 | ERR_OAUTH_REFRESH_FAILED | Failed to refresh OAuth token |
| 8409 | ERR_GIT_REPO_CREATE_FAILED | Failed to create remote repository |

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Repository Structure](./10-repository-structure.md)
- [Project Settings](../02-project-management/03-project-settings.md)
