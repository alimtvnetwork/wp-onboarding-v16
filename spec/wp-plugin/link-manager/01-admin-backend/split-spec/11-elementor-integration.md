# 11 - Elementor Integration

> **Status:** Complete  
> **Priority:** Critical  
> **Updated:** 2026-01-31

---

## Purpose

Handles reading and writing link modifications to Elementor's data structure. Elementor stores content differently than standard WordPress content, requiring specialized handling to preserve widget integrity.

---

## Elementor Data Structure

Elementor stores page/post content in multiple locations:

```
1. Post Meta: `_elementor_data` (JSON string)
2. Post Meta: `_elementor_edit_mode` ('builder' if Elementor-built)
3. Post Content: `post_content` (rendered HTML for frontend caching)
```

### Sample `_elementor_data` Structure

```json
[
  {
    "id": "abc12345",
    "elType": "section",
    "settings": {},
    "elements": [
      {
        "id": "def67890",
        "elType": "column",
        "elements": [
          {
            "id": "ghi11111",
            "elType": "widget",
            "widgetType": "text-editor",
            "settings": {
              "editor": "<p>Check out <a href=\"https://example.com\" title=\"Example\">this link</a></p>"
            }
          },
          {
            "id": "jkl22222",
            "elType": "widget",
            "widgetType": "heading",
            "settings": {
              "title": "Welcome",
              "link": {
                "url": "https://example.com",
                "is_external": true,
                "nofollow": false
              }
            }
          }
        ]
      }
    ]
  }
]
```

---

## Core Interfaces

```php
<?php
declare(strict_types=1);

namespace LinkManager\Elementor;

/**
 * Represents a link found in Elementor data
 */
interface ElementorLinkInterface
{
    public function getElementId(): string;
    public function getWidgetType(): string;
    public function getSettingPath(): string; // e.g., 'settings.editor' or 'settings.link.url'
    public function getUrl(): string;
    public function getAnchorText(): ?string; // null for non-text links (buttons, images)
    public function getTitleAttribute(): ?string;
    public function isInHtmlContent(): bool; // true if in 'editor' field (rich text)
    public function isDirectLink(): bool; // true if in 'link' field (button/heading links)
}

/**
 * Result of Elementor content analysis
 */
interface ElementorAnalysisResultInterface
{
    public function isElementorContent(): bool;
    public function getLinks(): array; // ElementorLinkInterface[]
    public function getWidgetCount(): int;
    public function getRawData(): array;
}

/**
 * Main Elementor integration service
 */
interface ElementorServiceInterface
{
    /**
     * Check if post uses Elementor
     */
    public function isElementorPost(int $postId): bool;
    
    /**
     * Analyze Elementor content for links
     */
    public function analyzeContent(int $postId): ElementorAnalysisResultInterface;
    
    /**
     * Modify a link in Elementor data
     */
    public function modifyLink(
        int $postId,
        string $elementId,
        string $settingPath,
        array $modifications
    ): bool;
    
    /**
     * Remove a link (keeping text if applicable)
     */
    public function removeLink(
        int $postId,
        string $elementId,
        string $settingPath,
        bool $keepText = true
    ): bool;
    
    /**
     * Get raw Elementor data for backup
     */
    public function getRawData(int $postId): ?string;
    
    /**
     * Restore Elementor data from backup
     */
    public function restoreData(int $postId, string $jsonData): bool;
}
```

---

## Widget Type Handlers

Different Elementor widgets store links differently:

