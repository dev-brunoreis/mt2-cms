<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img',
    ];

    /** @var list<string> */
    private array $allowedTags = self::ALLOWED_TAGS;

    public function sanitize(string $html, bool $allowImages = true): string
    {
        $this->allowedTags = $allowImages
            ? self::ALLOWED_TAGS
            : array_values(array_filter(self::ALLOWED_TAGS, static fn (string $tag): bool => $tag !== 'img'));

        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="cms-sanitize-root">' . $html . '</div>';

        $loaded = $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return '';
        }

        $root = $document->getElementById('cms-sanitize-root');

        if ($root === null) {
            return '';
        }

        $this->cleanNode($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Ticket messages: sanitize HTML, or escape + nl2br for plain text.
     */
    public function ticketHtml(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return '';
        }

        if (!preg_match('/<[a-z][\s\S]*>/i', $trimmed)) {
            return nl2br(htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        }

        return $this->sanitize($trimmed, false);
    }

    public function excerpt(string $html, int $maxLength = 160): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    private function cleanNode(\DOMNode $node): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        /** @var list<\DOMNode> $children */
        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select'], true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, $this->allowedTags, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);
                continue;
            }

            $this->cleanAttributes($child, $tag);

            if ($tag === 'a' && !$child->hasAttribute('href')) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);
                continue;
            }

            if ($tag === 'img' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
                continue;
            }

            $this->cleanNode($child);
        }
    }

    private function cleanAttributes(\DOMElement $element, string $tag): void
    {
        /** @var list<string> $names */
        $names = [];

        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $names[] = $attr->name;
            }
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            if (str_starts_with($lower, 'on') || $lower === 'style') {
                $element->removeAttribute($name);
                continue;
            }

            if ($tag === 'a' && $lower === 'href') {
                $href = trim($element->getAttribute('href'));

                if (!$this->isAllowedHref($href)) {
                    $element->removeAttribute($name);
                } else {
                    $element->setAttribute('href', $href);
                    $element->setAttribute('rel', 'noopener noreferrer');
                }

                continue;
            }

            if ($tag === 'img' && $lower === 'src') {
                $src = trim($element->getAttribute('src'));

                if (!$this->isAllowedImageSrc($src)) {
                    $element->removeAttribute($name);
                } else {
                    $element->setAttribute('src', $src);
                }

                continue;
            }

            if ($tag === 'img' && in_array($lower, ['alt', 'title', 'width', 'height'], true)) {
                continue;
            }

            if ($tag === 'a' && in_array($lower, ['title', 'target', 'rel'], true)) {
                if ($lower === 'target') {
                    $target = strtolower(trim($element->getAttribute('target')));

                    if ($target !== '_blank') {
                        $element->removeAttribute($name);
                    }
                }

                continue;
            }

            $element->removeAttribute($name);
        }
    }

    private function isAllowedHref(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return $href !== '';
        }

        if (str_starts_with($href, '/')) {
            return !str_starts_with($href, '//');
        }

        $lower = strtolower($href);

        return str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'https://')
            || str_starts_with($lower, 'mailto:');
    }

    private function isAllowedImageSrc(string $src): bool
    {
        if (!str_starts_with($src, '/uploads/news/')) {
            return false;
        }

        if (str_contains($src, '..') || str_contains($src, '\\')) {
            return false;
        }

        return (bool) preg_match('#^/uploads/news/[0-9]{4}/[0-9]{2}/[a-zA-Z0-9._-]+$#', $src);
    }
}
