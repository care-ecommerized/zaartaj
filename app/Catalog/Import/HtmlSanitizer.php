<?php

namespace App\Catalog\Import;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /**
     * Tags kept in the output. Everything else is unwrapped, not deleted, so that
     * the text inside an unknown wrapper survives.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 'span',
        'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /**
     * Tags removed together with their contents.
     *
     * @var list<string>
     */
    private const DROP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'noscript'];

    /**
     * Attributes kept, per tag. Everything else goes, which is what strips the
     * `data-start`, `data-message-author-role` and Tailwind `class` noise that the
     * descriptions in this export are full of.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
    ];

    /**
     * Clean a product description for storage.
     *
     * Several rows in the Shopify export contain entire ChatGPT conversation panes
     * — `<div class="..." data-message-author-role="assistant">` and friends. Storing
     * that raw and rendering it is both broken layout and an injection risk, so the
     * wrappers are unwrapped down to their prose and every attribute is dropped.
     */
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        // The XML prologue is what keeps DOMDocument from assuming ISO-8859-1 and
        // re-corrupting text that Encoding has just repaired.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Unparseable markup still has usable text in it.
            return $this->normalise(strip_tags($html));
        }

        $root = $document->getElementById('__root__');

        if (! $root instanceof DOMElement) {
            return $this->normalise(strip_tags($html));
        }

        $this->scrub($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $this->normalise($output);
    }

    /**
     * Depth-first pass over an element's children, dropping and unwrapping as it goes.
     */
    private function scrub(DOMElement $element): void
    {
        // Snapshot the children: the list is live, and we mutate it as we walk.
        $children = iterator_to_array($element->childNodes);

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $element->removeChild($child);

                continue;
            }

            // Recurse before restructuring, so descendants are clean either way.
            $this->scrub($child);

            if (in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->stripAttributes($child, $tag);

                continue;
            }

            $this->unwrap($child, $element);
        }
    }

    private function stripAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            // javascript: and data: URLs are the reason href is allowlisted rather
            // than trusted.
            if ($name === 'href' && ! $this->isSafeUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }

    /**
     * Replace a node with its own children, preserving order.
     */
    private function unwrap(DOMElement $element, DOMNode $parent): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Relative, root-relative, anchor and protocol-relative links are all fine.
        if (preg_match('#^(/|\#|\?|\./|\.\./)#', $url)) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:)#i', $url);
    }

    /**
     * Collapse the whitespace left behind by unwrapping, and drop the empty
     * paragraphs that padded the original markup.
     */
    private function normalise(string $html): string
    {
        $html = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html) ?? $html;
        $html = preg_replace('/\s+/u', ' ', $html) ?? $html;
        $html = preg_replace('#>\s+<#', '><', $html) ?? $html;

        return trim($html);
    }
}
