<?php
// Change 123456 before upload!
if ($_POST["password"] != "123456") { 
    die('Password is not correct.');
}

$image_file = $_FILES["image"];

if (!isset($image_file)) {
    die('No file uploaded.');
}

if (!file_exists(__DIR__ . "/" . date('Y'))) {
    mkdir(__DIR__ . "/" . date('Y'), 0777, true);
}

$image_type = exif_imagetype($image_file["tmp_name"]);
if (!$image_type) {
    die('Uploaded file is not an image.');
}

if (file_exists( __DIR__ . "/" . date('Y') . "/" . $image_file["name"] )){
    die('File already exists.');
}

move_uploaded_file(
    $image_file["tmp_name"],
    __DIR__ . "/" . date('Y') . "/" . $image_file["name"]

);

echo "https://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']) . "/" . date('Y') . "/" . $image_file["name"];

?>
