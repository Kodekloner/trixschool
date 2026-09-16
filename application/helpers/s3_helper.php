<?php
$schoolliftComposerAutoload = '/var/www/trixschool/vendor/autoload.php';
if (is_file($schoolliftComposerAutoload)) {
    require_once $schoolliftComposerAutoload;
}

use Aws\S3\S3Client;
use Aws\Exception\S3Exception;
use Dotenv\Dotenv;

if (class_exists(Dotenv::class) && is_file('/var/www/trixschool/.env')) {
    $dotenv = Dotenv::createImmutable('/var/www/trixschool');
    $dotenv->load();
}

if (!function_exists('school_upload_content_type')) {
    /** Determine upload metadata from the file bytes, not only its user-supplied extension. */
    function school_upload_content_type($file_path, $extension = '')
    {
        if (is_file($file_path) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_file($finfo, $file_path) : false;
            if ($finfo) {
                finfo_close($finfo);
            }
            if (is_string($detected) && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $detected)) {
                return $detected;
            }
        }

        $mimeTypes = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        );
        $extension = strtolower(ltrim((string) $extension, '.'));
        return isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
    }
}

if (!function_exists('upload_to_s3')) {
    /**
     * Uploads a file to Amazon S3.
     *
     * @param string $file_path Path to the file on the local server (e.g. $_FILES["file"]["tmp_name"])
     * @param string $file_name The name of the file (e.g. $_FILES["file"]["name"])
     * @param string $s3_folder The folder path in the S3 bucket where the file should be uploaded.
    * @return array
     */
    function upload_to_s3($file_path, $fileInfo, $img_name, $s3_folder = 'uploads/') {
        $domain = school_asset_tenant_prefix();

        // Extract file info
        $img_name = $domain . '.' . $img_name;

        $fileExtension = strtolower($fileInfo['extension']);


        // Use the actual file bytes so a mislabeled extension cannot create incorrect S3 metadata.
        $contentType = school_upload_content_type($file_path, $fileExtension);

        // S3 Bucket and key setup
        $bucket = 'schoollift';
        $key = $s3_folder . $img_name; // Use the basename or modify the name as needed

        try {
            // Initialize the S3 client
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-2',
                'credentials' => [
                    'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                ],
            ]);

            // Upload the file to S3
            // log_message('info', 'Uploading file to S3: ' . $key);
            $result = $s3->putObject([
                'Bucket' => $bucket,
                'Key'    => $key,
                'Body'   => fopen($file_path, 'r'),
                'ContentType' => $contentType,
            ]);

            // Return success with S3 key
            // log_message('info', 'File uploaded successfully to S3.');
            return [
                'success' => true,
                's3_key' => $key,
                'new_image_name' => $img_name,
                'message' => 'File uploaded successfully.',
            ];

        } catch (S3Exception $e) {
            error_log($e->getMessage());
            // Return error in case of failure
            return [
                'success' => false,
                'error' => 'Error uploading to S3: ' . $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('school_asset_tenant_prefix')) {
    /**
     * Match the tenant prefix added by upload_to_s3 (for example, hameedacademy.10.jpg).
     */
    function school_asset_tenant_prefix()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
        $host = preg_replace('/:\d+$/', '', $host);
        $host = preg_replace('/^www\./i', '', $host);
        return preg_replace('/\.(com\.ng|com|ng|org\.ng|org)$/i', '', $host);
    }
}

if (!function_exists('is_school_s3_filename')) {
    function is_school_s3_filename($filename)
    {
        $prefix = school_asset_tenant_prefix();
        return $prefix !== '' && stripos(basename((string) $filename), $prefix . '.') === 0;
    }
}

if (!function_exists('get_school_asset_url')) {
    /**
     * Resolve a stored asset value to a browser-safe URL.
     *
     * Supports full URLs, S3 keys like uploads/..., and filename-only values.
     * Filename-only values carrying the tenant prefix resolve to S3 because upload_to_s3 stores only
     * that basename in legacy rows. Older unprefixed filenames remain on the tenant origin.
     */
    function get_school_asset_url($path, $local_directory = '')
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $normalized_path = ltrim(str_replace('\\', '/', $path), '/');

        if (strpos($normalized_path, 'uploads/') === 0) {
            return 'https://schoollift.s3.us-east-2.amazonaws.com/' . $normalized_path;
        }

        if ($local_directory !== '') {
            $normalized_directory = trim(str_replace('\\', '/', $local_directory), '/');
            $resolved_path = $normalized_directory . '/' . basename($normalized_path);
            if (strpos($resolved_path, 'uploads/') === 0 && is_school_s3_filename($normalized_path)) {
                return 'https://schoollift.s3.us-east-2.amazonaws.com/' . $resolved_path;
            }
            return base_url($resolved_path);
        }

        return base_url($normalized_path);
    }
}

if (!function_exists('build_school_media_asset_path')) {
    /**
     * Build a stored media path that works for both legacy local records and S3-backed records.
     */
    function build_school_media_asset_path($img_name, $dir_path = '')
    {
        $img_name = trim((string) $img_name);
        $dir_path = trim((string) $dir_path);

        if ($img_name === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $img_name)) {
            return $img_name;
        }

        $normalized_img = ltrim($img_name, '/');

        if (strpos($normalized_img, 'uploads/') === 0) {
            return $normalized_img;
        }

        if ($dir_path !== '') {
            return trim($dir_path, '/') . '/' . basename($normalized_img);
        }

        return $normalized_img;
    }
}

if (!function_exists('get_school_media_url')) {
    function get_school_media_url($img_name, $dir_path = '')
    {
        return get_school_asset_url(build_school_media_asset_path($img_name, $dir_path));
    }
}

if (!function_exists('get_school_media_thumb_url')) {
    function get_school_media_thumb_url($img_name, $thumb_path = '', $dir_path = '')
    {
        $img_name   = trim((string) $img_name);
        $thumb_path = trim((string) $thumb_path);

        if ($img_name === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $img_name)) {
            return $img_name;
        }

        $normalized_img = ltrim($img_name, '/');

        if (strpos($normalized_img, 'uploads/') === 0) {
            return get_school_asset_url($normalized_img);
        }

        if ($thumb_path !== '') {
            return get_school_asset_url(trim($thumb_path, '/') . '/' . basename($normalized_img));
        }

        return get_school_media_url($img_name, $dir_path);
    }
}
