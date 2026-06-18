<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

final class SafeMarkdown
{
    public static function render(?string $text): HtmlString
    {
        $text = trim((string) $text);

        if ($text === '') {
            return new HtmlString('');
        }

        $lines = preg_split('/\R/', e($text)) ?: [];
        $html = [];
        $paragraph = [];
        $list = [];

        $flushParagraph = static function () use (&$html, &$paragraph): void {
            if ($paragraph === []) {
                return;
            }

            $html[] = '<p>'.implode('<br>', $paragraph).'</p>';
            $paragraph = [];
        };

        $flushList = static function () use (&$html, &$list): void {
            if ($list === []) {
                return;
            }

            $html[] = '<ul><li>'.implode('</li><li>', $list).'</li></ul>';
            $list = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                $flushList();
                $level = strlen($matches[1]) + 1;
                $html[] = sprintf('<h%d>%s</h%d>', $level, self::inline($matches[2]), $level);
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                $list[] = self::inline($matches[1]);
                continue;
            }

            $flushList();
            $paragraph[] = self::inline($line);
        }

        $flushParagraph();
        $flushList();

        return new HtmlString('<div class="markdown-content">'.implode("\n", $html).'</div>');
    }

    private static function inline(string $html): string
    {
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $html) ?? $html;

        return preg_replace_callback('/https?:\/\/[^\s<]+/i', static function (array $matches): string {
            $url = rtrim($matches[0], '.,)');
            $suffix = substr($matches[0], strlen($url));

            return sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>%s',
                $url,
                $url,
                $suffix
            );
        }, $html) ?? $html;
    }
}