```php
<?php
declare(strict_types=1);

namespace LinkManager\Elementor\Handlers;

/**
 * Base handler interface
 */
interface WidgetHandlerInterface
{
    public function getWidgetType(): string;
    public function extractLinks(array $settings): array;
    public function modifyLink(array $settings, string $path, array $modifications): array;
    public function removeLink(array $settings, string $path, bool $keepText): array;
}

/**
 * Text Editor widget (rich HTML content)
 */
final class TextEditorHandler implements WidgetHandlerInterface
{
    public function getWidgetType(): string
    {
        return 'text-editor';
    }
    
    public function extractLinks(array $settings): array
    {
        $links = [];
        $html = $settings['editor'] ?? '';
        
        // Parse HTML for anchor tags
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED);
        
        $anchors = $dom->getElementsByTagName('a');
        foreach ($anchors as $index => $anchor) {
            $links[] = [
                'path' => "settings.editor.anchor[{$index}]",
                'url' => $anchor->getAttribute('href'),
                'text' => $anchor->textContent,
                'title' => $anchor->getAttribute('title') ?: null,
                'isHtml' => true
            ];
        }
        
        return $links;
    }
    
    // ... modification methods
}

/**
 * Heading widget (direct link property)
 */
final class HeadingHandler implements WidgetHandlerInterface
{
    public function getWidgetType(): string
    {
        return 'heading';
    }
    
    public function extractLinks(array $settings): array
    {
        $link = $settings['link'] ?? null;
        if (!$link || empty($link['url'])) {
            return [];
        }
        
        return [[
            'path' => 'settings.link',
            'url' => $link['url'],
            'text' => $settings['title'] ?? null,
            'isExternal' => $link['is_external'] ?? false,
            'nofollow' => $link['nofollow'] ?? false,
            'isHtml' => false
        ]];
    }
}

/**
 * Button widget
 */
final class ButtonHandler implements WidgetHandlerInterface
{
    public function getWidgetType(): string
    {
        return 'button';
    }
    
    public function extractLinks(array $settings): array
    {
        $link = $settings['link'] ?? null;
        if (!$link || empty($link['url'])) {
            return [];
        }
        
        return [[
            'path' => 'settings.link',
            'url' => $link['url'],
            'text' => $settings['text'] ?? 'Button',
            'isExternal' => $link['is_external'] ?? false,
            'isHtml' => false
        ]];
    }
}

/**
 * Image widget
 */
final class ImageHandler implements WidgetHandlerInterface
{
    public function getWidgetType(): string
    {
        return 'image';
    }
    
    public function extractLinks(array $settings): array
    {
        $links = [];
        
        // Image link
        $link = $settings['link'] ?? null;
        if ($link && !empty($link['url'])) {
            $links[] = [
                'path' => 'settings.link',
                'url' => $link['url'],
                'isHtml' => false
            ];
        }
        
        // Image source URL
        $image = $settings['image'] ?? null;
        if ($image && !empty($image['url'])) {
            $links[] = [
                'path' => 'settings.image.url',
                'url' => $image['url'],
                'isImageSource' => true,
                'isHtml' => false
            ];
        }
        
        return $links;
    }
}
```

---

## Elementor Data Traversal

```php
<?php
declare(strict_types=1);

namespace LinkManager\Elementor;

final class ElementorDataTraverser
{
    /** @var WidgetHandlerInterface[] */
    private array $handlers;
    
    public function __construct(array $handlers)
    {
        $this->handlers = [];
        foreach ($handlers as $handler) {
            $this->handlers[$handler->getWidgetType()] = $handler;
        }
    }
    
    /**
     * Traverse Elementor data and extract all links
     */
    public function extractAllLinks(array $data): array
    {
        $links = [];
        $this->traverseElements($data, $links);
        return $links;
    }
    
    private function traverseElements(array $elements, array &$links): void
    {
        foreach ($elements as $element) {
            // Process widget elements
            if (($element['elType'] ?? '') === 'widget') {
                $widgetType = $element['widgetType'] ?? '';
                $elementId = $element['id'] ?? '';
                
                if (isset($this->handlers[$widgetType])) {
                    $widgetLinks = $this->handlers[$widgetType]
                        ->extractLinks($element['settings'] ?? []);
                    
                    foreach ($widgetLinks as $link) {
                        $link['elementId'] = $elementId;
                        $link['widgetType'] = $widgetType;
                        $links[] = $link;
                    }
                } else {
                    // Generic handler for unknown widgets
                    $this->extractGenericLinks($element, $links);
                }
            }
            
            // Recurse into nested elements
            if (!empty($element['elements'])) {
                $this->traverseElements($element['elements'], $links);
            }
        }
    }
    
    private function extractGenericLinks(array $element, array &$links): void
    {
        // Search settings for any 'link' or 'url' properties
        $settings = $element['settings'] ?? [];
        $this->searchForUrls($settings, $element['id'] ?? '', '', $links);
    }
    
    private function searchForUrls(
        array $data, 
        string $elementId, 
        string $path, 
        array &$links
    ): void {
        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;
            
            if (is_array($value)) {
                // Check for Elementor link structure
                if (isset($value['url']) && is_string($value['url'])) {
                    $links[] = [
                        'elementId' => $elementId,
                        'path' => "settings.{$currentPath}",
                        'url' => $value['url'],
                        'isHtml' => false
                    ];
                } else {
                    $this->searchForUrls($value, $elementId, $currentPath, $links);
                }
            } elseif (is_string($value) && str_contains($value, '<a ')) {
                // HTML content with links
                $links[] = [
                    'elementId' => $elementId,
                    'path' => "settings.{$currentPath}",
                    'html' => $value,
                    'isHtml' => true
                ];
            }
        }
    }
}
```

