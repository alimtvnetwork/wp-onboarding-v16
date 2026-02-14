package wordpress

// PostStatusType represents WordPress post status values.
type PostStatusType string

const (
	// PostStatusPublish represents a published post.
	PostStatusPublish PostStatusType = "publish"

	// PostStatusDraft represents a draft post.
	PostStatusDraft PostStatusType = "draft"

	// PostStatusPending represents a pending review post.
	PostStatusPending PostStatusType = "pending"
)

// IsEqual checks type-safe equality against another PostStatusType.
func (p PostStatusType) IsEqual(other PostStatusType) bool {
	return p == other
}

// String returns the raw string value.
func (p PostStatusType) String() string {
	return string(p)
}

// IsPublish returns true if the post status is Publish.
func (p PostStatusType) IsPublish() bool {
	return p.IsEqual(PostStatusPublish)
}

// IsDraft returns true if the post status is Draft.
func (p PostStatusType) IsDraft() bool {
	return p.IsEqual(PostStatusDraft)
}

// IsPending returns true if the post status is Pending.
func (p PostStatusType) IsPending() bool {
	return p.IsEqual(PostStatusPending)
}
