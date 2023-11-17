<?php

// database is built on SQL

Class Database {

// these are hardcoded credentials for now, advise moving to .env file later in project

    private $db_host = "localhost"; // host name for db server
    private $db_port = 3306; // port for db server
    private $db_name = "spacetech_dev"; // database name
    private $db_username = "spacetech_dev"; // username for accessing db
    private $db_password = "m@tz%H83dtS4#XS"; // password for accessing db

    private $db_connection = null;

    private function createDatabaseConnection() {

        // check if database connectoin is already established

        if($this->db_connection === null) {
            $this->db_connection = new mysqli($this->db_host, $this->db_username, $this->db_password, $this->db_name, $this->db_port);
        }

        // if a connection is already established, continue on to ensure
        // that connection object is successfully connected

        return $this->testDatabaseConnection();

    }

    private function destroyDatabaseConnection() {
        if($this->db_connection !== null) {
            $this->db_connection -> close();
        }
        $this->db_connection == null;
    }

    public function testDatabaseConnection() {
        if($this->db_connection === null) {
            $this->createDatabaseConnection();
        }

        if($this->db_connection->connect_error) {
            $msg = "Connection Error: " . htmlspecialchars($this->db_connection->connect_error, ENT_QUOTES);
        } else {
            $msg = "OK";
        }
        //$this->destroyDatabaseConnection();
        return $msg;
    }

    public function checkSetup() {
        if($this->createDatabaseConnection() == "OK") {
            try {
                $this->db_connection->execute_query("SELECT 1 FROM `users` LIMIT 1");
            } catch(mysqli_sql_exception $e) {
                // setup has not occurred, default superadmin user does not exist
                // trigger creation of database tables
                $this->runSetup();
                return true;
            }
        } else {
            return true; // setup has already occurred
        }
    }
        
    private function generatePasswordHash($password) {
      return password_hash($password, PASSWORD_DEFAULT);
    }

    private function checkPassword($input, $hash) {
      return password_verify($input, $hash);
    }

    private function runSetup() {
        $sql_setup_commands = ["CREATE TABLE `users` (
            `user_id` integer PRIMARY KEY,
            `user_email` varchar(50) NOT NULL UNIQUE,
            `user_passwordhash` varchar(255) NOT NULL,
            `user_name` varchar(50) NOT NULL,
            `user_isstaff` boolean NOT NULL DEFAULT false,
            `user_isadmin` boolean NOT NULL DEFAULT false
          );",

          "ALTER TABLE `users` CHANGE `user_id` `user_id` INT(11) NOT NULL AUTO_INCREMENT;",
          
          "CREATE TABLE `categories` (
            `category_id` integer PRIMARY KEY,
            `category_name` varchar(50) NOT NULL,
            `category_isdisabled` boolean NOT NULL DEFAULT false,
            `category_image` varchar(255)
          );",
          
          "CREATE TABLE `products` (
            `product_id` integer PRIMARY KEY,
            `category_id` integer NOT NULL,
            `product_name` varchar(75) NOT NULL,
            `product_desc` text NOT NULL,
            `product_price` decimal(6,2) NOT NULL,
            `product_stockcount` integer NOT NULL DEFAULT 0,
            `product_isdisabled` boolean NOT NULL DEFAULT false
          );",
          
          "CREATE TABLE `reviews` (
            `review_id` integer PRIMARY KEY,
            `review_userid` integer NOT NULL,
            `review_productid` integer NOT NULL,
            `review_rating` integer NOT NULL COMMENT 'Constrain input to only allow 1 to 5 stars',
            `review_text` text NOT NULL
          );",
          
          "CREATE TABLE `basket_entries` (
            `basket_entry_id` integer PRIMARY KEY,
            `basket_userid` integer NOT NULL,
            `basket_productid` integer NOT NULL,
            `entry_quanitity` integer NOT NULL DEFAULT 1,
            `entry_subtotal` decimal(6,2) NOT NULL
          );",
          
          "CREATE TABLE `orders` (
            `order_id` integer PRIMARY KEY,
            `order_userid` integer NOT NULL,
            `order_address` text NOT NULL,
            `order_comments` text NOT NULL,
            `order_total` decimal(6,2) NOT NULL,
            `order_ispaid` boolean NOT NULL DEFAULT true,
            `order_status` integer NOT NULL
          );",
          
          "CREATE TABLE `order_status` (
            `status_id` integer PRIMARY KEY,
            `status_name` varchar(20) NOT NULL,
            `status_colour` varchar(7) NOT NULL COMMENT 'Hex colour value'
          );",
          
          "CREATE TABLE `order_items` (
            `line_id` integer PRIMARY KEY,
            `order_id` integer NOT NULL,
            `product_id` integer NOT NULL,
            `line_quantity` integer NOT NULL,
            `line_subtotal` decimal(6,2) NOT NULL
          );",
          
          "CREATE TABLE `returns` (
            `return_id` integer PRIMARY KEY,
            `return_customer_id` integer NOT NULL,
            `return_order_id` integer NOT NULL,
            `return_line_id` integer NOT NULL,
            `return_reason` text NOT NULL,
            `return_status` integer NOT NULL
          );",
          
          "ALTER TABLE `products` ADD FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);",
          
          "ALTER TABLE `reviews` ADD FOREIGN KEY (`review_userid`) REFERENCES `users` (`user_id`);",
          
          "ALTER TABLE `reviews` ADD FOREIGN KEY (`review_productid`) REFERENCES `products` (`product_id`);",
          
          "ALTER TABLE `basket_entries` ADD FOREIGN KEY (`basket_productid`) REFERENCES `products` (`product_id`);",
          
          "ALTER TABLE `orders` ADD FOREIGN KEY (`order_userid`) REFERENCES `users` (`user_id`);",
          
          "ALTER TABLE `orders` ADD FOREIGN KEY (`order_status`) REFERENCES `order_status` (`status_id`);",
          
          "ALTER TABLE `order_items` ADD FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);",
          
          "ALTER TABLE `order_items` ADD FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);",
          
          "ALTER TABLE `returns` ADD FOREIGN KEY (`return_customer_id`) REFERENCES `users` (`user_id`);",
          
          "ALTER TABLE `returns` ADD FOREIGN KEY (`return_order_id`) REFERENCES `orders` (`order_id`);",
          
          "ALTER TABLE `returns` ADD FOREIGN KEY (`return_line_id`) REFERENCES `order_items` (`line_id`);",
          
          "ALTER TABLE `returns` ADD FOREIGN KEY (`return_status`) REFERENCES `order_status` (`status_id`);"

        ];
        if($this->createDatabaseConnection() == "OK") {
            foreach ($sql_setup_commands as $command) {
                $result = $this->db_connection->execute_query($command);
            }
        }
    }

    public function createUser($email, $password, $name) {
      if($this->createDatabaseConnection() == "OK") {
        try {
          $result = $this->db_connection->execute_query("SELECT user_email FROM `users` WHERE `user_email` LIKE ?", [$email]);
          if($result->num_rows > 0) {
            while ($row = $result->fetch_assoc() ) {
              if(strtolower($row["user_email"]) == $email) {
                return "Error - supplied email address already in use.";
              }
            }
          } else {
              $passhash = $this->generatePasswordHash($password);
              $this->db_connection->execute_query("INSERT INTO `users` (`user_email`, `user_passwordhash`, `user_name`) VALUES (?,?,?);", [$email, $passhash, $name]);
              return "User account created successfully.";
          }
        } catch (Exception $e) {
          return "An error occurred. Stack trace: " . $e;
        }
      }
    }

    
}

$db_handler = new Database();
echo $db_handler->testDatabaseConnection();
echo $db_handler->checkSetup();
echo $db_handler->createUser("220148717@aston.ac.uk", "password", "Harrison");
echo $db_handler->createUser("220148717@aston.ac.uk", "password2", "Harrison2");

?>