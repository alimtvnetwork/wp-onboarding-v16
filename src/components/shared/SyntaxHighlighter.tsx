import { useEffect, useRef, useMemo } from "react";
import hljs from "highlight.js/lib/core";

// Register languages for WordPress plugin development
import php from "highlight.js/lib/languages/php";
import javascript from "highlight.js/lib/languages/javascript";
import typescript from "highlight.js/lib/languages/typescript";
import css from "highlight.js/lib/languages/css";
import scss from "highlight.js/lib/languages/scss";
import json from "highlight.js/lib/languages/json";
import xml from "highlight.js/lib/languages/xml";
import markdown from "highlight.js/lib/languages/markdown";
import yaml from "highlight.js/lib/languages/yaml";
import sql from "highlight.js/lib/languages/sql";
import bash from "highlight.js/lib/languages/bash";

// Register all languages
hljs.registerLanguage("php", php);
hljs.registerLanguage("javascript", javascript);
hljs.registerLanguage("js", javascript);
hljs.registerLanguage("typescript", typescript);
hljs.registerLanguage("ts", typescript);
hljs.registerLanguage("css", css);
hljs.registerLanguage("scss", scss);
hljs.registerLanguage("json", json);
hljs.registerLanguage("xml", xml);
hljs.registerLanguage("html", xml);
hljs.registerLanguage("markdown", markdown);
hljs.registerLanguage("md", markdown);
hljs.registerLanguage("yaml", yaml);
hljs.registerLanguage("yml", yaml);
hljs.registerLanguage("sql", sql);
hljs.registerLanguage("bash", bash);
hljs.registerLanguage("sh", bash);

// Map file extensions to highlight.js language names
const extensionToLanguage: Record<string, string> = {
  php: "php",
  js: "javascript",
  jsx: "javascript",
  ts: "typescript",
  tsx: "typescript",
  css: "css",
  scss: "scss",
  less: "css",
  json: "json",
  xml: "xml",
  html: "html",
  htm: "html",
  md: "markdown",
  markdown: "markdown",
  yaml: "yaml",
  yml: "yaml",
  sql: "sql",
  sh: "bash",
  bash: "bash",
  txt: "plaintext",
  log: "plaintext",
};

interface SyntaxHighlighterProps {
  code: string;
  fileName: string;
  showLineNumbers?: boolean;
  className?: string;
}

export function SyntaxHighlighter({
  code,
  fileName,
  showLineNumbers = true,
  className = "",
}: SyntaxHighlighterProps) {
  const codeRef = useRef<HTMLElement>(null);

  // Get language from file extension
  const language = useMemo(() => {
    const ext = fileName.split(".").pop()?.toLowerCase() || "";
    return extensionToLanguage[ext] || "plaintext";
  }, [fileName]);

  // Highlighted HTML
  const highlightedCode = useMemo(() => {
    if (language === "plaintext") {
      return escapeHtml(code);
    }
    try {
      const result = hljs.highlight(code, { language, ignoreIllegals: true });
      return result.value;
    } catch {
      return escapeHtml(code);
    }
  }, [code, language]);

  // Split into lines for line numbers
  const lines = useMemo(() => {
    return highlightedCode.split("\n");
  }, [highlightedCode]);

  return (
    <div className={`syntax-highlighter ${className}`}>
      <pre className="text-xs font-mono leading-relaxed overflow-x-auto">
        <code ref={codeRef} className={`hljs language-${language}`}>
          {showLineNumbers ? (
            <table className="w-full border-collapse">
              <tbody>
                {lines.map((line, index) => (
                  <tr key={index} className="hover:bg-accent/20">
                    <td className="select-none text-right pr-4 text-muted-foreground/50 border-r border-border/30 w-12 align-top">
                      {index + 1}
                    </td>
                    <td 
                      className="pl-4 whitespace-pre-wrap break-all"
                      dangerouslySetInnerHTML={{ __html: line || "&nbsp;" }}
                    />
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div dangerouslySetInnerHTML={{ __html: highlightedCode }} />
          )}
        </code>
      </pre>
    </div>
  );
}

// Helper to escape HTML for plaintext
function escapeHtml(text: string): string {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

export default SyntaxHighlighter;
