<?php

header('Content-Type: application/json');

session_start();
$phpInputTemp = file_get_contents('php://input');


$phpInput = json_decode($phpInputTemp, true);
require_once "../models/User.php";
mb_internal_encoding("UTF-8");

if (empty($phpInput['email'])){ 
    echo json_encode([
        'success' => false,
        'message' => "Полето имейл е задължително.",
    ]);
} 
if (empty($phpInput['password'])){
    echo json_encode([
        'success' => false,
        'message' => "Полето парола е задължително",
    ]);
}
else {

        $email = $phpInput['email'];
        $password = $phpInput['password'];

        $user = new User(null, null, null, $email, $password, null, null, null);

        try {

            $user->checkLogin();

            $_SESSION['email'] = $phpInput['email'];

            echo json_encode([
                'success' => true,
                'email' => $_SESSION['email'],
            ]);
            
        } catch (Exception $e) {
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    
}

