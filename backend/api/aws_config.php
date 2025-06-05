<?php
// AWS Configuration using IAM Role (No access keys needed)
define('AWS_REGION', 'eu-north-1');
define('AWS_S3_BUCKET', 'alumni-club-files-13');
define('S3_BASE_URL', 'https://' . AWS_S3_BUCKET . '.s3.' . AWS_REGION . '.amazonaws.com/');

// VPC Configuration
define('VPC_CIDR', '10.0.0.0/16');

// Database Configuration
define('DB_HOST', 'alumni-club-db-1.c360m0agikz5.eu-north-1.rds.amazonaws.com');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'alumniClub2025');
define('DB_NAME', 'alumni-club-db-1');
define('DB_PORT', 3306);

// Application Configuration
define('APP_ENV', 'production');
define('APP_DEBUG', false);
?>
