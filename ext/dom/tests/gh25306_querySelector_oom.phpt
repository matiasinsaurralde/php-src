--TEST--
GH-25306 follow-up: querySelector() must not use an uninitialized selectors struct when lxb_css_parser_init() OOMs
--EXTENSIONS--
dom
--SKIPIF--
<?php
if (getenv("USE_ZEND_ALLOC") === "0") die("skip Zend MM disabled");
if (PHP_INT_SIZE != 8) die("skip 64-bit only (page-block tuning)");
?>
--INI--
memory_limit=2M
--FILE--
<?php
// Create the document while memory is plentiful.
$doc = Dom\HTMLDocument::createFromString("<!DOCTYPE html><html><body><p>x</p></body></html>");

// Consume memory so that the FIRST allocation inside lxb_css_parser_init()
// (parser->states_begin = 24 * 1024 = 24576 bytes = 6 pages), reached from
// querySelector(), returns NULL via PHP's fallible lexbor allocator. On the
// regressed build dom_parse_selector() then returns NULL before memset()ing the
// stack `selectors`, and dom_selector_cleanup() destroys that uninitialized
// struct -> wild dereference/free. The fix memset()s selectors first, so this
// must end in a clean out-of-memory fatal error, never a crash.
//
// NOTE: like the sibling OOM tests (e.g. gh25305.phpt), the exact padding is
// allocation-timing sensitive; it is tuned for a 2M limit on a 4K-page build.
$pad = [];
for ($i = 0; $i < 46; $i++) {
    $pad[] = str_repeat("a", 24551); // 24 (zend_string hdr) + 24551 + 1 = 24576 = 6 pages
}

$doc->querySelector('p');
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted%Sin %s on line %d%A
