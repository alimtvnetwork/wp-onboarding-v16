# Issue #13: Go `buildWPClient` Undefined Method

> **Date Fixed:** 2026-02-09  
> **Category:** Backend/Go  
> **Severity:** Compilation error — backend won't build

---

## Symptom

```
internal\services\site\service.go:1141:19: s.buildWPClient undefined (type *Service has no field or method buildWPClient)
```

## Root Cause

`CheckRemotePluginExists` method referenced `s.buildWPClient(ctx, siteID)` which was never defined on the `*Service` type. The method was likely a planned abstraction that was never implemented.

## Fix

Replaced with the standard credential-decryption pattern used by other methods in the same service:

```go
site, err := s.GetByID(ctx, siteID)
password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
client := s.wpClientFactory(site.URL, site.Username, string(password), nil)
return client.CheckPluginExistsViaUploader(pluginSlug)
```

## Prevention Pattern

**Rule:** When adding new service methods, use existing patterns from the same file. Never reference methods that don't exist yet without implementing them first.
