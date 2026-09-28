<?php
// Prints the <body> signature: tag#id.classes + visible text, one per line.
// Ignores whitespace, <script>, <style>. Usage: php dom-sig.php file.html
declare(strict_types=1);
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="UTF-8">' . file_get_contents($argv[1]));
$walk = function (DOMNode $node, int $depth) use (&$walk): void {
    foreach ($node->childNodes as $c) {
        $pad = str_repeat('  ', $depth);
        if ($c instanceof DOMElement) {
            if (in_array($c->tagName, ['script', 'style'], true)) continue;
            $id = $c->getAttribute('id');
            $cls = preg_replace('/\s+/', '.', trim($c->getAttribute('class')));
            echo $pad, $c->tagName, $id !== '' ? "#$id" : '', $cls !== '' ? ".$cls" : '', "\n";
            $walk($c, $depth + 1);
        } elseif ($c instanceof DOMText) {
            $t = trim(preg_replace('/\s+/u', ' ', $c->textContent));
            if ($t !== '') echo $pad, '"', $t, "\"\n";
        }
    }
};
$walk($doc->getElementsByTagName('body')->item(0), 0);
