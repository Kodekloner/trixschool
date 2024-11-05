<?php
require  '/var/www/trixschool/vendor/autoload.php'; // Adjust the path as needed

use Aws\S3\S3Client;
use Aws\Exception\S3Exception;

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
        $domain = $_SERVER['HTTP_HOST'];
        $full_domain = preg_replace('/^www\./i', '', $domain); // Remove "www." if it exists
        $domain = preg_replace('/\.(com\.ng|com|ng|org\.ng|org)$/i', '', $full_domain); // Remove common extensions

        // Extract file info
        $img_name = $domain . '.' . $img_name;

        $fileExtension = strtolower($fileInfo['extension']);


        // Define MIME types
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            // Add more mappings if necessary
        ];

        // Determine content type
        $contentType = isset($mimeTypes[$fileExtension]) ? $mimeTypes[$fileExtension] : 'application/octet-stream';

        // S3 Bucket and key setup
        $bucket = 'schoollift';
        $key = $s3_folder . $img_name; // Use the basename or modify the name as needed

        try {
            // Initialize the S3 client
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-2',
                'credentials' => [
                    'key'    => 'AKIAXC7XRFGT25OMXJ22',
                    'secret' => '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',
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
