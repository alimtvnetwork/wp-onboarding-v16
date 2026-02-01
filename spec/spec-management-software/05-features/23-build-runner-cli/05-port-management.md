# Port Management

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Port availability checking, fallback management, and firewall rule configuration for the Build Runner CLI.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Configuration](./03-configuration.md)
- [Core Architecture](./01-core-architecture.md)

---

## Port Manager

### Interface

```go
type PortManager struct {
    config          *PortConfig
    firewallManager *FirewallManager
    logger          *LogService
}

type PortConfig struct {
    Default      int           `json:"default"`
    Fallback     []int         `json:"fallback"`
    CheckTimeout time.Duration `json:"checkTimeout"`
    Firewall     FirewallConfig `json:"firewall"`
}

type PortCheckResult struct {
    Port      int    `json:"port"`
    Available bool   `json:"available"`
    Reason    string `json:"reason,omitempty"`
    Process   string `json:"process,omitempty"`
    PID       int    `json:"pid,omitempty"`
}

type PortResolution struct {
    RequestedPort  int               `json:"requestedPort"`
    AvailablePort  int               `json:"availablePort"`
    CheckedPorts   []PortCheckResult `json:"checkedPorts"`
}
```

### Core Functions

```go
// CheckPort verifies if a port is available
func (pm *PortManager) CheckPort(port int) (*PortCheckResult, error) {
    // Try to listen on the port
    listener, err := net.Listen("tcp", fmt.Sprintf(":%d", port))
    if err != nil {
        // Port is in use, get process info
        processInfo := pm.getProcessOnPort(port)
        return &PortCheckResult{
            Port:      port,
            Available: false,
            Reason:    "in use",
            Process:   processInfo.Name,
            PID:       processInfo.PID,
        }, nil
    }
    listener.Close()
    
    return &PortCheckResult{
        Port:      port,
        Available: true,
    }, nil
}

// ResolvePort finds an available port with fallback
func (pm *PortManager) ResolvePort(primary int, fallback []int) (*PortResolution, error) {
    resolution := &PortResolution{
        RequestedPort: primary,
        CheckedPorts:  make([]PortCheckResult, 0),
    }
    
    // Check primary port
    result, err := pm.CheckPort(primary)
    if err != nil {
        return nil, err
    }
    resolution.CheckedPorts = append(resolution.CheckedPorts, *result)
    
    if result.Available {
        resolution.AvailablePort = primary
        return resolution, nil
    }
    
    // Check fallback ports
    for _, port := range fallback {
        result, err := pm.CheckPort(port)
        if err != nil {
            continue
        }
        resolution.CheckedPorts = append(resolution.CheckedPorts, *result)
        
        if result.Available {
            resolution.AvailablePort = port
            return resolution, nil
        }
    }
    
    return resolution, fmt.Errorf("no available port found")
}
```

---

## Process Detection

### Windows

```go
func (pm *PortManager) getProcessOnPortWindows(port int) ProcessInfo {
    // Use netstat to find process
    cmd := exec.Command("netstat", "-ano", "-p", "TCP")
    output, err := cmd.Output()
    if err != nil {
        return ProcessInfo{}
    }
    
    // Parse: TCP    0.0.0.0:8080    0.0.0.0:0    LISTENING    1234
    pattern := regexp.MustCompile(fmt.Sprintf(`TCP\s+[\d.]+:%d\s+[\d.]+:\d+\s+LISTENING\s+(\d+)`, port))
    matches := pattern.FindStringSubmatch(string(output))
    
    if len(matches) > 1 {
        pid, _ := strconv.Atoi(matches[1])
        return ProcessInfo{
            PID:  pid,
            Name: pm.getProcessName(pid),
        }
    }
    
    return ProcessInfo{}
}
```

### Linux

```go
func (pm *PortManager) getProcessOnPortLinux(port int) ProcessInfo {
    // Try ss first (faster)
    cmd := exec.Command("ss", "-tlnp", fmt.Sprintf("sport = :%d", port))
    output, err := cmd.Output()
    if err != nil {
        // Fallback to netstat
        cmd = exec.Command("netstat", "-tlnp")
        output, _ = cmd.Output()
    }
    
    // Parse output to extract PID and process name
    // ...
}
```

### macOS

```go
func (pm *PortManager) getProcessOnPortMacOS(port int) ProcessInfo {
    cmd := exec.Command("lsof", "-i", fmt.Sprintf(":%d", port), "-t")
    output, err := cmd.Output()
    if err != nil {
        return ProcessInfo{}
    }
    
    pid, _ := strconv.Atoi(strings.TrimSpace(string(output)))
    return ProcessInfo{
        PID:  pid,
        Name: pm.getProcessName(pid),
    }
}
```

---

## Firewall Management

### Interface

