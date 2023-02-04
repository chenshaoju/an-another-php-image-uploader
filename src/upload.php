<?php
// Change 123456 before upload!
if ($_POST["password"] != "123456") { 
    die('Password is not correct.');
}

if (!isset($_FILES["image"])) {
    die("There is no file to upload.");
}

$filepath = $_FILES['image']['tmp_name'];
$fileSize = filesize($filepath);
$fileinfo = finfo_open(FILEINFO_MIME_TYPE);
$filetype = finfo_file($fileinfo, $filepath);

if ($fileSize === 0) {
    die("The file is empty.");
}

if ($fileSize > 3145728) {
    die("The file is too large");
}

$allowedTypes = [
   'image/gif' => 'gif',
   'image/png' => 'png',
   'image/jpeg' => 'jpg'
];

if (!in_array($filetype, array_keys($allowedTypes))) {
    die("File not allowed.");
}

$image_type = exif_imagetype($filepath);
if (!$image_type) {
    die('Uploaded file is not an image.');
}

if (!file_exists(__DIR__ . "/" . date('Y'))) {
    mkdir(__DIR__ . "/" . date('Y'), 0777, true);
}

$filename = time().basename($filepath);
$extension = $allowedTypes[$filetype];
$targetDirectory = __DIR__ . "/" . date('Y');

$newFilepath = $targetDirectory . "/" . $filename . "." . $extension;

if (!copy($filepath, $newFilepath)) {
    die("Can't move file.");
}
unlink($filepath);

if (isset($_SERVER['HTTPS']) &&
    ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
  $protocol = 'https://';
}
else {
  $protocol = 'http://';
}

echo '<input type="text" value="' . $protocol . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']) . "/" . date('Y') . "/" . $filename . "." . $extension . '" id="imgurl" size="50" />';
echo '<button onclick="copyurl()">Copy URL</button>';
echo '<br /><br /><img src="' . $protocol . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']) . "/" . date('Y') . "/" . $filename . "." . $extension . '" />';

?>
<script>
    function copyurl() {
  var copyText = document.getElementById("imgurl");
  copyText.select();
  copyText.setSelectionRange(0, 99999);

  navigator.clipboard.writeText(copyText.value);

  // Alert the copied text, uncomment if need.
//  alert("Copied the text: " + copyText.value);
} 
    </script>
