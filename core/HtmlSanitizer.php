<?php

namespace Core;

/**
 * Whitelist-based HTML Sanitizer for User Generated Content
 */
class HtmlSanitizer
{
    /**
     * Whitelist of allowed tags and their allowed attributes.
     *
     * @var array
     */
    protected array $allowedTags = [
        'p' => [],
        'b' => [],
        'i' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'strike' => [],
        'span' => ['style', 'class'],
        'a' => ['href', 'title', 'target'],
        'pre' => ['class'],
        'code' => ['class'],
        'blockquote' => ['cite'],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'br' => [],
        'hr' => [],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    /**
     * Blacklist of tags that should be completely removed, including all their children.
     *
     * @var array
     */
    protected array $removeTags = [
        'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'canvas', 'svg', 'math'
    ];

    /**
     * Sanitize input HTML string against the whitelist.
     *
     * @param string $html
     * @return string
     */
    public function sanitize(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }

        // Disable standard libxml errors to prevent warning leak
        $libxmlState = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        
        // Convert UTF-8 characters to HTML numeric entities to safely preserve multi-byte characters
        $convmap = [0x80, 0x10ffff, 0, 0x1ffff];
        $encodedHtml = mb_encode_numericentity($html, $convmap, 'UTF-8');
        
        // Wrap in a simple <div> and load HTML
        $wrappedHtml = '<div>' . $encodedHtml . '</div>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $cleanHtml = '';
        $root = $dom->documentElement; // The wrapping <div>
        
        if ($root) {
            // Sanitize only the children inside the root wrapper <div>, 
            // preventing the root wrapper itself from being unwrapped.
            $children = [];
            foreach ($root->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                $this->sanitizeNode($child);
            }
            
            // Collect HTML from children inside the wrapper
            foreach ($root->childNodes as $child) {
                $cleanHtml .= $dom->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        return trim($cleanHtml);
    }

    /**
     * Recursively traverse and sanitize DOM nodes.
     *
     * @param \DOMNode $node
     */
    protected function sanitizeNode(\DOMNode $node): void
    {
        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                $this->sanitizeNode($child);
            }
        }

        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($node->nodeName);

            // 1. If tag is in the remove list, delete it and its children
            if (in_array($tagName, $this->removeTags, true)) {
                $node->parentNode->removeChild($node);
                return;
            }

            // 2. If tag is not allowed, unwrap it (move children out and remove tag)
            if (!isset($this->allowedTags[$tagName])) {
                while ($node->hasChildNodes()) {
                    $node->parentNode->insertBefore($node->firstChild, $node);
                }
                $node->parentNode->removeChild($node);
                return;
            }

            // 3. Filter attributes
            if ($node->hasAttributes()) {
                $attrs = [];
                foreach ($node->attributes as $attr) {
                    $attrs[] = $attr;
                }
                foreach ($attrs as $attr) {
                    $attrName = strtolower($attr->name);
                    
                    // Check if attribute is allowed on this tag
                    if (!in_array($attrName, $this->allowedTags[$tagName], true)) {
                        $node->removeAttribute($attr->name);
                        continue;
                    }

                    // Check for JavaScript protocols in URIs (href/src)
                    if (($attrName === 'href' || $attrName === 'src') && preg_match('/^\s*javascript:/i', $attr->value)) {
                        $node->removeAttribute($attr->name);
                    }
                }
            }
        }
    }
}
