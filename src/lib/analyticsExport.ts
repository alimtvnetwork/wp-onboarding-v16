// Export utilities for publish analytics — CSV and PDF report generation.

import type { PublishAnalyticsData } from "@/hooks/usePublishAnalytics";

const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function durationLabel(ms: number): string {
  if (ms === 0) return "—";
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

// ── CSV Export ─────────────────────────────────────────

function toCsvRow(values: (string | number)[]): string {
  return values.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(",");
}

export function exportAnalyticsCsv(data: PublishAnalyticsData): void {
  const lines: string[] = [];

  // Summary
  lines.push("=== SUMMARY (30 Days) ===");
  lines.push(toCsvRow(["Metric", "Value"]));
  lines.push(toCsvRow(["Total Publishes", data.summary.total]));
  lines.push(toCsvRow(["Successes", data.summary.success]));
  lines.push(toCsvRow(["Failures", data.summary.failed]));
  lines.push(toCsvRow(["Success Rate", `${data.summary.total > 0 ? Math.round((data.summary.success / data.summary.total) * 100) : 0}%`]));
  lines.push(toCsvRow(["Avg Duration", durationLabel(data.summary.avgDurationMs)]));
  lines.push(toCsvRow(["Peak Day", data.summary.peakDay]));
  lines.push("");

  // Daily publishes
  lines.push("=== DAILY PUBLISHES ===");
  lines.push(toCsvRow(["Date", "Total", "Success", "Failed", "Partial"]));
  for (const d of data.daily) {
    lines.push(toCsvRow([d.date, d.total, d.success, d.failed, d.partial]));
  }
  lines.push("");

  // Success rate
  lines.push("=== SUCCESS RATE TREND ===");
  lines.push(toCsvRow(["Date", "Rate (%)", "Total"]));
  for (const s of data.successRate) {
    lines.push(toCsvRow([s.date, s.rate, s.total]));
  }
  lines.push("");

  // Duration heatmap
  lines.push("=== DURATION HEATMAP (Avg ms) ===");
  const hours = Array.from({ length: 24 }, (_, i) => `${i}:00`);
  lines.push(toCsvRow(["Day", ...hours]));
  for (let day = 0; day < 7; day++) {
    const row: (string | number)[] = [DAY_LABELS[day]];
    for (let hour = 0; hour < 24; hour++) {
      const cell = data.heatmap.find((c) => c.day === day && c.hour === hour);
      row.push(cell?.avgDurationMs ?? 0);
    }
    lines.push(toCsvRow(row));
  }
  lines.push("");

  // Per-site breakdown
  lines.push("=== PER-SITE BREAKDOWN ===");
  lines.push(toCsvRow(["Site", "Total", "Success", "Failed", "Partial", "Success Rate (%)"]));
  for (const s of data.sites) {
    lines.push(toCsvRow([s.siteName, s.total, s.success, s.failed, s.partial, s.successRate]));
  }

  downloadFile(lines.join("\n"), "publish-analytics.csv", "text/csv");
}

// ── PDF-like HTML Report ───────────────────────────────
// Generates a print-ready HTML document that opens in a new tab for Print → PDF.

export function exportAnalyticsPdf(data: PublishAnalyticsData): void {
  const successRate = data.summary.total > 0
    ? Math.round((data.summary.success / data.summary.total) * 100)
    : 0;

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Publish Analytics Report</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 40px; color: #1a1a2e; background: #fff; font-size: 13px; line-height: 1.5; }
    h1 { font-size: 22px; margin-bottom: 4px; }
    .subtitle { color: #6b7280; margin-bottom: 24px; font-size: 13px; }
    .summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 28px; }
    .summary-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
    .summary-card .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
    .summary-card .value { font-size: 22px; font-weight: 700; margin-top: 2px; }
    h2 { font-size: 15px; margin: 24px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
    th, td { padding: 6px 10px; border: 1px solid #e5e7eb; text-align: left; }
    th { background: #f9fafb; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }
    tr:nth-child(even) { background: #fafafa; }
    .heatmap-grid { display: grid; grid-template-columns: 50px repeat(24, 1fr); gap: 2px; font-size: 10px; }
    .heatmap-label { display: flex; align-items: center; font-weight: 500; font-size: 11px; }
    .heatmap-cell { aspect-ratio: 1; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #fff; }
    .text-right { text-align: right; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .badge-green { background: #dcfce7; color: #166534; }
    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-yellow { background: #fef9c3; color: #854d0e; }
    @media print {
      body { padding: 20px; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>
  <h1>Publish Analytics Report</h1>
  <p class="subtitle">30-day overview · Generated ${new Date().toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" })}</p>

  <div class="summary-grid">
    <div class="summary-card"><div class="label">Total Publishes</div><div class="value">${data.summary.total}</div></div>
    <div class="summary-card"><div class="label">Success Rate</div><div class="value">${successRate}%</div></div>
    <div class="summary-card"><div class="label">Successes</div><div class="value">${data.summary.success}</div></div>
    <div class="summary-card"><div class="label">Failures</div><div class="value">${data.summary.failed}</div></div>
    <div class="summary-card"><div class="label">Avg Duration</div><div class="value">${durationLabel(data.summary.avgDurationMs)}</div></div>
  </div>

  <h2>Daily Publishes</h2>
  <table>
    <thead><tr><th>Date</th><th class="text-right">Total</th><th class="text-right">Success</th><th class="text-right">Failed</th><th class="text-right">Partial</th></tr></thead>
    <tbody>
      ${data.daily.filter((d) => d.total > 0).map((d) => `
        <tr><td>${d.date}</td><td class="text-right">${d.total}</td><td class="text-right">${d.success}</td><td class="text-right">${d.failed}</td><td class="text-right">${d.partial}</td></tr>
      `).join("")}
    </tbody>
  </table>

  <h2>Per-Site Breakdown</h2>
  <table>
    <thead><tr><th>Site</th><th class="text-right">Total</th><th class="text-right">Success</th><th class="text-right">Failed</th><th class="text-right">Partial</th><th class="text-right">Rate</th></tr></thead>
    <tbody>
      ${data.sites.map((s) => `
        <tr>
          <td>${s.siteName}</td>
          <td class="text-right">${s.total}</td>
          <td class="text-right">${s.success}</td>
          <td class="text-right">${s.failed}</td>
          <td class="text-right">${s.partial}</td>
          <td class="text-right"><span class="badge ${s.successRate >= 90 ? "badge-green" : s.successRate >= 70 ? "badge-yellow" : "badge-red"}">${s.successRate}%</span></td>
        </tr>
      `).join("")}
    </tbody>
  </table>

  <h2>Duration Heatmap (Avg ms by Day × Hour)</h2>
  <div class="heatmap-grid">
    <div class="heatmap-label"></div>
    ${Array.from({ length: 24 }, (_, h) => `<div style="text-align:center;font-size:9px;color:#6b7280">${h}</div>`).join("")}
    ${DAY_LABELS.map((label, day) => {
      const cells = Array.from({ length: 24 }, (_, hour) => {
        const cell = data.heatmap.find((c) => c.day === day && c.hour === hour);
        const ms = cell?.avgDurationMs ?? 0;
        const maxMs = Math.max(...data.heatmap.map((c) => c.avgDurationMs));
        const ratio = maxMs > 0 ? ms / maxMs : 0;
        const bg = ms === 0 ? "#f3f4f6" : `hsl(${Math.round(120 - ratio * 120)}, 70%, 45%)`;
        return `<div class="heatmap-cell" style="background:${bg}" title="${ms}ms">${ms > 0 ? Math.round(ms / 1000) : ""}</div>`;
      }).join("");
      return `<div class="heatmap-label">${label}</div>${cells}`;
    }).join("")}
  </div>

  <p class="no-print" style="margin-top: 30px; color: #6b7280; font-size: 12px;">
    💡 Use <strong>Ctrl+P</strong> (or ⌘P) to save as PDF.
  </p>
</body>
</html>`;

  const blob = new Blob([html], { type: "text/html" });
  const url = URL.createObjectURL(blob);
  const win = window.open(url, "_blank");
  if (win) {
    win.addEventListener("load", () => {
      URL.revokeObjectURL(url);
    });
  }
}

// ── Helpers ────────────────────────────────────────────

function downloadFile(content: string, filename: string, mimeType: string): void {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
