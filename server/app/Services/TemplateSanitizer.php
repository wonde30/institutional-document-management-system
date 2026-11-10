<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class TemplateSanitizer
{
    /**
     * Whitelist of permitted HTML tags.
     */
    protected const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'table', 'tr', 'td', 'th',
        'tbody', 'thead', 'tfoot', 'ul', 'ol', 'li', 'span', 'div',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'blockquote', 'code', 'pre',
    ];

    /**
     * Whitelist of permitted attributes per tag or globally.
     */
    protected const ALLOWED_ATTRIBUTES = [
        'class', 'style', 'colspan', 'rowspan', 'align', 'valign', 'dir',
    ];

    /**
     * Sanitize HTML content according to strict institutional rules.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Prevent libxml warnings from bubbling
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        // Wrap in HTML entity declaration for proper UTF-8 handling
        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body) {
            $this->cleanNode($body);

            // Extract inner HTML of body
            $cleaned = '';
            foreach ($body->childNodes as $child) {
                $cleaned .= $doc->saveHTML($child);
            }
        } else {
            $cleaned = strip_tags($html, '<p><br><strong><em><u><table><tr><td><th><ul><ol><li><div><span>');
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return trim($cleaned);
    }

    /**
     * Recursively clean DOM elements against whitelist.
     */
    protected function cleanNode(DOMNode $node): void
    {
        // Work on a copy of childNodes because removing/replacing mutates the list
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);

                // Strip disallowed tags: either remove completely or unwrap text
                if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                    if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg'], true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    // For other unknown tags, unwrap their children into the parent
                    while ($child->hasChildNodes()) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                // Filter attributes
                if ($child->hasAttributes()) {
                    $attributesToRemove = [];
                    foreach ($child->attributes as $attr) {
                        $attrName = strtolower($attr->name);

                        // Strip any event handler (starts with 'on') or non-whitelisted attribute
                        if (str_starts_with($attrName, 'on') || ! in_array($attrName, self::ALLOWED_ATTRIBUTES, true)) {
                            $attributesToRemove[] = $attr->name;
                            continue;
                        }

                        // Inspect style attribute for dangerous expressions or URLs
                        if ($attrName === 'style') {
                            $val = strtolower($attr->value);
                            if (str_contains($val, 'javascript:') || str_contains($val, 'behavior:') || str_contains($val, 'expression(') || str_contains($val, 'url(')) {
                                $attributesToRemove[] = $attr->name;
                            }
                        }
                    }

                    foreach ($attributesToRemove as $name) {
                        $child->removeAttribute($name);
                    }
                }

                // Recurse into valid children
                $this->cleanNode($child);
            }
        }
    }
}
