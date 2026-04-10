<?php


require 'db.php';

$username = 'admin';
$password = 'admin123';
$hashed   = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$exists = db_col($pdo, "SELECT COUNT(*) FROM users WHERE username = ?", [$username]);

if ($exists) {
    echo "<p style='font-family:sans-serif;color:orange;padding:20px'>
          ⚠️ Admin user already exists. No changes made.</p>";
} else {
    db_run($pdo, "INSERT INTO users (username, password) VALUES (?, ?)", [$username, $hashed]);
    echo "<p style='font-family:sans-serif;color:green;padding:20px'>
          ✅ Admin account created!<br>
          Username: <strong>$username</strong><br>
          Password: <strong>$password</strong><br><br>
          <a href='index.php'>Go to Login →</a><br><br>
          ⚠️ <strong>Delete this file (hash.php) from your server after use!</strong>
          </p>";
}
?>