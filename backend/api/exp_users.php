<?php
require_once '../config/aws-config.php';
require_once '../vendor/autoload.php';
require_once '../db/db.php';

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

    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Fetch all users data
    $stmt = $db->prepare("
        SELECT 
            id, username, email, first_name, last_name, 
            graduation_year, major, current_company, 
            current_position, phone, linkedin_url, 
            profile_image, bio, created_at, updated_at,
            status, user_role
        FROM users 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        throw new Exception('No users found to export');
    }

    // Create CSV content
    $csvContent = '';
    
    // Add CSV headers
    $headers = [
        'ID', 'Username', 'Email', 'First Name', 'Last Name',
        'Graduation Year', 'Major', 'Current Company', 'Current Position',
        'Phone', 'LinkedIn URL', 'Profile Image', 'Bio',
        'Created At', 'Updated At', 'Status', 'Role'
    ];
    $csvContent .= implode(',', $headers) . "\n";

    // Add user data
    foreach ($users as $user) {
        $row = [
            $user['id'],
            '"' . str_replace('"', '""', $user['username']) . '"',
            '"' . str_replace('"', '""', $user['email']) . '"',
            '"' . str_replace('"', '""', $user['first_name']) . '"',
            '"' . str_replace('"', '""', $user['last_name']) . '"',
            $user['graduation_year'],
            '"' . str_replace('"', '""', $user['major']) . '"',
            '"' . str_replace('"', '""', $user['current_company']) . '"',
            '"' . str_replace('"', '""', $user['current_position']) . '"',
            '"' . str_replace('"', '""', $user['phone']) . '"',
            '"' . str_replace('"', '""', $user['linkedin_url']) . '"',
            '"' . str_replace('"', '""', $user['profile_image']) . '"',
            '"' . str_replace('"', '""', $user['bio']) . '"',
            $user['created_at'],
            $user['updated_at'],
            $user['status'],
            $user['user_role']
        ];
        $csvContent .= implode(',', $row) . "\n";
    }

    // Generate filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "exports/users_export_{$timestamp}.csv";
    
    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'users_export_');
    file_put_contents($tempFile, $csvContent);

    // Upload to S3
    $result = $s3Client->putObject([
        'Bucket' => AWS_S3_BUCKET,
        'Key' => $filename,
        'SourceFile' => $tempFile,
        'ContentType' => 'text/csv',
        'ACL' => 'private', // Keep exports private
        'Metadata' => [
            'exported-by' => $_SESSION['user_id'],
            'export-time' => date('Y-m-d H:i:s'),
            'total-users' => count($users)
        ],
        'ServerSideEncryption' => 'AES256'
    ]);

    // Clean up temp file
    unlink($tempFile);

    // Log the export
    $logStmt = $db->prepare("
        INSERT INTO export_logs (user_id, export_type, file_url, record_count, created_at) 
        VALUES (?, 'users', ?, ?, NOW())
    ");
    $logStmt->execute([$_SESSION['user_id'], $result['ObjectURL'], count($users)]);

    // Generate presigned URL for download (valid for 1 hour)
    $cmd = $s3Client->getCommand('GetObject', [
        'Bucket' => AWS_S3_BUCKET,
        'Key' => $filename
    ]);
    $request = $s3Client->createPresignedRequest($cmd, '+1 hour');
    $downloadUrl = (string) $request->getUri();

    echo json_encode([
        'success' => true,
        'message' => 'Users exported successfully',
        'download_url' => $downloadUrl,
        'filename' => $filename,
        'total_users' => count($users),
        'expires_in' => '1 hour'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Export error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
