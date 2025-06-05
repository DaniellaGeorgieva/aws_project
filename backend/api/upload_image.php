<?php

require '../../../vendor/autoload.php';
use Aws\S3\S3Client;

        $s3client = new S3Client(['region' => 'eu-north-1']);
  	$bucketName = "alumni-club-files-13";


$file = $_FILES['photo'];


$name = $file['name'];
$tmp_name = $file['tmp_name'];

$extension = explode('.', $name);
$extension = strtolower(end($extension));


//temp details
$tmp_file_name = uniqid() . "." . "{$extension}";
$tmp_file_path = "../../files/uploaded/{$tmp_file_name}";


//Move  the file

move_uploaded_file($tmp_name, $tmp_file_path);


       try {
            $s3client->putObject([
                'Bucket' => $bucketName,
                'Key' => "uploads/{$name}",
		'Body' => fopen($tmp_file_path, 'rb')
               
            ]);
		//remove the file
		unlink($tmp_file_path);
               
            echo json_encode([
            	'success' => true,
            	'message' => "Successfully uploaded",
		'filepath' => $name
            ]);

        } catch (Exception $exception) {
            echo "Failed to upload $fileName with error: " . $exception->getMessage();

		echo json_encode([
            		'success' => false,
            		'message' => "Failed"
        	]);
            exit("Please fix error with file upload before continuing.");
        }

