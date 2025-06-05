<?php
    //change database name
    
    class DB {
        public $connection;

        public function __construct() {
	            $this->connection = new PDO("mysql:host=alumni-club-db-1.c360m0agikz5.eu-north-1.rds.amazonaws.com;port=3306;dbname=alumniclub_db","admin","alumniClub2025");
        }

        public function getConnection() {
            return $this->connection;
        }
    }



