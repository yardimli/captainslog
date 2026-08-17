<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BladeDivSemanticsTest extends TestCase
{
    public function test_every_blade_div_has_a_descriptive_id_or_semantic_first_class(): void
    {
        $viewsPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
        $violations = [];

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            preg_match_all('/<div\b[^>]*>/s', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                if (preg_match('/\bid\s*=\s*(["\']).+?\1/s', $tag)) {
                    continue;
                }

                preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/s', $tag, $classMatch);
                $firstClass = preg_split('/\s+/', trim($classMatch[2] ?? ''))[0] ?? '';

                if (! preg_match('/^(api-|block-|calendar-|demo-|event-|guest-|manual-|modal-|navigation-|time-|timeline-|toast-)/', $firstClass)) {
                    $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                    $relativePath = str_replace($viewsPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $violations[] = "{$relativePath}:{$line} uses '{$firstClass}'";
                }
            }
        }

        $this->assertSame([], $violations, "Anonymous or utility-first divs found:\n".implode("\n", $violations));
    }

    public function test_static_div_ids_are_unique_within_each_blade_file(): void
    {
        $viewsPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
        $duplicates = [];

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            preg_match_all('/<div\b[^>]*\bid\s*=\s*(["\'])([^"\'{]+)\1[^>]*>/s', $source, $matches);
            $counts = array_count_values($matches[2]);

            foreach ($counts as $id => $count) {
                if ($count > 1) {
                    $relativePath = str_replace($viewsPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $duplicates[] = "{$relativePath} repeats #{$id} {$count} times";
                }
            }
        }

        $this->assertSame([], $duplicates, "Duplicate static div IDs found:\n".implode("\n", $duplicates));
    }
}
