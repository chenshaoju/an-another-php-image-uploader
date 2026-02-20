<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<body>
<form action="upload.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />
    <input type="file" name="image" accept="image/gif,image/png,image/jpeg" required />
    <p>Password: <input type="password" name="password" required /></p>
    <button type="submit">Upload</button>
</form>
</body>
</html>