---

## Modification Service

```php
<?php
declare(strict_types=1);

namespace LinkManager\Elementor;

final class ElementorModificationService
{
    public function __construct(
        private readonly ElementorDataTraverser $traverser,
        private readonly array $handlers
    ) {}
    
    /**
     * Modify link in Elementor data
     */
    public function modifyLink(
        array $data,
        string $elementId,
        string $settingPath,
        array $modifications
    ): array {
        return $this->traverseAndModify(
            $data,
            $elementId,
            fn($element) => $this->applyModification($element, $settingPath, $modifications)
        );
    }
    
    /**
     * Remove link from Elementor data
     */
    public function removeLink(
        array $data,
        string $elementId,
        string $settingPath,
        bool $keepText
    ): array {
        return $this->traverseAndModify(
            $data,
            $elementId,
            fn($element) => $this->applyRemoval($element, $settingPath, $keepText)
        );
    }
    
    private function traverseAndModify(
        array $data,
        string $targetId,
        callable $modifier
    ): array {
        foreach ($data as $key => $element) {
            if (($element['id'] ?? '') === $targetId) {
                $data[$key] = $modifier($element);
            }
            
            if (!empty($element['elements'])) {
                $data[$key]['elements'] = $this->traverseAndModify(
                    $element['elements'],
                    $targetId,
                    $modifier
                );
            }
        }
        
        return $data;
    }
    
    private function applyModification(
        array $element,
        string $path,
        array $modifications
    ): array {
        // Parse path and apply modifications
        // e.g., 'settings.link.url' or 'settings.editor.anchor[0]'
        
        $widgetType = $element['widgetType'] ?? '';
        if (isset($this->handlers[$widgetType])) {
            $element['settings'] = $this->handlers[$widgetType]
                ->modifyLink($element['settings'], $path, $modifications);
        }
        
        return $element;
    }
    
    private function applyRemoval(
        array $element,
        string $path,
        bool $keepText
    ): array {
        $widgetType = $element['widgetType'] ?? '';
        if (isset($this->handlers[$widgetType])) {
            $element['settings'] = $this->handlers[$widgetType]
                ->removeLink($element['settings'], $path, $keepText);
        }
        
        return $element;
    }
}
```

---

## Saving Modified Content

```php
<?php
declare(strict_types=1);

namespace LinkManager\Elementor;

final class ElementorSaveService
{
    /**
     * Save modified Elementor data back to post
     */
    public function save(int $postId, array $data): bool
    {
        // 1. Encode data as JSON
        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode Elementor data');
        }
        
        // 2. Update _elementor_data meta
        $result = update_post_meta($postId, '_elementor_data', $json);
        
        // 3. Regenerate rendered HTML for post_content
        $this->regenerateContent($postId);
        
        // 4. Clear Elementor cache
        $this->clearElementorCache($postId);
        
        return $result !== false;
    }
    
    private function regenerateContent(int $postId): void
    {
        // Trigger Elementor's content regeneration
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            
            // Force re-render
            $document = \Elementor\Plugin::$instance->documents->get($postId);
            if ($document) {
                $document->save([]);
            }
        }
    }
    
    private function clearElementorCache(int $postId): void
    {
        delete_post_meta($postId, '_elementor_css');
        delete_transient('elementor_css_' . $postId);
    }
}
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14300 | ERR_ELEM_NOT_ELEMENTOR | Post is not built with Elementor |
| 14301 | ERR_ELEM_DATA_CORRUPTED | _elementor_data is malformed |
| 14302 | ERR_ELEM_ELEMENT_NOT_FOUND | Element ID not found in data |
| 14303 | ERR_ELEM_SAVE_FAILED | Failed to save modified data |
| 14304 | ERR_ELEM_CACHE_CLEAR_FAILED | Cache clearing failed |
| 14305 | ERR_ELEM_WIDGET_UNSUPPORTED | Widget type has no handler |

---

## Acceptance Criteria

**Done when:**
- [ ] Correctly identifies Elementor vs standard posts
- [ ] Extracts links from all common widget types
- [ ] Modifications preserve Elementor data structure integrity
- [ ] Post content regenerates correctly after save
- [ ] Elementor cache clears after modifications
- [ ] Handles nested elements correctly
- [ ] Falls back gracefully for unknown widget types

---

## Dependencies

- `10-link-parser.md` - HTML parsing within widget content
- Elementor Plugin API
- WordPress post meta API
