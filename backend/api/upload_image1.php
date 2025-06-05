<?php
require_once '../vendor/autoload.php';

// AWS Configuration using IAM Role (No access keys needed)
define('AWS_REGION', 'eu-north-1');
define('AWS_S3_BUCKET', 'alumni-club-files-13');
define('S3_BASE_URL', 'https://' . AWS_S3_BUCKET . '.s3.' . AWS_REGION . '.amazonaws.com/');


use Aws\S3\S3Client;
use Aws\Exception\AwsException;

session_start();

echo "step 1";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo "step 2";

    // S3 Client using IAM Role - No credentials needed!
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => AWS_REGION,
        // AWS SDK automatically uses the EC2 IAM role
    ]);

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $filename = $_FILES["anyfile"]["name"];
        $filetype = $_FILES["anyfile"]["type"];
        $filesize = $_FILES["anyfile"]["size"];
        
        if (!in_array($filetype, $allowedTypes)) {
            throw new Exception('Invalid file type');
        }
        
        if ($filesize > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception('File too large');
        }
        if(file_exists("../../files/uploaded/" . $filename)){
            echo $filename . " is already exists.";
        } 
        else{
           if(move_uploaded_file($_FILES["anyfile"]["tmp_name"], "files/uploaded/" . $filename)){
                
                $file_Path ='../../files/uploads/'. $filename;
                $key = basename($file_Path);
                try {
                   $result = $s3Client->putObject([
                      'Bucket' => AWS_S3_BUCKET,
                      'Body'   => fopen($file_Path, 'r'),
                      'Key'    => $key,
                      'ACL'    => 'public-read', // make file 'public'
                   ]);
               } catch (Aws\S3\Exception\S3Exception $e) {
                     echo "There was an error uploading the file.\n";
                     echo $e->getMessage();
               }
     }else{
           echo "File is not uploaded";
     }      
 
