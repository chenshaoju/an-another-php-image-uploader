<?php
session_start();

const MAX_FILE_SIZE = 3145728;
const RATE_LIMIT_WINDOW_SECONDS = 300;
const RATE_LIMIT_MAX_ATTEMPTS = 30;

function fail(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    exit;
}

function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function enforceRateLimit(string $ip): void
{
    $key = hash('sha256', $ip);
    $rateFile = sys_get_temp_dir() . '/php_uploader_rate_' . $key . '.json';
    $now = time();

    $state = ['start' => $now, 'count' => 0];
    if (file_exists($rateFile)) {
        $decoded = json_decode((string)file_get_contents($rateFile), true);
        if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
            $state = [
                'start' => (int)$decoded['start'],
                'count' => (int)$decoded['count'],
            ];
        }
    }

    if (($now - $state['start']) > RATE_LIMIT_WINDOW_SECONDS) {
        $state = ['start' => $now, 'count' => 0];
    }

    $state['count']++;
    file_put_contents($rateFile, json_encode($state), LOCK_EX);

    if ($state['count'] > RATE_LIMIT_MAX_ATTEMPTS) {
        fail('Too many requests. Please try again later.', 429);
    }
}

function getConfiguredPasswordHash(): string
{
    $hash = getenv('UPLOADER_PASSWORD_HASH');
    if (!is_string($hash) || trim($hash) === '') {
        fail('Server is not configured. Missing UPLOADER_PASSWORD_HASH.', 500);
    }

    return $hash;
}

function validateCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
        fail('Invalid CSRF token.', 403);
    }
}

function buildPublicUrl(string $relativePath): string
{
    $baseUrl = getenv('APP_BASE_URL');
    if (is_string($baseUrl) && trim($baseUrl) !== '') {
        return rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $protocol = $isHttps ? 'https://' : 'http://';

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host);

    $scriptDir = trim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/');
    $prefix = $scriptDir === '' ? '' : $scriptDir . '/';

    return $protocol . $host . '/' . $prefix . ltrim($relativePath, '/');
}

function buildPublicRelativePrefix(): string
{
    $configuredPrefix = getenv('UPLOAD_URL_PREFIX');
    if (is_string($configuredPrefix)) {
        return trim($configuredPrefix, '/');
    }

    // Backward-compatible default: when UPLOAD_DIR is not set, files are under current folder.
    // If UPLOAD_DIR is explicitly configured, do not force an extra /uploads path segment.
    $hasCustomUploadDir = getenv('UPLOAD_DIR');
    if (is_string($hasCustomUploadDir) && trim($hasCustomUploadDir) !== '') {
        return '';
    }

    return 'uploads';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('Method not allowed.', 405);
}

enforceRateLimit(getClientIp());
validateCsrfToken();

$password = $_POST['password'] ?? '';
if (!is_string($password) || !password_verify($password, getConfiguredPasswordHash())) {
    fail('Password is not correct.', 401);
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    fail('There is no file to upload.');
}

if ((int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail('Upload failed with error code: ' . (int)$_FILES['image']['error']);
}

$filepath = (string)($_FILES['image']['tmp_name'] ?? '');
if ($filepath === '' || !is_uploaded_file($filepath)) {
    fail('Invalid upload source.');
}

$fileSize = filesize($filepath);
if ($fileSize === false || $fileSize === 0) {
    fail('The file is empty.');
}

if ($fileSize > MAX_FILE_SIZE) {
    fail('The file is too large.');
}

$fileinfo = finfo_open(FILEINFO_MIME_TYPE);
if ($fileinfo === false) {
    fail('File type detection is unavailable.', 500);
}

$filetype = finfo_file($fileinfo, $filepath);
finfo_close($fileinfo);

$allowedTypes = [
    'image/gif' => 'gif',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
];

if (!is_string($filetype) || !array_key_exists($filetype, $allowedTypes)) {
    fail('File not allowed.');
}

if (exif_imagetype($filepath) === false) {
    fail('Uploaded file is not an image.');
}

$uploadRoot = getenv('UPLOAD_DIR');
if (!is_string($uploadRoot) || trim($uploadRoot) === '') {
    $uploadRoot = './';
}

$yearDir = $uploadRoot . '/' . date('Y');
if (!is_dir($yearDir) && !mkdir($yearDir, 0755, true) && !is_dir($yearDir)) {
    fail('Upload directory could not be created.', 500);
}

$filename = bin2hex(random_bytes(16));
$extension = $allowedTypes[$filetype];
$newFilepath = $yearDir . '/' . $filename . '.' . $extension;

if (!move_uploaded_file($filepath, $newFilepath)) {
    fail("Can't move file.", 500);
}


$publicPrefix = buildPublicRelativePrefix();
$publicPath = ($publicPrefix === '' ? '' : $publicPrefix . '/') . date('Y') . '/' . $filename . '.' . $extension;
$publicPath = '' . date('Y') . '/' . $filename . '.' . $extension;
$publicUrl = buildPublicUrl($publicPath);
$safeUrl = htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<body>
<input type="text" value="<?= $safeUrl ?>" id="imgurl" size="50" readonly />
<button onclick="copyurl()">Copy URL</button>
<br /><br /><img src="<?= $safeUrl ?>" alt="Uploaded image" />
<script>
function copyurl() {
  var copyText = document.getElementById('imgurl');
  copyText.select();
  copyText.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(copyText.value);
}
</script>
</body>
</html>
