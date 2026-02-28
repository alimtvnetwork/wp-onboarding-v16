# Memory: coding-standards/go-no-inline-struct-literals
Updated: 2026-02-28

## Rule: Never Pass Struct Literals Inline as Arguments

When calling a function that takes a struct argument, the struct **must** be created as a named variable on a preceding line(s), with each field on its own line. Never construct a struct literal directly inside a function call.

### ❌ Bad — inline struct literal in function argument

```go
appErr := Services.PluginService.UpdateMappingsForPlugin(r.Context(), plugin.UpdatePluginMappingsInput{PluginId: id, SiteIds: input.SiteIds, RemoteSlug: input.RemoteSlug})
```

### ✅ Good — extracted to a named variable with one field per line

```go
mappingsInput := plugin.UpdatePluginMappingsInput{
    PluginId:   id,
    SiteIds:    input.SiteIds,
    RemoteSlug: input.RemoteSlug,
}

appErr := Services.PluginService.UpdateMappingsForPlugin(r.Context(), mappingsInput)
```

### ❌ Bad — inline struct inside another call

```go
ws.Broadcast(s.wsHub, ws.EventAutoPublishTriggered, ws.AutoPublishTriggeredData{
    PluginId: pluginId, PluginName: p.Name, Changes: len(changes), Sites: len(p.Mappings),
})
```

### ✅ Good — extracted variable, one field per line

```go
broadcastData := ws.AutoPublishTriggeredData{
    PluginId:   pluginId,
    PluginName: p.Name,
    Changes:    len(changes),
    Sites:      len(p.Mappings),
}

ws.Broadcast(s.wsHub, ws.EventAutoPublishTriggered, broadcastData)
```

### Why

- **Readability**: Function calls stay short; struct fields are clearly visible on separate lines.
- **Debuggability**: The variable can be inspected or logged before passing.
- **Consistency**: All struct construction follows the same pattern throughout the codebase.

### Scope

Applies to **all** Go code. Includes `ws.Broadcast`, `s.logRemoteAction`, `s.sessionService.Log`, and any other function accepting struct arguments.
