// TypeScript types for WordPress User Management (mirrors Go/PHP UserTypes).

export interface UserSocial {
  Facebook?: string;
  Instagram?: string;
  LinkedIn?: string;
  MySpace?: string;
  Pinterest?: string;
  SoundCloud?: string;
  Tumblr?: string;
  Wikipedia?: string;
  X?: string;
  YouTube?: string;
  Mastodon?: string;
}

export interface UserYoast {
  HonorificPrefix?: string;
  HonorificSuffix?: string;
  BirthDate?: string;
  Gender?: string;
  Awards?: string;
  ExpertiseIn?: string;
  LanguagesSpoken?: string;
  JobTitle?: string;
  EmployerName?: string;
  AuthorTitle?: string;
  AuthorMetaDescription?: string;
  Pronouns?: string;
}

export interface WPUser {
  Id: number;
  Username: string;
  Email: string;
  FirstName?: string;
  LastName?: string;
  DisplayName?: string;
  Nickname?: string;
  Website?: string;
  Bio?: string;
  Role: string;
  RegisteredAt?: string;
  Social?: UserSocial;
  Yoast?: UserYoast;
}

export interface WPUserSummary {
  Id: number;
  Username: string;
  Email: string;
  DisplayName?: string;
  Role: string;
  RegisteredAt?: string;
}

export interface UserCreateInput {
  Username: string;
  Email: string;
  Password: string;
  FirstName?: string;
  LastName?: string;
  DisplayName?: string;
  Nickname?: string;
  Website?: string;
  Bio?: string;
  Role?: string;
  Social?: UserSocial;
  Yoast?: UserYoast;
  CreateAppPassword?: boolean;
  AppPasswordName?: string;
}

export interface UserUpdateInput {
  Email?: string;
  Password?: string;
  FirstName?: string;
  LastName?: string;
  DisplayName?: string;
  Nickname?: string;
  Website?: string;
  Bio?: string;
  Role?: string;
  Social?: UserSocial;
  Yoast?: UserYoast;
}

export interface UserCreateResult {
  Id: number;
  Username: string;
  Email: string;
  Role: string;
  AppPassword?: string;
}

export interface UserUpdateResult {
  Id: number;
  Updated: boolean;
  FieldsModified: string[];
}

export interface UserDeleteResult {
  Deleted: boolean;
  ReassignedTo?: number;
}

export interface UserImportResult {
  Created: number;
  Updated: number;
  Skipped: number;
  Errors: Array<{ Row?: number; Username: string; Error: string }>;
}

export type WPRole = "administrator" | "editor" | "author" | "contributor" | "subscriber";

export const WP_ROLES: { value: WPRole; label: string }[] = [
  { value: "administrator", label: "Administrator" },
  { value: "editor", label: "Editor" },
  { value: "author", label: "Author" },
  { value: "contributor", label: "Contributor" },
  { value: "subscriber", label: "Subscriber" },
];
