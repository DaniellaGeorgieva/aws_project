<?php 

header('Content-Type: application/json');
echo "Step 1";
session_start();
echo "Step 2";
$phpInput = json_decode(file_get_contents('php://input'), true);
echo "Step 3";
require_once "../models/User.php";
echo "step 4";
mb_internal_encoding("UTF-8");
echo "step 5";
echo $phpInput['email'];

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
	
	echo "step 8";
        
        
        require_once "../db/db.php";

		try{
			$db = new DB();
			$conn = $db->getConnection();
		}
		catch (PDOException $e) {
			echo json_encode([
				'success' => false,
				'message' => "Неуспешно свързване с базата данни",
			]);
			exit();
		}
	echo "step 8";

        $selectStatement = $conn->prepare("SELECT * FROM `users` WHERE emailAddress = :emailAddress");
	echo "step 9";
        $result = $selectStatement->execute(['emailAddress' => $email]);
	echo "step 10";	

		$dbUser = $selectStatement->fetch();
	echo "step 11";
		if ($dbUser == false) {
            throw new Exception("Грешно потребителско име.");
		}
	echo "step 12";
		
        if (!password_verify($password, $dbUser['UserPassword'])) {
            throw new Exception("Грешна парола."); 
        }    
	echo "step 13";
	 $_SESSION['email'] = $phpInput['email'];
	echo $_SESSION['email'];
	 echo json_encode([
                'success' => true,
                'email' => $_SESSION['email'],
            ]);
	echo "step 14";

}
