// Utilities for rendering/copying logs that may contain embedded "\\n" sequences.

/**
 * Converts embedded literal "\\n" sequences into real newlines for display.
 *
 * NOTE: This intentionally does NOT attempt full JSON unescaping—only newlines,
 * which are common in stack traces and multi-line responses.
 */
export function unescapeEmbeddedNewlines(input: string): string {
  return String(input)
    .replace(/\\r\\n/g, "\n")
    .replace(/\\n/g, "\n");
}

/**
 * Prepares text for clipboard by:
 * 1) unescaping embedded "\\n" sequences
 * 2) normalizing newlines to CRLF (Windows-friendly)
 */
export function toClipboardText(input: string): string {
  return unescapeEmbeddedNewlines(String(input)).replace(/\r?\n/g, "\r\n");
}

/**
 * Returns an HH:MM:SS 24h timestamp without time zone.
 * - If the input already contains a time token, we extract it.
 * - If it's ISO, we format in UTC via toISOString.
 * - If unparsable, returns the original input.
 */
export function formatTime24h(timestamp: string): string {
  const raw = String(timestamp || "");
  const timeMatch = raw.match(/(\d{2}:\d{2}:\d{2})/);
  if (timeMatch) return timeMatch[1];

  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) {
    return d.toISOString().slice(11, 19);
  }

  return raw;
}

/**
 * Returns YYYY-MM-DD HH:MM:SS 24h in UTC without time zone.
 */
export function formatDateTimeUtc(timestamp: string): string {
  const raw = String(timestamp || "");
  const dtMatch = raw.match(/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/);
  if (dtMatch) return dtMatch[1];

  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) {
    return d.toISOString().slice(0, 19).replace("T", " ");
  }

  return raw;
}
