<?php

// database is built on SQL

class Database
{

  // these are hardcoded credentials for now, advise moving to .env file later in project

  private $db_host = "localhost"; // host name for db server
  private $db_port = 3306; // port for db server
  private $db_name = "spacetech_dev"; // database name
  private $db_username = "spacetech_dev"; // username for accessing db
  private $db_password = "m@tz%H83dtS4#XS"; // password for accessing db

  private $db_connection = null;

    public function validateResetToken($token) {

      // input validation
      if(!is_string($token)) {
        return false;
      }

      // check db connection
      if($this->createDatabaseConnection() !== "OK") {
        return false;
      }

      try {
        $result = $this->db_connection->execute_query("SELECT * FROM `password_reset_tokens` WHERE `token` = ?;", [$token]);

        if($result->num_rows <= 0) {
          // no results found, token invalid
          
          return false;
        }

        while ($row = $result->fetch_assoc() ) {
          if($row["expiry_time"] > time()) {
            // token valid
            return true;
          }
        }
        return false;

      } catch(Exception $e) {
        return false;
      }

    }

    

    public function getUserIDFromResetToken($token) {

      // input validation
      if(!is_string($token)) {
        return "Input validation failed.";
      }

      // check db connection
      if($this->createDatabaseConnection() !== "OK") {
        return $this->ERROR_MSG_DB_CONNECTION_FAILED;
      }

      try {
        $result = $this->db_connection->execute_query("SELECT * FROM `password_reset_tokens` WHERE `token` = ?;", [$token]);

        if($result->num_rows <= 0) {
          // no results found, token invalid
          return false;
        }

        while ($row = $result->fetch_assoc() ) {
          return $this->getUserIDFromEmail($row["email"]);
        }
        
        return false;

      } catch(Exception $e) {
        return "Database query error.";
      }

    }

/**
     * Set's a user's password.
     * 
     * @param int $user_id - User's ID
     * @param string $password - User's desired password
     * 
     * @return string|boolean - Returns an error if there was an issue resetting the password,
     * or returns true if reset successfully.
     * 
     */
    public function setPassword($user_id, $password) {

      // input validation
      if(!is_int($user_id) || !is_string($password)) {
        return "Input validation failed.";
      }

      // check db connection
      if($this->createDatabaseConnection() !== "OK") {
        return "Database connection error.";
      }

      try {
        $result = $this->db_connection->execute_query("SELECT user_email FROM `users` WHERE `user_id` = ?", [$user_id]);
        
        if($result->num_rows < 0) {
          // user not found
          return "Error - user not found.";
        }

        
        $passhash = $this->generatePasswordHash($password);
        $this->db_connection->execute_query("UPDATE `users` SET `user_passwordhash` = ? WHERE `user_id` = ?", [$passhash, $user_id]);
        return true;

      } catch(Exception $e) {
        return "Database query error.";
      }
    }

    private function createDatabaseConnection() {
    // check if database connectoin is already established

    if ($this->db_connection === null) {
      $this->db_connection = new mysqli($this->db_host, $this->db_username, $this->db_password, $this->db_name, $this->db_port);
    }

    // if a connection is already established, continue on to ensure
    // that connection object is successfully connected

    return $this->testDatabaseConnection();
  }

  private function destroyDatabaseConnection()
  {

    // check if database connection is active, close if currently active
    if ($this->db_connection !== null) {
      $this->db_connection->close();
    }

    // set variable to null 
    // TODO - check if variable is exists / is not currently set to null
    $this->db_connection == null;
  }


  /**
   * Tests the connection to the database.
   * 
   * `Database error.` - database has not been initialised
   * 
   * `Connection Error.` - connection has been initialised but an error has occurred.
   * 
   * `OK` - database is initialised, connection is good & is ready to use.
   * 
   * @return string - Returns a string indicating the connection status.
   */
  public function testDatabaseConnection()
  {
    // check if database connection has been established yet
    if ($this->db_connection === null) {
      // this would have caused an infinite loop if ever there was an error creating the database connection
      // $this->createDatabaseConnection();
      return "Database error.";
    }
    // set default placeholder for connection check
    $msg = "Checking connection...";

    if ($this->db_connection->connect_error) {
      $msg = "Connection Error: " . htmlspecialchars($this->db_connection->connect_error, ENT_QUOTES);
    } else {
      $msg = "OK";
    }

    // return connection status
    return $msg;
  }

