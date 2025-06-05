<?php
require_once '../config/aws-config.php';
require_once '../vendor/autoload.php';
require_once '../db/DB.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

try {
    // Initialize S3 Client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => AWS_REGION,
    ]);

    $db = DB::getInstance()->getConnection();
    
    // Handle file upload to S3 first
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        
        // Validate file type
        if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            throw new Exception('Invalid file type. Please upload a CSV file.');
        }
        
        if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
            throw new Exception('File too large. Maximum size: 50MB');
        }
        
        // Generate filename
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "imports/users_import_{$timestamp}.csv";
        
        // Upload to S3
        $uploadResult = $s3Client->putObject([
            'Bucket' => AWS_S3_BUCKET,
            'Key' => $filename,
            'SourceFile' => $file['tmp_name'],
            'ContentType' => 'text/csv',
            'ACL' => 'private',
            'Metadata' => [
                'imported-by' => $_SESSION['user_id'],
                'import-time' => date('Y-m-d H:i:s'),
                'original-filename' => $file['name']
            ],
            'ServerSideEncryption' => 'AES256'
        ]);
        
        // Process the uploaded file
        $csvData = file_get_contents($file['tmp_name']);
        
    } elseif (isset($_POST['s3_file_key'])) {
        // Import from existing S3 file
        $s3FileKey = $_POST['s3_file_key'];
        
        // Download file from S3
        $result = $s3Client->getObject([
            'Bucket' => AWS_S3_BUCKET,
            'Key' => $s3FileKey
        ]);
        
        $csvData = $result['Body']->getContents();
        $filename = $s3FileKey;
        
    } else {
        throw new Exception('No CSV file provided');
    }

    // Parse CSV data
    $lines = str_getcsv($csvData, "\n");
    if (empty($lines)) {
        throw new Exception('CSV file is empty');
    }

    // Get headers (first line)
    $headers = str_getcsv(array_shift($lines));
    
    // Validate required headers
    $requiredHeaders = ['email', 'username', 'first_name', 'last_name'];
    $missingHeaders = array_diff($requiredHeaders, array_map('strtolower', $headers));
    
    if (!empty($missingHeaders)) {
        throw new Exception('Missing required headers: ' . implode(', ', $missingHeaders));
    }

    // Map headers to lowercase for easier processing
    $headerMap = array_flip(array_map('strtolower', $headers));
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    $db->beginTransaction();
    
    try {
        foreach ($lines as $lineNumber => $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line);
            
            if (count($data) !== count($headers)) {
                $errors[] = "Line " . ($lineNumber + 2) . ": Column count mismatch";
                $errorCount++;
                continue;
            }
            
            // Extract user data
            $userData = [
                'email' => $data[$headerMap['email']] ?? '',
                'username' => $data[$headerMap['username']] ?? '',
                'first_name' => $data[$headerMap['first_name']] ?? '',
                'last_name' => $data[$headerMap['last_name']] ?? '',
                'graduation_year' => $data[$headerMap['graduation_year']] ?? null,
                'major' => $data[$headerMap['major']] ?? '',
                'current_company' => $data[$headerMap['current_company']] ?? '',
                'current_position' => $data[$headerMap['current_position']] ?? '',
                'phone' => $data[$headerMap['phone']] ?? '',
                'linkedin_url' => $data[$headerMap['linkedin_url']] ?? '',
                'bio' => $data[$headerMap['bio']] ?? '',
                'user_role' => $data[$headerMap['user_role']] ?? 'alumni',
                'status' => $data[$headerMap['status']] ?? 'active'
            ];
            
            // Validate required fields
            if (empty($userData['email']) || empty($userData['username'])) {
                $errors[] = "Line " . ($lineNumber + 2) . ": Missing email or username";
                $errorCount++;
                continue;
            }
            
            // Check if user already exists
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $checkStmt->execute([$userData['email'], $userData['username']]);
            
            if ($checkStmt->fetch()) {
                // Update existing user
                $updateStmt = $db->prepare("
                    UPDATE users SET 
                        first_name = ?, last_name = ?, graduation_year = ?, 
                        major = ?, current_company = ?, current_position = ?, 
                        phone = ?, linkedin_url = ?, bio = ?, user_role = ?, 
                        status = ?, updated_at = NOW()
                    WHERE email = ? OR username = ?
                ");
                $updateStmt->execute([
                    $userData['first_name'], $userData['last_name'], $userData['graduation_year'],
                    $userData['major'], $userData['current_company'], $userData['current_position'],
                    $userData['phone'], $userData['linkedin_url'], $userData['bio'],
                    $userData['user_role'], $userData['status'],
                    $userData['email'], $userData['username']
                ]);
            } else {
                // Insert new user
                $insertStmt = $db->prepare("
                    INSERT INTO users (
                        email, username, first_name, last_name, graduation_year,
                        major, current_company, current_position, phone, 
                        linkedin_url, bio, user_role, status, password_hash, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                // Generate temporary password
                $tempPassword = bin2hex(random_bytes(8));
                $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                $insertStmt->execute([
                    $userData['email'], $userData['username'], $userData['first_name'],
                    $userData['last_name'], $userData['graduation_year'], $userData['major'],
                    $userData['current_company'], $userData['current_position'],
                    $userData['phone'], $userData['linkedin_url'], $userData['bio'],
                    $userData['user_role'], $userData['status'], $passwordHash
                ]);
            }
            
            $successCount++;
        }
        
        $db->commit();
        
        // Log the import
        $logStmt = $db->prepare("
            INSERT INTO import_logs (user_id, import_type, file_url, success_count, error_count, created_at) 
            VALUES (?, 'users', ?, ?, ?, NOW())
        ");
        $logStmt->execute([$_SESSION['user_id'], $filename, $successCount, $errorCount]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Import completed',
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => array_slice($errors, 0, 10), // Limit errors shown
            'filename' => $filename
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Import error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
