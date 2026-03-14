package wordpress

// UserSocial contains social profile URLs for a WordPress user.
type UserSocial struct {
	Facebook   string `json:"Facebook,omitempty"`
	Instagram  string `json:"Instagram,omitempty"`
	LinkedIn   string `json:"LinkedIn,omitempty"`
	MySpace    string `json:"MySpace,omitempty"`
	Pinterest  string `json:"Pinterest,omitempty"`
	SoundCloud string `json:"SoundCloud,omitempty"`
	Tumblr     string `json:"Tumblr,omitempty"`
	Wikipedia  string `json:"Wikipedia,omitempty"`
	X          string `json:"X,omitempty"`
	YouTube    string `json:"YouTube,omitempty"`
	Mastodon   string `json:"Mastodon,omitempty"`
}

// UserYoast contains Yoast SEO Schema metadata for a WordPress user.
type UserYoast struct {
	HonorificPrefix      string `json:"HonorificPrefix,omitempty"`
	HonorificSuffix      string `json:"HonorificSuffix,omitempty"`
	BirthDate            string `json:"BirthDate,omitempty"`
	Gender               string `json:"Gender,omitempty"`
	Awards               string `json:"Awards,omitempty"`
	ExpertiseIn          string `json:"ExpertiseIn,omitempty"`
	LanguagesSpoken      string `json:"LanguagesSpoken,omitempty"`
	JobTitle             string `json:"JobTitle,omitempty"`
	EmployerName         string `json:"EmployerName,omitempty"`
	AuthorTitle          string `json:"AuthorTitle,omitempty"`
	AuthorMetaDescription string `json:"AuthorMetaDescription,omitempty"`
	Pronouns             string `json:"Pronouns,omitempty"`
}

// UserResponse represents a full user object returned from the PHP API.
type UserResponse struct {
	Id           int         `json:"Id"`
	Username     string      `json:"Username"`
	Email        string      `json:"Email"`
	FirstName    string      `json:"FirstName,omitempty"`
	LastName     string      `json:"LastName,omitempty"`
	DisplayName  string      `json:"DisplayName,omitempty"`
	Nickname     string      `json:"Nickname,omitempty"`
	Website      string      `json:"Website,omitempty"`
	Bio          string      `json:"Bio,omitempty"`
	Role         string      `json:"Role"`
	RegisteredAt string      `json:"RegisteredAt,omitempty"`
	Social       *UserSocial `json:"Social,omitempty"`
	Yoast        *UserYoast  `json:"Yoast,omitempty"`
}

// UserSummary represents a condensed user object for list responses.
type UserSummary struct {
	Id           int    `json:"Id"`
	Username     string `json:"Username"`
	Email        string `json:"Email"`
	DisplayName  string `json:"DisplayName,omitempty"`
	Role         string `json:"Role"`
	RegisteredAt string `json:"RegisteredAt,omitempty"`
}

// UserCreateRequest is the payload for creating a new WordPress user.
type UserCreateRequest struct {
	Username          string      `json:"Username"`
	Email             string      `json:"Email"`
	Password          string      `json:"Password"`
	FirstName         string      `json:"FirstName,omitempty"`
	LastName          string      `json:"LastName,omitempty"`
	DisplayName       string      `json:"DisplayName,omitempty"`
	Nickname          string      `json:"Nickname,omitempty"`
	Website           string      `json:"Website,omitempty"`
	Bio               string      `json:"Bio,omitempty"`
	Role              string      `json:"Role,omitempty"`
	Social            *UserSocial `json:"Social,omitempty"`
	Yoast             *UserYoast  `json:"Yoast,omitempty"`
	CreateAppPassword bool        `json:"CreateAppPassword,omitempty"`
	AppPasswordName   string      `json:"AppPasswordName,omitempty"`
}

// UserUpdateRequest is the payload for updating a WordPress user.
type UserUpdateRequest struct {
	Email       string      `json:"Email,omitempty"`
	Password    string      `json:"Password,omitempty"`
	FirstName   string      `json:"FirstName,omitempty"`
	LastName    string      `json:"LastName,omitempty"`
	DisplayName string      `json:"DisplayName,omitempty"`
	Nickname    string      `json:"Nickname,omitempty"`
	Website     string      `json:"Website,omitempty"`
	Bio         string      `json:"Bio,omitempty"`
	Role        string      `json:"Role,omitempty"`
	Social      *UserSocial `json:"Social,omitempty"`
	Yoast       *UserYoast  `json:"Yoast,omitempty"`
}

// UserCreateResult is the response from creating a user.
type UserCreateResult struct {
	Id          int    `json:"Id"`
	Username    string `json:"Username"`
	Email       string `json:"Email"`
	Role        string `json:"Role"`
	AppPassword string `json:"AppPassword,omitempty"`
}

// UserUpdateResult is the response from updating a user.
type UserUpdateResult struct {
	Id             int      `json:"Id"`
	Updated        bool     `json:"Updated"`
	FieldsModified []string `json:"FieldsModified"`
}

// UserDeleteResult is the response from deleting a user.
type UserDeleteResult struct {
	Deleted      bool `json:"Deleted"`
	ReassignedTo int  `json:"ReassignedTo,omitempty"`
}

// AppPasswordCreateRequest is the payload for creating an app password.
type AppPasswordCreateRequest struct {
	UserId int    `json:"UserId"`
	Name   string `json:"Name"`
}

// AppPasswordCreateResult is the response from creating an app password.
type AppPasswordCreateResult struct {
	UserId   int    `json:"UserId"`
	Name     string `json:"Name"`
	Password string `json:"Password"`
	Uuid     string `json:"Uuid"`
}

// AppPasswordRevokeRequest is the payload for revoking an app password.
type AppPasswordRevokeRequest struct {
	UserId int    `json:"UserId"`
	Uuid   string `json:"Uuid"`
}

// UserImportResult is the response from CSV/SQLite import.
type UserImportResult struct {
	Created int                      `json:"Created"`
	Updated int                      `json:"Updated"`
	Skipped int                      `json:"Skipped"`
	Errors  []UserImportErrorDetail  `json:"Errors"`
}

// UserImportErrorDetail describes a single import error.
type UserImportErrorDetail struct {
	Row      int    `json:"Row,omitempty"`
	Username string `json:"Username"`
	Error    string `json:"Error"`
}
