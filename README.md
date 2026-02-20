# an-another-php-image-uploader

## demo
![demo.gif](https://github.com/chenshaoju/an-another-php-image-uploader/blob/main/demo.gif?raw=true)

## requirements
Any webserver with PHP support (`session`, `fileinfo`, and `exif`).

## secure setup
1. Upload files from the `src` directory.
2. Use `index.php` (not `index.htm`) as the entry page.
3. Configure a password hash via environment variable:

```bash
php -r "echo password_hash('replace-with-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Then set:

```bash
UPLOADER_PASSWORD_HASH='<generated hash>'
```

4. Optionally configure:
   - `UPLOAD_DIR` (default: `../uploads` relative to project root)
   - `APP_BASE_URL` (recommended, e.g. `https://example.com`)

5. Ensure upload directory permissions are least privilege (`0755` or tighter based on your deployment).

## reference

https://dev.to/einlinuus/how-to-upload-files-with-php-correctly-and-securely-1kng

https://stackoverflow.com/questions/10717249/get-current-domain

https://stackoverflow.com/questions/51789617/php-get-url-of-current-file-directory

https://stackoverflow.com/questions/27036435/php-postpassword

https://blog.csdn.net/xgocn/article/details/79301171

https://stackoverflow.com/questions/4503135/php-get-site-url-protocol-http-vs-https

https://www.w3schools.com/howto/howto_js_copy_clipboard.asp

https://www.w3schools.com/tags/att_input_size.asp
