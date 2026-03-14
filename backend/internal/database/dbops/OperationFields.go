package dbops

// OperationFields holds typed metadata for database operation logging (GE pattern).
type OperationFields struct {
	// Domain context (set by callers)
	SiteId     int64  `json:",omitempty"`
	PluginId   int64  `json:",omitempty"`
	MappingId  int64  `json:",omitempty"`
	Url        string `json:",omitempty"`
	Path       string `json:",omitempty"`
	RemoteSlug string `json:",omitempty"`
	PluginName string `json:",omitempty"`
	SiteName   string `json:",omitempty"`
	Version    string `json:",omitempty"`
	Category   string `json:",omitempty"`

	// Operation context (set internally by dbops)
	Table        string `json:",omitempty"`
	Operation    string `json:",omitempty"`
	AffectedRows int64  `json:",omitempty"`
	Caller       string `json:",omitempty"`
	Error        string `json:",omitempty"`
	StackTrace   string `json:",omitempty"`
	Id           int64  `json:",omitempty"`
	IsCreated    bool   `json:",omitempty"`
	IsExists     bool   `json:",omitempty"`
	LastInsertId int64  `json:",omitempty"`
	Note         string `json:",omitempty"`
}

// toKeyvals converts the struct to a flat key-value slice for the logger.
func (f OperationFields) toKeyvals() []any {
	var kv []any

	if f.SiteId != 0 {
		kv = append(kv, "siteId", f.SiteId)
	}
	if f.PluginId != 0 {
		kv = append(kv, "pluginId", f.PluginId)
	}
	if f.MappingId != 0 {
		kv = append(kv, "mappingId", f.MappingId)
	}
	if f.Url != "" {
		kv = append(kv, "url", f.Url)
	}
	if f.Path != "" {
		kv = append(kv, "path", f.Path)
	}
	if f.RemoteSlug != "" {
		kv = append(kv, "remoteSlug", f.RemoteSlug)
	}
	if f.PluginName != "" {
		kv = append(kv, "pluginName", f.PluginName)
	}
	if f.SiteName != "" {
		kv = append(kv, "siteName", f.SiteName)
	}
	if f.Version != "" {
		kv = append(kv, "version", f.Version)
	}
	if f.Category != "" {
		kv = append(kv, "category", f.Category)
	}
	if f.Table != "" {
		kv = append(kv, "table", f.Table)
	}
	if f.Operation != "" {
		kv = append(kv, "operation", f.Operation)
	}
	if f.AffectedRows != 0 {
		kv = append(kv, "affectedRows", f.AffectedRows)
	}
	if f.Caller != "" {
		kv = append(kv, "caller", f.Caller)
	}
	if f.Error != "" {
		kv = append(kv, "error", f.Error)
	}
	if f.StackTrace != "" {
		kv = append(kv, "stackTrace", f.StackTrace)
	}
	if f.Id != 0 {
		kv = append(kv, "id", f.Id)
	}
	if f.IsCreated {
		kv = append(kv, "created", f.IsCreated)
	}
	if f.IsExists {
		kv = append(kv, "exists", f.IsExists)
	}
	if f.LastInsertId != 0 {
		kv = append(kv, "lastInsertId", f.LastInsertId)
	}
	if f.Note != "" {
		kv = append(kv, "note", f.Note)
	}

	return kv
}

// mergeFields overlays non-zero extra fields onto base.
func mergeFields(base, extra OperationFields) OperationFields {
	result := base

	if extra.SiteId != 0 {
		result.SiteId = extra.SiteId
	}
	if extra.PluginId != 0 {
		result.PluginId = extra.PluginId
	}
	if extra.MappingId != 0 {
		result.MappingId = extra.MappingId
	}
	if extra.Url != "" {
		result.Url = extra.Url
	}
	if extra.Path != "" {
		result.Path = extra.Path
	}
	if extra.RemoteSlug != "" {
		result.RemoteSlug = extra.RemoteSlug
	}
	if extra.PluginName != "" {
		result.PluginName = extra.PluginName
	}
	if extra.SiteName != "" {
		result.SiteName = extra.SiteName
	}
	if extra.Version != "" {
		result.Version = extra.Version
	}
	if extra.Category != "" {
		result.Category = extra.Category
	}
	if extra.Table != "" {
		result.Table = extra.Table
	}
	if extra.Operation != "" {
		result.Operation = extra.Operation
	}
	if extra.AffectedRows != 0 {
		result.AffectedRows = extra.AffectedRows
	}
	if extra.Caller != "" {
		result.Caller = extra.Caller
	}
	if extra.Error != "" {
		result.Error = extra.Error
	}
	if extra.StackTrace != "" {
		result.StackTrace = extra.StackTrace
	}
	if extra.Id != 0 {
		result.Id = extra.Id
	}
	if extra.IsCreated {
		result.IsCreated = extra.IsCreated
	}
	if extra.IsExists {
		result.IsExists = extra.IsExists
	}
	if extra.LastInsertId != 0 {
		result.LastInsertId = extra.LastInsertId
	}
	if extra.Note != "" {
		result.Note = extra.Note
	}

	return result
}
