<?php

require __DIR__ . '/../vendor/autoload.php';

$reflection = new ReflectionClass(Utils\BookmarkImporter::class);
$importer = $reflection->newInstanceWithoutConstructor();
$extract = $reflection->getMethod('extractData');
$html = '<a href="https://example.org/ok">ok</a>'
    . '<a href="JaVaScRiPt:alert(1)">bad</a>'
    . '<a href="data:text/html,evil">bad</a>'
    . '<a href="file:///etc/passwd">bad</a>';
[, , $bookmarks, $skipped] = $extract->invoke($importer, $html);
if (count($bookmarks) !== 1 || $bookmarks[0]['url'] !== 'https://example.org/ok' || $skipped !== 3) {
    throw new RuntimeException('Unsafe bookmark URL was accepted');
}
echo "Bookmark URL import security: PASS\n";