  /**
   * Check the database is correctly setup.
   * 
   * @return boolean status - returns `true` if database is correctly setup.
   * 
   */
  public function checkSetup()
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return true; // setup has already occurred
    }

    try {
      $this->db_connection->execute_query("SELECT 1 FROM `users` LIMIT 1;");
      return true;
    } catch (mysqli_sql_exception $e) {
      // setup has not occurred, users table does not exist
      // trigger creation of database tables
      $this->runSetup();
      return true;
    }

  }
  /**
   * Generates a password hash for a desired password.
   * 
   * @param string $password Input password to be hashed.
   * 
   * @return string - Returns the password hash 
   * 
   */
  private function generatePasswordHash($password)
  {
    return password_hash($password, PASSWORD_DEFAULT);
  }

  /**
   * Checks an inputted password against a hash to confirm if it matches.
   * 
   * @param string $input - Inputted password to check against hash
   * @param string $hash - Hash to compare password to
   * 
   * @return boolean - Returns `true` if password matches, returns `false` if doesn't match.
   */
  private function checkPassword($input, $hash)
  {
    return password_verify($input, $hash);
  }

  /**
   * Runs setup procedure of creating necessary tables & sets up required relationships.
   * 
   * @return void
   * 
   */
  private function runSetup()
  {
    $sql_setup_commands = ["CREATE TABLE `users` (
            `user_id` integer PRIMARY KEY,
            `user_email` varchar(50) NOT NULL UNIQUE,
            `user_passwordhash` varchar(255) NOT NULL,
            `user_name` varchar(50) NOT NULL,
            `user_isstaff` boolean NOT NULL DEFAULT false,
            `user_isadmin` boolean NOT NULL DEFAULT false
          );",

          "ALTER TABLE `users` CHANGE `user_id` `user_id` INT(11) NOT NULL AUTO_INCREMENT;",
          
          "CREATE TABLE `password_reset_tokens` (
            `token` VARCHAR(255) NOT NULL, 
            `email` VARCHAR(255) NOT NULL, 
            `creation_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expiry_time` TIMESTAMP,
            PRIMARY KEY (`token`(255))
          );",

      "CREATE TABLE `categories` (
        `category_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `category_name` VARCHAR(50) NOT NULL,
        `category_isdisabled` BOOLEAN NOT NULL DEFAULT false,
        `category_image` VARCHAR(255)
      );",

      "CREATE TABLE `products` (
            `product_id` integer AUTO_INCREMENT PRIMARY KEY,
            `category_id` integer NOT NULL,
            `product_name` varchar(75) NOT NULL,
            `product_desc` text NOT NULL,
            `product_price` decimal(6,2) NOT NULL,
            `product_stockcount` integer NOT NULL DEFAULT 0,
            `product_isdisabled` boolean NOT NULL DEFAULT false
          );",

      "CREATE TABLE `reviews` (
            `review_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `review_userid` integer NOT NULL,
            `review_productid` integer NOT NULL,
            `review_rating` integer NOT NULL COMMENT 'Constrain input to only allow 1 to 5 stars',
            `review_text` text NOT NULL,
            `review_approved` boolean NOT NULL DEFAULT false,
          );",

      "ALTER TABLE `reviews` ADD `review_approved` BOOLEAN NOT NULL DEFAULT FALSE;",

      "CREATE TABLE `basket_entries` (
            `basket_entry_id` integer PRIMARY KEY,
            `basket_userid` integer NOT NULL,
            `basket_productid` integer NOT NULL,
            `entry_quantity` integer NOT NULL DEFAULT 1,
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
          "ALTER TABLE `orders` CHANGE `order_id` `order_id` INT(11) NOT NULL AUTO_INCREMENT;",

          "ALTER TABLE `orders` ADD `order_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `order_status`;",
          
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
    if ($this->createDatabaseConnection() == "OK") {
      foreach ($sql_setup_commands as $command) {
        $result = $this->db_connection->execute_query($command);
      }
    }
  }

  /**
   * Creates a user given an email address, desired password & name.
   * Stores the user data in the database.
   * 
   * @param string $email - User's email address
   * @param string $password - User's desired password
   * @param string $name - User's name
   * 
   * @return string - Returns an error if there was an issue creating the user,
   * or returns a confirmation message if created successfully.
   * 
   */
  public function createUser($email, $password, $name)
  {
    if ($this->createDatabaseConnection() == "OK") {
      try {
        $result = $this->db_connection->execute_query("SELECT user_email FROM `users` WHERE `user_email` LIKE ?", [$email]);
        if ($result->num_rows > 0) {
          /**
           * While loop & if statement redundant. Check occurs at database level, so additional checking not required.
           * 
           */
          //while ($row = $result->fetch_assoc() ) {
          //if(strtolower($row["user_email"]) == $email) {
          return "Error - supplied email address already in use.";
          // }
          //}
        } else {
          $passhash = $this->generatePasswordHash($password);
          $this->db_connection->execute_query("INSERT INTO `users` (`user_email`, `user_passwordhash`, `user_name`) VALUES (?,?,?);", [$email, $passhash, $name]);
          return "User account created successfully.";
        }
      } catch (Exception $e) {
        return "An error occurred. Stack trace: " . $e;
      }
    } else {
      return "Database connection error.";
    }
  }

  /**
   * Makes a user a staff member.
   * 
   * @param int $user_id The ID of the user to be updated.
   * @return string Returns a message indicating the success or failure of the operation.
   */
  public function makeUserStaff($user_id)
  {
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $this->db_connection->execute_query("UPDATE `users` SET `user_isstaff` = true WHERE `user_id` = ?;", [$user_id]);
      return "User successfully made a staff member.";
    } catch (Exception $e) {
      return "An error occurred. Stack trace: " . $e->getMessage();
    }
  }

  /**
   * Checks if a user is a staff member.
   * 
   * @param int $user_id The ID of the user to check.
   * @return boolean|string Returns true if the user is a staff member, false if not, or an error message if the operation fails.
   */
  public function isUserStaff($user_id)
  {
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $result = $this->db_connection->execute_query("SELECT `user_isstaff` FROM `users` WHERE `user_id` = ? LIMIT 1;", [$user_id]);
      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool) $row["user_isstaff"];
      } else {
        return "User not found.";
      }
    } catch (Exception $e) {
      return "An error occurred. Stack trace: " . $e->getMessage();
    }
  }

  /**
   * Checks & validates given credentials against database of users
   * 
   * @param string $email - User's inputted email
   * @param string $password - User's inputted password
   * 
   * @return string|int - Returns user's ID if successful, returns error message if unsuccessful.
   * 
   */
  public function checkCredentials($email, $password)
  {

    // check db connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $result = $this->db_connection->execute_query("SELECT * FROM `users` WHERE `user_email` LIKE ?;", [$email]);

      // check if user found in database
      if ($result->num_rows <= 0) {
        return "Incorrect credentials.";
      }

      while ($row = $result->fetch_assoc()) {

        // check if email matches entry, if it doesn't, go to next element in list of results
        if (strtolower($row["user_email"]) !== strtolower($email)) {
          continue;
        }

        if ($this->checkPassword($password, $row["user_passwordhash"])) {
          return $row["user_id"];
        }

      }

      // if no entries match, credentials are wrong, return error.
    } catch (Exception $e) {
      return "Incorrect credentials.";
    }
  }

  public function getJsonOrderContents($order_id) {
    
    // input validation
    if(!is_int($order_id)) {
      return "Error - order ID must be integer";
    }

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT `order_items`.*, `products`.`product_name` FROM `order_items` JOIN `products` ON `order_items`.`product_id` = `products`.`product_id` WHERE `order_id` = ?;";

    try {
      $request = $this->db_connection->execute_query($sql, [$order_id]);

      $order_contents_array = array();

      // check if any line items found
      if(!($request->num_rows > 0)) {
        return "{}";
      }

      while($row = $request->fetch_assoc()) {
        $order_contents_array[] = $row;
      }

      return json_encode($order_contents_array);
 
    } catch(Exception $e) {
      return "Database query error.";
    }

  }

  public function getOrderStatuses() {
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT * FROM `order_status`;";

    try {
      $request = $this->db_connection->execute_query($sql);
      $statuses_array = array();

      while($row = $request->fetch_assoc()) {
        $statuses_array[] = $row;
      }

      return $statuses_array;

    } catch(Exception $e) {
      return "DB query error.";
    }
  }

  public function updateOrder($order_id, $new_order_status, $isRefund) {

    // input validation
    if(!is_int($order_id) && !is_int($new_order_status)) {
      return "Error - parameters must be integers.";
    }

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "UPDATE `orders` SET `order_status`=?, `order_ispaid`=? WHERE `order_id`=?;";

    try {
      $this->db_connection->execute_query($sql, [$new_order_status, !$isRefund, $order_id]);
      return true;
    } catch(Exception $e) {
      return "DB query error.";
    }

    
  }

  public function changePassword($user_id, $new_pass) {

  // input validation
  if(!is_int($user_id) || !is_string($new_pass)) {
    return "Input validation failed";
  }

  // check db connection
  if($this->createDatabaseConnection() !== "OK") {
    return "Error - database connection error.";
  }

  $password_hash = $this->generatePasswordHash($new_pass);
  $sql = "UPDATE `users` SET `user_passwordhash`=? WHERE `user_id`=?;";

  try {
    $this->db_connection->execute_query($sql, [$password_hash, $user_id]);
  } catch(Exception $e) {
    return "DB query error.";
  }

  }

  public function updateUserDetails($user_id, $new_name, $new_email) {

    // input validation
    if(!is_int($user_id) || !is_string($new_name) || !is_string($new_email)) {
      return "Input validation failed";
    }

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "UPDATE `users` SET `user_email`=?,`user_name`=? WHERE `user_id`=?;";

    try {
      $this->db_connection->execute_query($sql, [$new_email, $new_name, $user_id]);
      return true;
    } catch(Exception $e) {
      return "DB Query error.";
    }

  }

  public function getAllUsers() {

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT `user_id`,`user_name`,`user_email`,`user_isstaff`,`user_isadmin` FROM `users`;";

    try {

      $request = $this->db_connection->execute_query($sql);
      $users_array = array();
      if($request->num_rows < 1) {
        return $users_array;
      }

      while($row = $request->fetch_assoc()) {
        $users_array[] = $row;
      }

      return $users_array;

    } catch(Exception $e) {
      return "DB query error.";
    }
    


  }


  public function getUserDetails($user_id) {

    // input validation
    if(!is_int($user_id)) {
      return "Input validation failed.";
    }

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT * FROM `users` WHERE `user_id` = ? LIMIT 1;";

    try {

      $request = $this->db_connection->execute_query($sql, [$user_id]);

      if($request->num_rows < 1) {
        return "No results found.";
      }

      while($row = $request->fetch_assoc()) {
        return $row;
      }

    } catch(Exception $e) {
      return "DB query error.";
    }
    


  }


  public function getOrdersByUser($user_id) {

    // input validation
    if(!is_int($user_id)) {
      return "Input validation failed.";
    }

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT `orders`.`order_id`, `orders`.`order_address`, `orders`.`order_comments`, `orders`.`order_total`, `orders`.`order_ispaid`, `orders`.`order_creation`, `order_status`.`status_id`, `order_status`.`status_name`, `order_status`.`status_colour`, `users`.`user_id`, `users`.`user_name`,`users`.`user_email` FROM `orders` JOIN `users` ON `orders`.`order_userid` = `users`.`user_id` JOIN `order_status` ON `orders`.`order_status` = `order_status`.`status_id` WHERE `orders`.`order_userid` = ? ORDER BY `orders`.`order_id` ASC;";
    
    try {
      $request = $this->db_connection->execute_query($sql,[$user_id]);
      $orders_array = array();

      // check if any orders found, return blank array if none found
      if(!($request->num_rows > 0)) {
        return $orders_array;
      }

      while($row = $request->fetch_assoc()) {
        $orders_array[] = $row;
      }

      return $orders_array;
    } catch(Exception $e) {
      return "Database query error.";
    }

  }

  public function getAllOrders() {

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT `orders`.`order_id`, `orders`.`order_address`, `orders`.`order_comments`, `orders`.`order_total`, `orders`.`order_ispaid`, `orders`.`order_creation`, `order_status`.`status_id`, `order_status`.`status_name`, `order_status`.`status_colour`, `users`.`user_id`, `users`.`user_name`,`users`.`user_email` FROM `orders` JOIN `users` ON `orders`.`order_userid` = `users`.`user_id` JOIN `order_status` ON `orders`.`order_status` = `order_status`.`status_id` ORDER BY `orders`.`order_id` ASC;";
    
    try {
      $request = $this->db_connection->execute_query($sql);
      $orders_array = array();

      // check if any orders found, return blank array if none found
      if(!($request->num_rows > 0)) {
        return $orders_array;
      }

      while($row = $request->fetch_assoc()) {
        $orders_array[] = $row;
      }

      return $orders_array;
    } catch(Exception $e) {
      return "Database query error.";
    }

  }

  public function getRecentOrders($maxDaysAgo) {

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT `orders`.`order_id`, `orders`.`order_address`, `orders`.`order_comments`, `orders`.`order_total`, `orders`.`order_ispaid`, `orders`.`order_creation`, `order_status`.`status_id`, `order_status`.`status_name`, `order_status`.`status_colour`, `users`.`user_id`, `users`.`user_name`,`users`.`user_email` FROM `orders` JOIN `users` ON `orders`.`order_userid` = `users`.`user_id` JOIN `order_status` ON `orders`.`order_status` = `order_status`.`status_id` WHERE `orders`.`order_creation` > CURRENT_DATE - ? ORDER BY `orders`.`order_id` ASC;";
    
    try {
      $request = $this->db_connection->execute_query($sql, [$maxDaysAgo]);
      $orders_array = array();

      // check if any orders found, return blank array if none found
      if(!($request->num_rows > 0)) {
        return $orders_array;
      }

      while($row = $request->fetch_assoc()) {
        $orders_array[] = $row;
      }

      return $orders_array;
    } catch(Exception $e) {
      return "Database query error.";
    }

  }

  public function getRecentRefundedOrders($maxDaysAgo) {

    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $sql = "SELECT `orders`.`order_id`, `orders`.`order_address`, `orders`.`order_comments`, `orders`.`order_total`, `orders`.`order_ispaid`, `orders`.`order_creation`, `order_status`.`status_id`, `order_status`.`status_name`, `order_status`.`status_colour`, `users`.`user_id`, `users`.`user_name`,`users`.`user_email` FROM `orders` JOIN `users` ON `orders`.`order_userid` = `users`.`user_id` JOIN `order_status` ON `orders`.`order_status` = `order_status`.`status_id` WHERE `orders`.`order_creation` > CURRENT_DATE - ? AND `order_status` = 6 AND `order_ispaid` = FALSE ORDER BY `orders`.`order_id` ASC;";
    
    try {
      $request = $this->db_connection->execute_query($sql, [$maxDaysAgo]);
      $orders_array = array();

      // check if any orders found, return blank array if none found
      if(!($request->num_rows > 0)) {
        return $orders_array;
      }

      while($row = $request->fetch_assoc()) {
        $orders_array[] =  $row;
      }

      return $orders_array;
    } catch(Exception $e) {
      return "Database query error.";
    }

  }

  public function getRevenueStats() {
    // check db connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    $getRevenueSQL = "SELECT SUM(`order_total`) AS 'revenue' FROM `orders` WHERE `order_status` != 6 AND `order_ispaid` = true;";
    $getRefundsSQL = "SELECT SUM(`order_total`) AS 'refunds' FROM `orders` WHERE `order_status` = 6 AND `order_ispaid` = false;";

    $output = array();

    try {

      $revenueResult = $this->db_connection->execute_query($getRevenueSQL);
      if($revenueResult->num_rows > 0) {
        while($row = $revenueResult->fetch_assoc()) {
          $output["revenue"] = $row["revenue"];
        }
      }

      $refundResult = $this->db_connection->execute_query($getRefundsSQL);
      if($refundResult->num_rows > 0) {
        while($row = $refundResult->fetch_assoc()) {
          $output["refunds"] = $row["refunds"];
        }
      }

      if(!is_string($output["revenue"])) {
        $output["revenue"] = 0.00;
      }
      
      if(!is_string($output["refunds"])) {
        $output["refunds"] = 0.00;
      }

      return $output;
    } catch(Exception $e) {
      return "DB query error.";
    }

  }

  public function getRandomProducts($limit = 6)
  {
    $sql = "SELECT * FROM products ORDER BY RAND() LIMIT ?";
    $stmt = $this->db_connection->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
      $products[] = $row;
    }
    return $products;
  }

  public function getAllReviews()
  {
    $reviews = [];
    if ($this->createDatabaseConnection() == "OK") {
      try {
        $sql = "SELECT reviews.*, products.product_name, users.user_name 
                    FROM `reviews` 
                    JOIN `products` ON reviews.review_productid = products.product_id 
                    JOIN `users` ON reviews.review_userid = users.user_id
                    ORDER BY reviews.review_id DESC";
        $result = $this->db_connection->query($sql);
        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
          }
        }
        return $reviews;
      } catch (Exception $e) {
        return "An error occurred: " . $e->getMessage();
      }
    } else {
      return "Database connection error.";
    }
  }


  public function getAllProducts($includeDisabledProducts)
  {
    // Check if the database connection is successfully established
    if (!$this->createDatabaseConnection()) {
      throw new Exception("Database connection failed.");
    }

    // Array to hold results
    $output = [];

    try {
      // Query the products and whether disabled products should be included
      $query = $includeDisabledProducts
        ? "SELECT * FROM `products`;"
        : "SELECT * FROM `products` WHERE `product_isdisabled` = FALSE;";

      // Execute Query and store result
      $result = $this->db_connection->execute_query($query);

      // Check if there was any results
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          // Add current row to array
          $output[] = $row;
        }
      }

    } catch (Exception $e) {
      throw new Exception("Stack trace: " . $e->getMessage());
    }

    // Return Array
    return $output;
  }


  public function getAllCategories($includeDisabledCategories)
  {

    // check db connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }


    if ($this->createDatabaseConnection() == "OK") {

      $output = array();

      try {
        if ($includeDisabledCategories) {
          $result = $this->db_connection->execute_query("SELECT * FROM `categories`;");
        } else {
          $result = $this->db_connection->execute_query("SELECT * FROM `categories` WHERE `category_isdisabled` = FALSE;");
        }

        while ($row = $result->fetch_assoc()) {
          if ($result->num_rows > 0) {

            // Refactored the below. Copied the resulting $row from the db,
            // rather than iterating through each key, making a temp array and then appending temp array.

            /*
            $category = array();
            $category["category_id"] = $row["category_id"];
            $category["category_name"] = $row["category_name"];
            $category["category_isdisabled"] = $row["category_isdisabled"];
            $category["category_image"] = $row["category_image"];
            */

            $output[] = $row;

          } else {
            break;
          }
        }


      } catch (Exception $e) {
        return "An error occurred. Stack trace: " . $e;
      }

      return $output;
    }
  }

  public function getReviewsByProductID($product_id)
  {
    $reviews = [];
    if ($this->createDatabaseConnection() == "OK") {
      try {

        $stmt = $this->db_connection->prepare("SELECT * FROM `reviews` WHERE `review_productid` = ? AND `review_approved` = TRUE");

        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
          $reviews[] = $row;
        }
        return $reviews;
      } catch (Exception $e) {
        return "An error occurred: " . $e->getMessage();
      }
    } else {
      return "Database connection error.";
    }
  }


  /**
   * Makes a user an admin.
   * 
   * @param int $user_id The ID of the user to be updated.
   * @return string Returns a message indicating the success or failure of the operation.
   */
  public function makeUserAdmin($user_id)
  {
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $this->db_connection->execute_query("UPDATE `users` SET `user_isstaff` = true, `user_isadmin` = true WHERE `user_id` = ?;", [$user_id]);
      return "User successfully made an admin.";
    } catch (Exception $e) {
      return "An error occurred. Stack trace: " . $e->getMessage();
    }
  }


  /**
   * Demotes a staff member to a normal user.
   * 
   * @param int $user_id The ID of the user to be updated.
   * @return string Returns a message indicating the success or failure of the operation.
   */
  public function makeStaffUser($user_id)
  {
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $this->db_connection->execute_query("UPDATE `users` SET `user_isstaff` = false WHERE `user_id` = ?;", [$user_id]);
      return "User successfully demoted.";
    } catch (Exception $e) {
      return "An error occurred. Stack trace: " . $e->getMessage();
    }
  }

  /**
   * Demotes an admin to a staff nmember.
   * 
   * @param int $user_id The ID of the user to be updated.
   * @return string Returns a message indicating the success or failure of the operation.
   */
  public function makeAdminStaff($user_id)
  {
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $this->db_connection->execute_query("UPDATE `users` SET `user_isadmin` = false WHERE `user_id` = ?;", [$user_id]);
      return "User successfully demoted.";
    } catch (Exception $e) {
      return "An error occurred. Stack trace: " . $e->getMessage();
    }
  }

  