```go
type FirewallManager struct {
    enabled  bool
    ruleName string
    logger   *LogService
}

type FirewallRule struct {
    Name      string `json:"name"`
    Port      int    `json:"port"`
    Protocol  string `json:"protocol"` // tcp, udp, both
    Direction string `json:"direction"` // in, out, both
    Action    string `json:"action"` // allow, block
    Enabled   bool   `json:"enabled"`
}

func (fm *FirewallManager) EnablePort(port int, name string, protocol string) error
func (fm *FirewallManager) DisablePort(port int) error
func (fm *FirewallManager) ListRules() ([]FirewallRule, error)
func (fm *FirewallManager) RuleExists(port int) (bool, error)
```

### Windows Implementation (netsh)

```go
func (fm *FirewallManager) enablePortWindows(port int, name string, protocol string) error {
    ruleName := fmt.Sprintf("%s-%d", name, port)
    
    // Add inbound rule
    cmd := exec.Command("netsh", "advfirewall", "firewall", "add", "rule",
        "name="+ruleName,
        "dir=in",
        "action=allow",
        "protocol="+protocol,
        fmt.Sprintf("localport=%d", port),
    )
    
    output, err := cmd.CombinedOutput()
    if err != nil {
        return fmt.Errorf("failed to add firewall rule: %s", string(output))
    }
    
    fm.logger.Info("Firewall rule added", "name", ruleName, "port", port)
    return nil
}

func (fm *FirewallManager) disablePortWindows(port int) error {
    // Find and remove rules matching the port
    cmd := exec.Command("netsh", "advfirewall", "firewall", "delete", "rule",
        fmt.Sprintf("name=%s-%d", fm.ruleName, port),
    )
    
    return cmd.Run()
}

func (fm *FirewallManager) listRulesWindows() ([]FirewallRule, error) {
    cmd := exec.Command("netsh", "advfirewall", "firewall", "show", "rule", 
        fmt.Sprintf("name=%s*", fm.ruleName))
    output, err := cmd.Output()
    if err != nil {
        return nil, err
    }
    
    // Parse output into FirewallRule structs
    return parseNetshOutput(string(output)), nil
}
```

### Linux Implementation (iptables/ufw)

```go
func (fm *FirewallManager) enablePortLinux(port int, name string, protocol string) error {
    // Check if ufw is available
    if fm.hasUFW() {
        return fm.enablePortUFW(port, protocol)
    }
    
    // Fallback to iptables
    return fm.enablePortIPTables(port, protocol)
}

func (fm *FirewallManager) enablePortUFW(port int, protocol string) error {
    cmd := exec.Command("sudo", "ufw", "allow", fmt.Sprintf("%d/%s", port, protocol))
    return cmd.Run()
}

func (fm *FirewallManager) enablePortIPTables(port int, protocol string) error {
    cmd := exec.Command("sudo", "iptables", "-A", "INPUT",
        "-p", protocol,
        "--dport", strconv.Itoa(port),
        "-j", "ACCEPT",
    )
    return cmd.Run()
}
```

### macOS Implementation (pfctl)

```go
func (fm *FirewallManager) enablePortMacOS(port int, name string, protocol string) error {
    // macOS uses pf (Packet Filter)
    rule := fmt.Sprintf("pass in proto %s from any to any port %d\n", protocol, port)
    
    // Add to pf.conf or use anchor
    anchorFile := fmt.Sprintf("/etc/pf.anchors/%s", fm.ruleName)
    
    // Write rule to anchor file
    err := os.WriteFile(anchorFile, []byte(rule), 0644)
    if err != nil {
        return err
    }
    
    // Load anchor
    cmd := exec.Command("sudo", "pfctl", "-a", fm.ruleName, "-f", anchorFile)
    return cmd.Run()
}
```

---

## Port Command Output

### JSON Output

```json
{
  "requestedPort": 8080,
  "availablePort": 8081,
  "checkedPorts": [
    {
      "port": 8080,
      "available": false,
      "reason": "in use",
      "process": "node",
      "pid": 12345
    },
    {
      "port": 8081,
      "available": true
    }
  ]
}
```

### Text Output

```
Checking port availability...
  ✗ Port 8080: in use by node (PID 12345)
  ✓ Port 8081: available

Available port: 8081
```

---

## Integration with Execution

```go
func (e *ExecutionEngine) executeWithPort(ctx context.Context, cmd *Command, requestedPort int) (*ExecutionResult, error) {
    // Resolve available port
    resolution, err := e.portManager.ResolvePort(requestedPort, e.config.Ports.Fallback)
    if err != nil {
        return nil, fmt.Errorf("no available port: %w", err)
    }
    
    // Enable firewall if configured
    if e.config.Ports.Firewall.AutoEnable {
        err = e.portManager.firewallManager.EnablePort(
            resolution.AvailablePort, 
            e.config.Ports.Firewall.RuleName,
            "tcp",
        )
        if err != nil {
            e.logger.Warn("Failed to enable firewall port", "error", err)
        }
    }
    
    // Set PORT environment variable
    cmd.Env["PORT"] = strconv.Itoa(resolution.AvailablePort)
    
    // Execute command
    result, err := e.execute(ctx, cmd)
    result.Port = resolution.AvailablePort
    
    return result, err
}
```

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Configuration](./03-configuration.md)
- [Error Handling](./06-error-handling.md)
