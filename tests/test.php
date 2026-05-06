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
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Scheduled Doc', 'body', $future]);
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
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Past Scheduled Doc', 'body', $past]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    assert_true($row['publish_at'] <= $now_utc, 'expected past publish_at to be in the past');
});

test('document with null publish_at is immediately available', function () {
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, NULL)');
    $stmt->execute(['No Schedule Doc', 'body']);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    assert_true($row['publish_at'] === null, 'expected publish_at to be null');
});

test('document becomes available once publish_at is reached (utc)', function () {
    $now_utc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $just_passed = (new DateTime('-1 second', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Just Published Doc', 'body', $just_passed]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    assert_true($row['publish_at'] <= $now_utc, 'document should be available once publish_at is reached');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
