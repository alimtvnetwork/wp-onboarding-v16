export interface SiteHealthSummary {
  siteId: number;
  siteName: string;
  siteUrl: string;
  currentStatus: string;
  lastCheckedAt?: string;
  avgResponseMs: number;
  uptimePercent: number;
  totalChecks: number;
  healthyChecks: number;
  downChecks: number;
  lastErrorAt?: string;
  lastError?: string;
  consecutiveDown: number;
  uploaderVersion?: string;
}

export interface SiteHealthStats {
  totalSites: number;
  healthySites: number;
  degradedSites: number;
  downSites: number;
  unknownSites: number;
  avgResponseMs: number;
  avgUptime: number;
}
