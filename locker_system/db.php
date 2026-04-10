<?php
$host   = 'localhost';
$dbname = 'locker_system_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:40px;color:red'>
         ❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "
         </div>");
}

function db_all($pdo, $sql, $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function db_one($pdo, $sql, $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC);
}
function db_col($pdo, $sql, $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn();
}
function db_run($pdo, $sql, $p = []) {
    $s = $pdo->prepare($sql); $s->execute($p); return $s;
}
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
?>