# User Management — Field Reference

## Core WordPress Fields

| Field             | JSON Key          | WP Function/Column     | Create | Update | Export | Notes                    |
|-------------------|-------------------|------------------------|--------|--------|--------|--------------------------|
| ID                | `Id`              | `ID`                   | —      | —      | ✅     | Read-only                |
| Username          | `Username`        | `user_login`           | ✅ req | —      | ✅     | Immutable after creation |
| Email             | `Email`           | `user_email`           | ✅ req | ✅     | ✅     | Required                 |
| Password          | `Password`        | `user_pass`            | ✅ req | ✅     | hashed | Never returned in GET    |
| First Name        | `FirstName`       | `first_name` (meta)    | ✅     | ✅     | ✅     |                          |
| Last Name         | `LastName`        | `last_name` (meta)     | ✅     | ✅     | ✅     |                          |
| Display Name      | `DisplayName`     | `display_name`         | ✅     | ✅     | ✅     |                          |
| Nickname          | `Nickname`        | `nickname` (meta)      | ✅     | ✅     | ✅     |                          |
| Website           | `Website`         | `user_url`             | ✅     | ✅     | ✅     |                          |
| Biographical Info | `Bio`             | `description` (meta)   | ✅     | ✅     | ✅     |                          |
| Role              | `Role`            | `role`                 | ✅     | ✅     | ✅     | See allowed roles below  |
| Registered Date   | `RegisteredAt`    | `user_registered`      | —      | —      | ✅     | Read-only                |

## Social Profile Fields (Contact Info)

These are stored as `user_meta` keys.

| Field              | JSON Key                | Meta Key        | Notes              |
|--------------------|-------------------------|-----------------|--------------------|
| Facebook URL       | `Social.Facebook`       | `facebook`      |                    |
| Instagram URL      | `Social.Instagram`      | `instagram`     |                    |
| LinkedIn URL       | `Social.LinkedIn`       | `linkedin`      |                    |
| MySpace URL        | `Social.MySpace`        | `myspace`       |                    |
| Pinterest URL      | `Social.Pinterest`      | `pinterest`     |                    |
| SoundCloud URL     | `Social.SoundCloud`     | `soundcloud`    |                    |
| Tumblr URL         | `Social.Tumblr`         | `tumblr`        |                    |
| Wikipedia URL      | `Social.Wikipedia`      | `wikipedia`     |                    |
| X (Twitter)        | `Social.X`              | `twitter`       | WP meta key is still `twitter` |
| YouTube URL        | `Social.YouTube`        | `youtube`       |                    |
| Mastodon URL       | `Social.Mastodon`       | `mastodon`      |                    |

## Yoast SEO Schema Fields

All stored as `user_meta` with the `wpseo_` prefix. Only processed when Yoast
SEO is active.

### Basic Information

| Field              | JSON Key                    | Meta Key                         |
|--------------------|-----------------------------|----------------------------------|
| Honorific Prefix   | `Yoast.HonorificPrefix`     | `wpseo_title_prefix`             |
| Honorific Suffix   | `Yoast.HonorificSuffix`     | `wpseo_title_suffix`             |
| Birth Date         | `Yoast.BirthDate`           | `wpseo_birth_date`               |
| Gender             | `Yoast.Gender`              | `wpseo_gender`                   |

### Extra Information

| Field              | JSON Key                    | Meta Key                         |
|--------------------|-----------------------------|----------------------------------|
| Awards             | `Yoast.Awards`              | `wpseo_awards`                   |
| Expertise In       | `Yoast.ExpertiseIn`         | `wpseo_expertise`                |
| Languages Spoken   | `Yoast.LanguagesSpoken`     | `wpseo_languages`                |

### Employer Information

| Field              | JSON Key                    | Meta Key                         |
|--------------------|-----------------------------|----------------------------------|
| Job Title          | `Yoast.JobTitle`            | `wpseo_job_title`                |
| Employer Name      | `Yoast.EmployerName`        | `wpseo_employer`                 |

### Yoast SEO Settings

| Field                        | JSON Key                         | Meta Key                    |
|------------------------------|----------------------------------|-----------------------------|
| Author Page Title            | `Yoast.AuthorTitle`              | `wpseo_title`               |
| Author Page Meta Description | `Yoast.AuthorMetaDescription`    | `wpseo_metadesc`            |
| Pronouns                     | `Yoast.Pronouns`                 | `wpseo_pronouns`            |

## Allowed Roles

All standard WordPress roles are assignable:

| Role          | Slug            |
|---------------|-----------------|
| Administrator | `administrator` |
| Editor        | `editor`        |
| Author        | `author`        |
| Contributor   | `contributor`   |
| Subscriber    | `subscriber`    |
