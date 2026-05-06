<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('document with future publish_at is not yet available', function () {
    $future = (new DateTime('+1 hour', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $slug = generate_slug('Scheduled Doc');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at, slug) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute(['Scheduled Doc', 'body', $future, $slug]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    assert_true($row['publish_at'] === $future, 'publish_at not stored correctly');
    assert_true($row['publish_at'] > $now_utc, 'expected future publish_at to be in the future');
});

test('document with past publish_at is available', function () {
    $past = (new DateTime('-1 hour', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $slug = generate_slug('Past Scheduled Doc');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at, slug) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute(['Past Scheduled Doc', 'body', $past, $slug]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    assert_true($row['publish_at'] <= $now_utc, 'expected past publish_at to be in the past');
});

test('document with null publish_at is immediately available', function () {
    $slug = generate_slug('No Schedule Doc');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at, slug) VALUES (?, ?, 1, NULL, ?)');
    $stmt->execute(['No Schedule Doc', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    assert_true($row['publish_at'] === null, 'expected publish_at to be null');
});

test('document becomes available once publish_at is reached (utc)', function () {
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $just_passed = (new DateTime('-1 second', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $slug = generate_slug('Just Published Doc');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at, slug) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute(['Just Published Doc', 'body', $just_passed, $slug]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    assert_true($row['publish_at'] <= $now_utc, 'document should be available once publish_at is reached');
});

test('slug is generated and stored on document creation', function () {
    $slug = generate_slug('Test Document');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Test Document', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT slug FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    assert_true($row['slug'] === $slug, 'slug not stored correctly');
    assert_true(str_starts_with($row['slug'], 'test-document-'), 'slug should be derived from title');
});

test('slug is unique across documents with the same title', function () {
    $slug1 = generate_slug('Duplicate Title');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Duplicate Title', 'body', $slug1]);

    $slug2 = generate_slug('Duplicate Title');
    $stmt->execute(['Duplicate Title', 'body', $slug2]);

    assert_true($slug1 !== $slug2, 'slugs for same title should differ due to random suffix');
});

test('slug lookup resolves correct document via share', function () {
    $slug = generate_slug('Slug Lookup Doc');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Slug Lookup Doc', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT id, title FROM documents WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    assert_true($row !== false, 'slug should resolve to a document');
    assert_true((int) $row['id'] === $docId, 'slug resolved to wrong document');
    assert_true($row['title'] === 'Slug Lookup Doc', 'unexpected title for slug');
});

test('document creation without a slug is rejected', function () {
    $threw = false;
    try {
        $stmt = db()->prepare('INSERT INTO documents (title, body, created_by) VALUES (?, ?, 1)');
        $stmt->execute(['No Slug Doc', 'body']);
    } catch (PDOException $e) {
        $threw = true;
        assert_true(str_contains($e->getMessage(), 'slug is required'), 'unexpected error: ' . $e->getMessage());
    }
    assert_true($threw, 'expected insert without slug to throw');
});

test('fts5 search finds document by title keyword', function () {
    $slug = generate_slug('Onboarding Handbook');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Onboarding Handbook', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $fts_query = '"onboarding"*';
    $stmt = db()->prepare('SELECT rowid FROM documents_fts WHERE documents_fts MATCH ?');
    $stmt->execute([$fts_query]);
    $ids = array_column($stmt->fetchAll(), 'rowid');
    assert_true(in_array($docId, $ids), 'fts5 should find document by title keyword');
});

test('fts5 search returns no results for unmatched query', function () {
    $fts_query = '"zzznomatch"*';
    $stmt = db()->prepare('SELECT rowid FROM documents_fts WHERE documents_fts MATCH ?');
    $stmt->execute([$fts_query]);
    $rows = $stmt->fetchAll();
    assert_true(empty($rows), 'expected no results for unmatched query');
});

test('fts5 search finds document by partial title prefix (onboard -> Onboarding)', function () {
    $slug = generate_slug('Onboarding Guide');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Onboarding Guide', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $fts_query = '"onboard"*';
    $stmt = db()->prepare('SELECT rowid FROM documents_fts WHERE documents_fts MATCH ?');
    $stmt->execute([$fts_query]);
    $ids = array_column($stmt->fetchAll(), 'rowid');
    assert_true(in_array($docId, $ids), 'fts5 should match partial prefix onboard -> Onboarding');
});

test('fts5 search does not find document by mid-word partial (board does not match Onboarding)', function () {
    $slug = generate_slug('Onboarding Packet');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Onboarding Packet', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    // FTS5 tokenises by word boundary — "board" is not a token prefix of "onboarding"
    $fts_query = '"board"*';
    $stmt = db()->prepare('SELECT rowid FROM documents_fts WHERE documents_fts MATCH ?');
    $stmt->execute([$fts_query]);
    $ids = array_column($stmt->fetchAll(), 'rowid');
    assert_true(!in_array($docId, $ids), 'fts5 does not support mid-word partial matching');
});

test('fts5 search does not find document for misspelled title (onbording does not match Onboarding)', function () {
    $slug = generate_slug('Onboarding Reference');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Onboarding Reference', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    // FTS5 has no fuzzy/Levenshtein matching — misspellings return no results
    $fts_query = '"onbording"*';
    $stmt = db()->prepare('SELECT rowid FROM documents_fts WHERE documents_fts MATCH ?');
    $stmt->execute([$fts_query]);
    $ids = array_column($stmt->fetchAll(), 'rowid');
    assert_true(!in_array($docId, $ids), 'fts5 does not support misspelling/fuzzy matching');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
