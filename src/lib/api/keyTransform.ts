// PascalCase → camelCase key transformer for Go backend responses.
//
// Go structs without explicit json tags serialize fields as PascalCase.
// The frontend expects camelCase. This module recursively transforms
// object keys at the API boundary so all downstream code stays unchanged.

/**
 * Convert a single PascalCase key to camelCase.
 *
 * Examples:
 *   PluginId   → pluginId
 *   IsSuccess  → isSuccess
 *   ID         → id
 *   WPVersion  → wpVersion
 *   URL        → url
 *   SiteUrl    → siteUrl
 */
export function pascalToCamel(key: string): string {
  if (!key) return key;

  // Already starts with lowercase — assume camelCase, leave as-is
  if (key[0] === key[0].toLowerCase()) return key;

  // Count leading uppercase chars
  let i = 0;
  while (i < key.length && key[i] === key[i].toUpperCase() && key[i] !== key[i].toLowerCase()) {
    i++;
  }

  if (i === 0) return key;                              // no uppercase prefix
  if (i === key.length) return key.toLowerCase();       // all uppercase (e.g. "ID", "URL")
  if (i === 1) return key[0].toLowerCase() + key.slice(1); // normal PascalCase

  // Multiple uppercase prefix: "WPVersion" → "wpVersion", "HTTPStatus" → "httpStatus"
  return key.slice(0, i - 1).toLowerCase() + key.slice(i - 1);
}

/**
 * Recursively transform all object keys from PascalCase to camelCase.
 * Arrays are traversed element-by-element. Primitives pass through unchanged.
 */
export function transformKeys<T>(value: unknown): T {
  if (value === null || value === undefined) return value as T;
  if (typeof value !== 'object') return value as T;

  if (Array.isArray(value)) {
    return value.map((item) => transformKeys(item)) as T;
  }

  const result: Record<string, unknown> = {};
  for (const [key, val] of Object.entries(value as Record<string, unknown>)) {
    result[pascalToCamel(key)] = transformKeys(val);
  }
  return result as T;
}