/**
   * Checks if a user is an admin.
   * 
   * @param int $user_id The ID of the user to check.
   * @return boolean|string Returns true if the user is an admin, false if not, or an error message if the operation fails.
   */
  public function isUserAdmin($user_id)
  {
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $result = $this->db_connection->execute_query("SELECT `user_isadmin` FROM `users` WHERE `user_id` = ? LIMIT 1;", [$user_id]);
      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (bool) $row["user_isadmin"];
      } else {
        return "User not found.";
      }
    } catch (Exception $e) {
      return "An error occurred. Stack trace: " . $e->getMessage();
    }
  }

  public function getProductPriceById($productId)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $sql = "SELECT product_price FROM products WHERE product_id = ?";
      $stmt = $this->db_connection->prepare($sql);
      $stmt->bind_param("i", $productId);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['product_price'];
      } else {
        return "No product found with ID: " . $productId;
      }
    } catch (Exception $e) {
      return "Error - " . $e->getMessage();
    }
  }

  public function getProductsByName($inputted_name)
  {
    if (is_string($inputted_name)) {
      if ($this->createDatabaseConnection() == "OK") {

        $output = array();

        try {
          $result = $this->db_connection->execute_query("SELECT * FROM `products` WHERE `product_name` = '%?%' ORDER BY `product_name`;", [$inputted_name]);

          while ($row = $result->fetch_assoc()) {
            if ($result->num_rows > 0) {
              $product = array();
              $product["product_id"] = $row["product_id"];
              $product["category_id"] = $row["category_id"];
              $product["product_name"] = $row["product_name"];
              $product["product_desc"] = $row["product_desc"];
              $product["product_price"] = $row["product_price"];
              $product["product_stockcount"] = $row["product_stockcount"];
              $product["product_isdisabled"] = $row["product_isdisabled"];

              $output[] = $product;

            } else {
              return "Error - No results found.";
            }
          }

        } catch (Exception $e) {
          return "An error occurred. Stack trace: " . $e;
        }

      }
    } else {
      return "Error - input must be a string";
    }
  }

  
  public function getCategoryNameByProductId($productId)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $sql = "SELECT c.category_name FROM categories c JOIN products p ON c.category_id = p.category_id WHERE p.product_id = ?";
      $stmt = $this->db_connection->prepare($sql);
      $stmt->bind_param("i", $productId);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['category_name'];
      } else {
        return "No category found for product ID: " . $productId;
      }
    } catch (Exception $e) {
      return "Error - " . $e->getMessage();
    }
  }

  public function getCategoryIdByName($categoryName)
  {
    // Ensure the database connection is successfully established
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $sql = "SELECT category_id FROM categories WHERE category_name = ? LIMIT 1;";
      $stmt = $this->db_connection->prepare($sql);

      $stmt->bind_param("s", $categoryName);
      $stmt->execute();

      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['category_id'];
      } else {
        return "null";
      }
    } catch (Exception $e) {
      return "Error - " . $e->getMessage();
    }
  }

  public function getCategoryById($categoryID)
  {
    // Ensure the database connection is successfully established
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    try {
      $sql = "SELECT * FROM categories WHERE category_id = ? LIMIT 1;";
      $stmt = $this->db_connection->prepare($sql);

      $stmt->bind_param("i", $categoryID);
      $stmt->execute();

      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row;
      } else {
        return "null";
      }
    } catch (Exception $e) {
      return "Error - " . $e->getMessage();
    }
  }



    /**
     * Get an array of products belonging to a particular category from Category ID.
     * @author H. Green (2024)
     * @param $category_id Category ID
     * @return null|string|array
     * Returns null if no products found.
     * Returns string (error message) if something went wrong
     * Returns array of products if successful.
     */
    public function getProductsByCategoryID($category_id) {

      // input validation
      if(!is_int($category_id)) {
        return "Error - Category ID must be an int.";
      }
      // check db connection
      if($this->createDatabaseConnection() !== "OK") {
        return "Error - Database connection error.    ";
      }


      try {
        $result = $this->db_connection->execute_query("SELECT * FROM `products` WHERE `category_id` = ?;", [$category_id]);
           
        if($result->num_rows <= 0) {
          // no results found, category contains no products, return blank array
          return array();
        }

        $products_array = array();

        while($row = $result->fetch_assoc() ) {

          // Refactored this mess again.
          /*
          $product = array();
          $product["product_id"] = $row["product_id"];
          $product["category_id"] = $row["category_id"];
          $product["product_name"] = $row["product_name"];
          $product["product_desc"] = $row["product_desc"];
          $product["product_price"] = $row["product_price"];
          $product["product_stockcount"] = $row["product_stockcount"];
          $product["product_isdisabled"] = $row["product_isdisabled"];
          $output[] = $output + $product;
  
          */

          $products_array[] = $row;
        
        }

        return $products_array;
      } catch(Exception $e) {
        return $this->ERROR_MSG_DB_QUERY_EXCEPTION;
      }
    }

    public function editUser($userId, $newName, $newEmail, $newPassword) {
      if ($this->createDatabaseConnection() !== "OK") {
        return "Error - database connection error.";
      }
  
      $password_hash = $this->generatePasswordHash($newPassword);

      try {
        $query = "UPDATE `users` SET `user_name` = ?, `user_email` = ?, `user_passwordhash`=? WHERE `user_id` = ?";
        $this->db_connection->execute_query($query, [$newName, $newEmail, $password_hash, $userId]);
        return "User updated successfully.";
      } catch (Exception $e) {
        return "Error - database query error: " . $e->getMessage();
      }
    }

  public function createCategory($name, $is_disabled, $image_path)
  {

    // check database connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }
    // attempt to insert new category info into database
    try {
      $this->db_connection->execute_query("INSERT INTO `categories` (`category_name`, `category_isdisabled`, `category_image`) VALUES (?,?,?);", [$name, $is_disabled, $image_path]);
      return "Category created successfully.";
    } catch (Exception $e) {
      return $e;
    }
  }

    public function getLowStockReport($below_amount) {
      if ($this->createDatabaseConnection() !== "OK") {
        return "Error - database connection error.";
      }

      if (!is_int($below_amount)) {
        return "input validation failed";
      }

      try {
        $result = $this->db_connection->execute_query("SELECT * FROM `products` WHERE `product_stockcount` < ?;", [$below_amount]);
        $products_array = array();

        if($result->num_rows > 0) {
          while($row = $result->fetch_assoc()) {
            $products_array[] = $row;
          }
        }

        return $products_array;

      } catch(Exception $e) {
        return "DB query error.";
      }

    }

    public function getHighStockReport($below_amount) {
      if ($this->createDatabaseConnection() !== "OK") {
        return "Error - database connection error.";
      }

      if (!is_int($below_amount)) {
        return "input validation failed";
      }

      try {
        $result = $this->db_connection->execute_query("SELECT * FROM `products` WHERE `product_stockcount` >= ?;", [$below_amount]);
        $products_array = array();

        if($result->num_rows > 0) {
          while($row = $result->fetch_assoc()) {
            $products_array[] = $row;
          }
        }

        return $products_array;

      } catch(Exception $e) {
        return "DB query error.";
      }

    }

  public function editCategory($category_id, $new_name, $new_image_path)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $query = "UPDATE `categories` SET `category_name` = ?, `category_image` = ? WHERE `category_id` = ?";
      $this->db_connection->execute_query($query, [$new_name, $new_image_path, $category_id]);
      return "Category updated successfully.";
    } catch (Exception $e) {
      return "Error - database query error: " . $e->getMessage();
    }
  }

  public function editProduct($product_id, $new_name, $new_desc, $new_price, $new_stock, $new_category_id)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $query = "UPDATE `products` SET `category_id`=?,`product_name`=?,`product_desc`=?,`product_price`=?,`product_stockcount`=? WHERE `product_id` = ?";
      $this->db_connection->execute_query($query, [$new_category_id, $new_name, $new_desc, $new_price, $new_stock, $product_id]);
      return "Product updated successfully.";
    } catch (Exception $e) {
      return "Error - database query error: " . $e->getMessage();
    }
  }

  public function toggleCategoryStatus($categoryId, $isDisabled)
  {
    // SQL to toggle the status
    $sql = "UPDATE categories SET category_isdisabled = ? WHERE category_id = ?";
    $stmt = $this->db_connection->prepare($sql);
    $stmt->bind_param("ii", $isDisabled, $categoryId);
    if ($stmt->execute()) {
      return true;
    } else {
      return false;
    }
  }

  public function toggleProductStatus($productId, $isDisabled)
  {
    // SQL to toggle the status
    $sql = "UPDATE products SET product_isdisabled = ? WHERE product_id = ?";
    $stmt = $this->db_connection->prepare($sql);
    $stmt->bind_param("ii", $isDisabled, $productId);
    if ($stmt->execute()) {
      return true;
    } else {
      return false;
    }
  }

  public function deleteCategory($category_id)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $query = "DELETE FROM `categories` WHERE `category_id` = ?";
      $this->db_connection->execute_query($query, [$category_id]);

      return "Category deleted successfully.";
    } catch (Exception $e) {
      return "Error - database query error: " . $e->getMessage();
    }
  }

  public function deleteUser($user_id)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $query = "DELETE FROM `users` WHERE `user_id` = ?";
      $this->db_connection->execute_query($query, [$user_id]);

      return "User deleted successfully.";
    } catch (Exception $e) {
      return "Error - database query error: " . $e->getMessage();
    }
  }

  public function deleteProduct($product_id)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $query = "DELETE FROM `products` WHERE `product_id` = ?";
      $this->db_connection->execute_query($query, [$product_id]);

      return "Product deleted successfully.";
    } catch (Exception $e) {
      return "Error - database query error: " . $e->getMessage();
    }
  }

  public function categoryExists($name)
  {
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $query = "SELECT 1 FROM `categories` WHERE `category_name` = ? LIMIT 1;";
      $execution = $this->db_connection->execute_query($query, [$name]);
      $exists = $execution->num_rows > 0;
      $execution->free_result();

      return $exists;
    } catch (Exception $e) {
      return false;
    }
  }

  public function createProduct($name, $category_id, $desc, $price, $stockcount, $is_disabled)
  {

    // validate function input
    if (is_string($name) && is_int($category_id) && is_string($desc) && !is_nan($price) && is_int($stockcount) && is_bool($is_disabled)) {
      return "Error - input validation failed";
    }

    // check database connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    // validate category ID, and attempt to add product
    try {
      $result = $this->db_connection->execute_query("SELECT `category_id` FROM `categories` WHERE `category_id` LIKE ?", [$category_id]);
      // check if supplied category ID found in DB
      if ($result->num_rows <= 0) {
        return "Category ID invalid.";
      }

      $this->db_connection->execute_query("INSERT INTO `products` (`product_name`, `category_id`, `product_desc`, `product_price`, `product_stockcount`, `product_isdisabled`) VALUES (?,?,?,?,?,?);", [$name, $category_id, $desc, $price, $stockcount, $is_disabled]);
      return "Product created successfully.";


    } catch (Exception $e) {
      return "Error - database query error.";
    }
  }

  public function createReview($userId, $productId, $rating, $reviewText)
  {
    if ($this->createDatabaseConnection() == "OK") {
      try {
        $stmt = $this->db_connection->prepare("INSERT INTO reviews (review_userid, review_productid, review_rating, review_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $userId, $productId, $rating, $reviewText);
        $stmt->execute();
        return true;
      } catch (Exception $e) {
        return false;
      }
    }
    return false;
  }

  public function getEmailFromUserID($id)
  {

    // check if supplied user ID is an integer
    if (!is_int($id)) {
      return "Error - ID must be an integer";
    }

    // check db connection is active, error if not
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection failure.";
    }

    try {
      // query the users table for the supplied user ID, limiting results to 1 entry to prevent errors
      $result = $this->db_connection->execute_query(
        "SELECT `user_email` FROM `users` WHERE `user_id` = ? LIMIT 1;",
        [$id]
      );

      // check if the db returned at least 1 user entry
      if ($result->num_rows <= 0) {
        return "Error - user ID not found.";
      }

      // loop through returned data from db
      while ($row = $result->fetch_assoc()) {
        // return user's email address from queried user ID
        return $row["user_email"];
      }

    } catch (Exception $e) {
      return "Error - database query failure.";
    }
  }

  public function getUserIDFromEmail($email) {
    // check if supplied user email is a string
    if(!is_string($email)) {
      return "Error - email must be a string";
    }

    // check db connection is active, error if not
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection failure.";
    }

    try {
      $result = $this->db_connection->execute_query(
        "SELECT `user_id` FROM `users` WHERE `user_email` LIKE ? LIMIT 1;", [$email]
      );
    
      // check if the db returned at least 1 user entry
      if($result->num_rows <= 0) {
        return "Error - user ID not found.";
      }

      // loop through returned data from db
      while($row = $result->fetch_assoc()) {
        // return user's email address from queried user ID
        return $row["user_id"];
      }

    } catch(Exception $e) {
      return "Error - database query failure.";
    }

    return "Error - unexpected error occurred.";

  }



  /** 
   * Get a user's name from a given user ID
   * 
   * @param int $id - User ID
   * 
   * @return string Returns the user's name or an error message.
   * 
   */
  public function getNameFromUserID($id)
  {

    // input validation
    if (!is_int($id)) {
      return "Error - ID must be an integer.";
    }

    // check db connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error";
    }

    try {
      $result = $this->db_connection->execute_query("SELECT `user_name` FROM `users` WHERE `user_id` = ? LIMIT 1;", [$id]);

      // check if user found in db
      if ($result->num_rows <= 0) {
        return "Error - no results found";
      }

      // loop through db results
      while ($row = $result->fetch_assoc()) {
        return $row["user_name"];
      }

      // something has gone very wrong if this is returned.
      return "Error - name not found.";
    } catch (Exception $e) {
      return "Error - database query error.";
    }


  }

  public function addToBasket($user_id, $product_id, $qty, $subtotal)
  {

    // input validation - check if supplied user ID is an integer
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    // input validation - check if supplied product ID is an integer
    if (!is_int($product_id)) {
      return "Error - Product ID must be an integer.";
    }

    // input validation - check if supplied quantity is an integer
    if (!is_int($qty)) {
      return "Error - Quantity must be an integer.";
    }

    // TODO - validate subtotal input     

    // check connection to database
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    // attempt to add item to basket
    try {
      $this->db_connection->execute_query(
        "INSERT INTO `basket_entries` (`basket_userid`, `basket_productid`, `entry_quantity`, `entry_subtotal`) VALUES (?,?,?,?);", [$user_id, $product_id, $qty, $subtotal]
      );

      // output success message
      return "Added to cart.";
    } catch (Exception $e) {
      // catch any errors outputted whilst executing the query
      return "Error - database query error.";
    }
  }

  /**
   * Removes an entry from a basket.
   * 
   * @param int $entry_id - basket entry ID to remove
   * @param int $user_id - User ID to remove from
   * 
   * @return string Returns a message to indicate success or failure.
   * 
   */
  public function removeFromBasket($entry_id, $user_id)
  {

    // input validation
    if (!is_int($user_id) || !is_int($entry_id)) {
      return "Error - User ID & Basket Entry ID must be integers.";
    }

    // check db connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error";
    }

    try {
      // remove item entry ID from basket
      $this->db_connection->execute_query("DELETE FROM basket_entries WHERE `basket_entry_id` = ? AND basket_userid = ?", [$entry_id, $user_id]);
      return "Removed from cart.";
    } catch (Exception $e) {
      return "Error - database query error.";
    }

  }

  /**
   * Submits an order for a user
   * 
   * @param int $user_id - ID of user placing order
   * @param string $address - Address lines concatenated into 1 string
   * @param string $comments - Any comments added to the order
   * @param int $total - Total order amount
   * @param boolean $is_paid - Has the order been paid?
   * 
   * @return string - Returns a status message depending on if an error occurred
   * or if the order was successful.
   * 
   */
  public function submitOrder($user_id, $address, $comments, $total, $is_paid)
  {

    // input validation
    if (!is_int($user_id)) {
      return "Error - user ID must be an integer.";
    }

    // check db connection
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {

      $result = $this->db_connection->execute_query("INSERT INTO `orders` (`order_userid`, `order_address`, `order_comments`, `order_total`, `order_ispaid`, `order_status`) VALUES (?, ?, ?, ?, ?, '1');", [$user_id, $address, $comments, $total, $is_paid]);

      // check order submitted successfully
      if ($result !== TRUE) {
        return "Error - an error occurred creating the order.";
      }

      // get order number from previous query
      $order_no = $this->db_connection->insert_id;

      $basket_contents = $this->getBasketContents(intval($user_id));

      // loop through each entry in the basket
      foreach ($basket_contents as $item) {

        $product_id = $item["basket_productid"];
        $qty = $item["entry_quantity"];
        $subtotal = $item["entry_subtotal"];

        // add item line to database and link to order ID
        $result = $this->db_connection->execute_query("INSERT INTO `order_items` (`order_id`, `product_id`, `line_quantity`, `line_subtotal`) VALUES (?, ?, ?, ?)", [$order_no, $product_id, $qty, $subtotal]);
        // remove item from basket
        $this->removeFromBasket(intval($item["basket_entry_id"]), $user_id);

        

        // update stock
        $new_stock_level = intval($this->getStockLevelOfItem($product_id) - $qty);
        $this->setStockLevelOfItem($product_id, $new_stock_level);

        // check if low/out of stock notification required
        if($new_stock_level >= 6 && $new_stock_level !== 0) {
          $this->notifyLowStock($product_id);
        } else if($new_stock_level == 0) {
          $this->notifyOutOfStock($product_id);
        }

      }

      return "Order submitted successfully.";

    } catch (Exception $e) {
      return "Error - database query error.";
    }

  }

  private function notifyLowStock($product_id) {
    $admins_list = $this->getAdminsList();

    $message = "Hi there,\n
    An item is now low on stock.\n
    \n
    Product ID: " . htmlspecialchars($product_id, ENT_QUOTES) . "\n
    \n
    Kind regards,\n
    SpaceTech.";

    foreach($admins_list as $admin) {
      mail($admin["user_email"], "SpaceTech - Stock Notification", $message);
    }
  }

  private function notifyOutOfStock($product_id) {

    $admins_list = $this->getAdminsList();

    $message = "Hi there,\n
    An item is now out of stock.\n
    \n
    Product ID: " . htmlspecialchars($product_id, ENT_QUOTES) . "\n
    \n
    Kind regards,\n
    SpaceTech.";

    foreach($admins_list as $admin) {
      mail($admin["user_email"], "SpaceTech - Stock Notification", $message);
          }
  }

  /**
   * Gets count of amount of basket entries a user has.
   * 
   * @param int $user_id - User ID
   * 
   * @return int|string - Returns count of items if successful, returns error if unsuccessful.
   * 
   */
  public function getBasketCount($user_id)
  {

    // input validation - check if supplied user ID is an integer
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    // check connection to database
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    // attempt to query database for basket entries count 
    // (not including quantities, this only outputs the number of unique entries in the basket)

    try {
      $result = $this->db_connection->execute_query("SELECT COUNT(`basket_userid`) FROM `basket_entries` WHERE `basket_userid` = ?;", [$user_id]);

      // check if basket is empty, if so, return a count of 0
      if ($result->num_rows <= 0) {
        return 0;
      }

      // loop through data returned from db
      while ($row = $result->fetch_assoc()) {
        // return count of basket items
        return $row["COUNT(`basket_userid`)"];
      }

      // if this is returned, something has gone very wrong somewhere...
      return "Error - database query error.";

    } catch (Exception $e) {
      // something went wrong when executing the database query
      return "Error - database query error.";
    }

  }

  public function getBasketTotal($user_id)
  {

    // input validation - check if supplied user ID is an integer
    if (!is_int($user_id)) {
      return "Error - User ID must be an integer.";
    }

    // check connection to database
    if ($this->createDatabaseConnection() !== "OK") {
      return "Error - Database connection error.";
    }

    // atempt to obtain sum of subtotals in user's basket
    try {
      $result = $this->db_connection->execute_query("SELECT SUM(`entry_subtotal`) FROM `basket_entries` WHERE `basket_userid` = ?;", [$user_id]);

      // if user's basket is empty, return a total of £0.00
      if ($result->num_rows <= 0) {
        return 0.00;
      }

      // loop through returned rows from DB
      while ($row = $result->fetch_assoc()) {
        // return sum of all basket entry subtotals, giving the total of the basket contents
        return $row["SUM(`entry_subtotal`)"];
      }

    } catch (Exception $e) {
      return "Error - database query error.";
    }

  }

  public function getBasketContents($user_id)
  {
    if (is_int($user_id)) {
      if ($this->createDatabaseConnection() == "OK") {
        try {
          $result = $this->db_connection->execute_query("SELECT * FROM `basket_entries` WHERE `basket_userid` = ?;", [$user_id]);

          $basket = array();

          while ($row = $result->fetch_assoc()) {
            if ($result->num_rows > 0) {

              $basket[] = $row;

            }
          }

          return $basket;

        } catch (Exception $e) {
          return "An error occurred. Stack trace - " . $e;
        }
      } else {
        return "An error occurred.";
      }
    } else {
      return "Error - user ID must be an int.";
    }
  }

  public function getProductByID($id)
  {
    // Check if the provided ID is an integer
    if (is_int($id)) {
      // Attempt to create a database connection
      if ($this->createDatabaseConnection() == "OK") {
        try {
          // Execute a query to fetch a product by its ID
          $result = $this->db_connection->execute_query("SELECT * FROM `products` WHERE `product_id` = ? LIMIT 1;", [$id]);

          // Check if any row is returned
          if ($result->num_rows > 0) {
            // Fetch the array from the result set
            $row = $result->fetch_assoc();

            // Return the fetched row directly
            return $row;
          } else {
            // Return an error message if no product is found with the given ID
            return "Error - No results found.";
          }

        } catch (Exception $e) {
          // Return a detailed error message in case of an exception
          return "An error occurred. Stack trace: " . $e->getMessage();
        }

      } else {
        // Return an error message if database connection fails
        return "Database connection failed.";
      }
    } else {
      // Return an error message if the provided ID is not an integer
      return "Error - ID must be an integer";
    }
  }

  /**
   * Check if a product is currently in stock.
   * 
   * @return int|string Returns the stock level of the specified product ID.
   * Returns a string if an error occurred.
   */
  public function getStockLevelOfItem($product_id) {

    // input validation
    if(!is_int($product_id)) {
      return "Error - product ID not an integer.";
    }

    // check database connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $result = $this->db_connection->execute_query("SELECT `product_stockcount` FROM `products` WHERE `product_id` = ?;", [$product_id]);

      if($result->num_rows <= 0) {
        // product not found
        return "Error - product not found.";
      }

      while($row = $result->fetch_assoc()) {
        return intval($row["product_stockcount"]);
      }

      return "Error - something unexpected happened.";

    } catch(Exception $e) {
      return "Error - database query error.";
    }

  }

  /**
   * Check if a product is currently in stock.
   * 
   * @param $product_id Product ID
   * @param $new_stock_level Desired new stock level
   * 
   * @return int|string Returns new stock level of the specified product ID.
   * Returns a string if an error occurred.
   */
  public function setStockLevelOfItem($product_id, $new_stock_level) {

    // input validation
    if(!is_int($product_id)) {
      return "Error - product ID not an integer.";
    }

    // check database connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }

    try {
      $this->db_connection->execute_query("UPDATE `products` SET `product_stockcount` = ? WHERE `product_id` = ?;", [$new_stock_level, $product_id]);
      return $this->getStockLevelOfItem($product_id);

    } catch(Exception $e) {
      return "Error - database query error.";
    }

  }

  public function storeAndSendPassResetToken($email) {

    if(!is_string($email)) {
      // input validation failed
      return false;
    }

    // check database connection
    if($this->createDatabaseConnection() !== "OK") {
      return "Error - database connection error.";
    }
    

    $hash_length = 16; // sets length for bytes used for reset token
    $reset_token = bin2hex(random_bytes($hash_length)); // url safe output 
    $current_time = strtotime(time());
    $expiry_time = strtotime(time() + 3600); // expires 1hr after creation

    try {
      $request = $this->db_connection->execute_query("INSERT INTO `password_reset_tokens` (`token`, `email`, `expiry_time`) VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR));",[$reset_token, $email]);
      $this->sendPassResetEmailToUser($email, $reset_token);
      return true;
    } catch(Exception $e) {
      return false;
    }
    

  }
    
    private function sendPassResetEmailToUser($email, $reset_token) {
      
      $message = "Hi there,\n
      You have requested to reset your password. Please click the link <a href='/reset.php?token=". $reset_token . "'>here</a> to reset your password.\n
      Kind regards,\n
      SpaceTech.";

      mail($email, "SpaceTech Password Reset", $message);

      return true;
    } 

    public function getAdminsList() {

      if($this->createDatabaseConnection() !== "OK") {
        return "Database connection error.";
      }

      try {
        $result = $this->db_connection->execute_query("SELECT * FROM `users` WHERE `user_isadmin` = 1;");
        
        $admins_list = array();

        // no admins found - return blank array
        if($result->num_rows <= 0) {
          return $admins_list;
        }

        while($row = $result->fetch_assoc()) {
          $admins_list[] = $row;
        }

        return $admins_list;

      } catch(Exception $e) {
        return "Database query error.";
      }

    }

    
    public function approveReview($review_id)

  {
    if ($this->createDatabaseConnection() == "OK") {
      try {
        $stmt = $this->db_connection->prepare("UPDATE `reviews` SET `review_approved` = TRUE WHERE `review_id` = ?");
        $stmt->bind_param("i", $review_id);
        $success = $stmt->execute();

        if ($success && $stmt->affected_rows > 0) {
          return true;
        } else {
          return false;
        }
      } catch (Exception $e) {
        return "An error occurred: " . $e->getMessage();
      }
    } else {
      return false;
    }
  }

  public function deleteReview($review_id)
  {
    if ($this->createDatabaseConnection() == "OK") {
      try {
        $stmt = $this->db_connection->prepare("DELETE FROM `reviews` WHERE `review_id` = ?");
        $stmt->bind_param("i", $review_id);
        $success = $stmt->execute();

        if ($success && $stmt->affected_rows > 0) {
          return true;
        } else {
          return false;
        }
      } catch (Exception $e) {
        return "An error occurred: " . $e->getMessage();
      }
    } else {
      return false;
    }
  }

}

$db_handler = new Database();
$db_handler->checkSetup();

//echo $db_handler->testDatabaseConnection();
//echo $db_handler->checkSetup();
//echo $db_handler->createUser("220148717@aston.ac.uk", "password", "Harrison");

