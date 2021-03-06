<?php
session_name("sess_id");
session_start();
require_once '../../api/api.php';
$api = new api();

/******** USER FUNCTIONS ********/

// User Sign-Up
if(isset($_POST['signup'])){
    $errors = array();

    try {
        $first_name = $api->sanitize_data(ucfirst($_POST['first_name']), 'string');
        $last_name = $api->sanitize_data(ucfirst($_POST['last_name']), 'string');
        $email = $api->sanitize_data($_POST['email'], 'email');
        $birthdate = $api->sanitize_data($_POST['birthdate'], 'string');
        $password = $_POST['password'];
        $confirm_password = trim($_POST['confirm_password']);

        // first name validation
        if(empty($first_name)){
            $_SESSION['first_name_err'] = "First name is required. ";
            array_push($errors, $_SESSION['first_name_err']);
        }
        
        elseif(mb_strlen($first_name) < 2){
            $_SESSION['first_name_err'] = "First name must be at least 2 characters long.";
            array_push($errors, $_SESSION['first_name_err']);
        }
        
        elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $first_name) || preg_match('/[0-9]+/', $first_name)){
            $_SESSION['first_name_err'] = "First name must not contain any numbers or special characters.";
            array_push($errors, $_SESSION['first_name_err']);
        }

        // last name validation
        if(empty($last_name)){
            $_SESSION['last_name_err'] = "Last name is required.";
            array_push($errors, $_SESSION['last_name_err']);
        }
        
        elseif(mb_strlen($last_name) < 2){
            $_SESSION['last_name_err'] = "Last name must be at least 2 characters long.";
            array_push($errors, $_SESSION['last_name_err']);
        }
        
        elseif(ctype_space($last_name) || preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $last_name)){
            $_SESSION['last_name_err'] = "Last name must not contain any spaces or special characters.";
            array_push($errors, $_SESSION['last_name_err']);
        }

        // email validation
        if(empty($email)){
            $_SESSION['email_err'] = "Email is required.";
            array_push($errors, $_SESSION['email_err']);
        }

        elseif(!$api->validate_data($email, 'email')){
            $_SESSION['email_err'] = "Invalid email.";
            array_push($errors, $_SESSION['email_err']);
        }

        $unique_email = $api->prepare("SELECT * FROM user WHERE user_email=?");
        if($unique_email===false){
            throw new Exception('prepare() error: The statement could not be prepared.');
        }

        $mysqli_checks = $api->bind_params($unique_email, "s", $email);
        if($mysqli_checks===false){
            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
        }

        $mysqli_checks = $api->execute($unique_email);
        if($mysqli_checks===false){
            throw new Exception('Execute error: The prepared statement could not be executed.');
        }

        $api->store_result($unique_email);
        if($api->num_rows($unique_email) >= 1){ 
            $_SESSION['email_err'] = "Email already taken.";
            array_push($errors, $_SESSION['email_err']);
        }

        $api->free_result($unique_email);
        $mysqli_checks = $api->close($unique_email);
        if($mysqli_checks===false){
            throw new Exception('The prepared statement could not be closed.');
        }
    
        // birthdate validation
        $checks = (bool) strtotime($birthdate);
        if($checks){
            $ymd = explode('-', $birthdate);
            $checks = checkdate($ymd[1], $ymd[2], $ymd[0]);
            if($checks){
                $d = DateTime::createFromFormat("Y-m-d", $birthdate);

                $checks = ($d && $d->format("Y-m-d") === $birthdate) ? true : false;
            }
        }

        if(!$checks){
            $_SESSION['birthdate_err'] = "Invalid birthdate.";
            array_push($errors, $_SESSION['birthdate_err']);
        }

        $tz = new DateTimeZone('Asia/Manila');
        $age = DateTime::createFromFormat('Y-m-d', $birthdate, $tz)->diff(new DateTime('now', $tz))->y;
        if($age < 18){
            $_SESSION['birthdate_err'] = "User must be at least 18 years old.";
            array_push($errors, $_SESSION['birthdate_err']);
        }

        if($age > 90){
            $_SESSION['birthdate_err'] = "User must not exceed 90 years old.";
            array_push($errors, $_SESSION['birthdate_err']);
        }
    
        // password validation
        if(empty($password)){
            $_SESSION['password_err'] = "Password is required.";
            array_push($errors, $_SESSION['password_err']);
        }

        elseif(ctype_space($password)){
            $_SESSION['password_err'] = "Password must not contain any spaces.";
            array_push($errors, $_SESSION['password_err']);
        }

        elseif(!preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $password)){
            $_SESSION['password_err'] = "Password must contain at least one special character.";
            array_push($errors, $_SESSION['password_err']);
        }

        elseif(!preg_match('/[A-Z]/', $password)){
            $_SESSION['password_err'] = "Password must contain at least one capital letter.";
            array_push($errors, $_SESSION['password_err']);
        }

        elseif(!preg_match('/[0-9]+/', $password)){
            $_SESSION['password_err'] = "Password must contain at least one numeric character.";
            array_push($errors, $_SESSION['password_err']);
        }

        if(empty($confirm_password)){
            $_SESSION['confirm_password_err'] = "Confirm password field must not be empty.";
            array_push($errors, $_SESSION['confirm_password_err']);
        }

        elseif(strcasecmp($password, $confirm_password) != 0){
            $_SESSION['confirm_password_err'] = "Passwords must match.";
            array_push($errors, $_SESSION['confirm_password_err']);
        }

        // Server insertion upon successful validation
        if(count($errors)== 0){
            $cstrong = true;
            $password = password_hash($password, PASSWORD_BCRYPT);
            $id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
            $uid = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));

            // Insert into client table
            $query = $api->insert();
            $query = $api->table($query, "client");
            $query = $api->columns($query, array("client_id","client_fname","client_lname","birthdate"));
            $query = $api->values($query);
            $query = $api->columns($query, array("?","?","?","?"));

            $insert_client = $api->prepare($query);
            if($insert_client===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }

            $mysqli_checks = $api->bind_params($insert_client, "ssss", array($id, $first_name, $last_name, $birthdate));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }

            $mysqli_checks = $api->execute($insert_client);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }

            $mysqli_checks = $api->close($insert_client);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }

            // Insert into user table
            $query = $api->insert();
            $query = $api->table($query, "user");
            $query = $api->columns($query, array("user_id","client_id","user_email","user_password","user_type"));
            $query = $api->values($query);
            $query = $api->columns($query, array("?","?","?","?","?"));

            $insert_user = $api->prepare($query);
            if($insert_user===false){
                throw new Exception('prepare() error: The statement could not be prepared.');
            }

            $mysqli_checks = $api->bind_params($insert_user, "sssss", array($uid, $id, $email, $password, "User"));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }

            $mysqli_checks = $api->execute($insert_user);
            if($mysqli_checks===true){
                $api->login($email, $_POST['password']);
            } else {
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }

            $mysqli_checks = $api->close($insert_user);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }
        }
    } catch (Exception $e) {
        $_SESSION['res'] = $e->getMessage();
        Header("Location: ../../client/register.php");
        exit();
    }
    
    Header("Location: ../../client/register.php");
}

// User Login
if(isset($_POST['login'])){
    $email = $api->sanitize_data($_POST['email'], 'email');
    $password = trim($_POST['password']);

    $mysqli_checks = $api->login($email, $password);
    if($mysqli_checks==!true){
        Header("Location: ../../client/index.php");
    } else {
        if(strcasecmp($_SESSION['user']['user_type'], 'User') == 0){
            Header("Location: ../../client/explore.php");
        } else {
            Header("Location: ../../admin/index.php");
        }
    }
}

// Update Profile
if(isset($_POST['update_profile'])){
    $errors = array();

    $user_id = $api->sanitize_data($_POST['user_id'], "string");
    $client_id = $api->sanitize_data($_POST['client_id'], "string");
    $first_name = $api->sanitize_data($_POST['first_name'], "string");
    $mi = $api->sanitize_data($_POST['mi'], "string");
    $last_name = $api->sanitize_data($_POST['last_name'], "string");
    $address = $api->sanitize_data($_POST['address'], "string");
    $contact_number = $api->sanitize_data($_POST['contact_number'], "int");
    $email = $api->sanitize_data($_POST['email'], "string");
    $birthdate = $api->sanitize_data($_POST['birthdate'], "string");
    
    $upload_file = boolval(!empty($_FILES['image']['name']) && !empty($_FILES['image']['type']));
    if($upload_file){
        // file upload
        $path = "../images/uploads/avatars/";
        $ext_whitelist= array('jpg','jpeg','png','gif');
        $type_whitelist = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif');

        $file_name = basename($_FILES['image']['name']);
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $file_type = strtolower($_FILES['image']['type']);

        $file_tmp_name = $_FILES['image']['tmp_name'];
        $file_size = $_FILES['image']['size'];
        $file_error = $_FILES['image']['error'];
    }

    // first name validation
    if(empty($first_name)){
        $_SESSION['first_name_err'] = "First name is required.";
        array_push($errors, $_SESSION['first_name_err']);
    }

    elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $first_name) || preg_match('/[0-9]+/', $first_name)){
        $_SESSION['first_name_err'] = "First name must not contain any numbers or special characters.";
        array_push($errors, $_SESSION['first_name_err']);
    }

    elseif(mb_strlen($first_name) > 50){
        $_SESSION['first_name_err'] = "First name must not exceed 50 characters.";
        array_push($errors, $_SESSION['first_name_err']);
    }

    // last name validation
    if(empty($last_name)){
        $_SESSION['last_name_err'] = "Last name is required.";
        array_push($errors, $_SESSION['last_name_err']);
    }

    elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $last_name) || preg_match('/[0-9]+/', $last_name)){
        $_SESSION['last_name_err'] = "Last name must not contain any numbers or special characters.";
        array_push($errors, $_SESSION['last_name_err']);
    }

    elseif(mb_strlen($last_name) > 50){
        $_SESSION['last_name_err'] = "Last name must not exceed 50 characters.";
        array_push($errors, $_SESSION['last_name_err']);
    }

    // middle initial validation
    if(mb_strlen($mi) > 2){
        $_SESSION['mi_err'] = "Referral middle initial must not exceed 2 characters.";
        array_push($errors, $_SESSION['mi_err']);
    }

    elseif(ctype_space($mi) || preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $mi) || preg_match('/[0-9]+/', $mi)){
        $_SESSION['mi_err'] = "Referral middle initial must not contain any numbers, spaces or special characters.";
        array_push($errors, $_SESSION['mi_err']);
    }

    // home address validation
    if(empty($address)){
        $_SESSION['address_err'] = "Home address is required.";
        array_push($errors, $_SESSION['address_err']);
    }

    elseif(preg_match("/['^£$%&*()}{@#~?><>|=_+¬-]/", $address)){
        $_SESSION['address_err'] = "Home address must not contain any special characters";
        array_push($errors, $_SESSION['address_err']);
    }

    elseif(mb_strlen($address) > 300){
        $_SESSION['address_err'] = "Home address must not exceed 300 characters.";
        array_push($errors, $_SESSION['address_err']);
    }

    // contact number validation
    if(empty($contact_number)){
        $_SESSION['contact_number_err'] = "Referral contact number is required.";
        array_push($errors, $_SESSION['contact_number_err']);
    }

    elseif(!$api->validate_data($contact_number, 'int')){
        $_SESSION['contact_number_err'] = "Referral contact number must be an integer.";
        array_push($errors, $_SESSION['contact_number_err']);
    }

    // email validation
    if(empty($email)){
        $_SESSION['email_err'] = "Referral email is required.";
        array_push($errors, $_SESSION['email_err']);
    }

    elseif(!$api->validate_data($email, 'email')){
        $_SESSION['email_err'] = "Invalid email. ";
        array_push($errors, $_SESSION['email_err']);
    }

    // birthdate validation
    $checks = (bool) strtotime($birthdate);
    if($checks){
        $ymd = explode('-', $birthdate);
        $checks = checkdate($ymd[1], $ymd[2], $ymd[0]);
        if($checks){
            $d = DateTime::createFromFormat("Y-m-d", $birthdate);

            $checks = ($d && $d->format("Y-m-d") === $birthdate) ? true : false;
        }
    }

    if(!$checks){
        $_SESSION['birthdate_err'] = "Invalid birthdate.";
        array_push($errors, $_SESSION['birthdate_err']);
    }

    if($upload_file){
        // file validations - path check
        if(!$path){
            $_SESSION['image_err'] = "Please specify a valid upload path.";
            array_push($errors, $_SESSION['image_err']);        
        }

        // file validations - check if there is a file
        if((!empty($_FILES['image'])) && ($file_error == 0)){
            // file validations - check file extension
            if(!in_array($file_ext, $ext_whitelist)){
                $_SESSION['image_err'] = "Uploaded file has invalid extension.";
                array_push($errors, $_SESSION['image_err']);
            }

            // file validations - check if file is a valid image
            if(!getimagesize($file_tmp_name)){
                $_SESSION['image_err'] = "Uploaded file is not a valid image.";
                array_push($errors, $_SESSION['image_err']);
            }

             // file validations - check file type
            if(!in_array($file_type, $type_whitelist)){
                $_SESSION['image_err'] = "You can't upload files of this type.";
                array_push($errors, $_SESSION['image_err']);
            }

            // file validations - check if file exceeds image size limit
            if($file_size > 50000000){
                $_SESSION['image_err'] = "File size is too large.";
                array_push($errors, $_SESSION['image_err']);
            }
        } else {
            $_SESSION['image_err'] = "An error occured while uploading your file. Please try again.";
            array_push($errors, $_SESSION['image_err']);
        }
    }
  
    if(empty($errors)){
        try {
            $statement = $api->prepare("UPDATE client SET client_fname=?, client_lname=?, client_mi=?, home_address=?, contact_number=?, birthdate=? WHERE client_id=?");
            if($statement===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }
    
            $mysqli_checks = $api->bind_params($statement, "sssssss", array($first_name, $last_name, $mi, $address, $contact_number, $birthdate, $client_id));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }

            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }
    
            $mysqli_checks = $api->close($statement);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            } else {
                $statement = null;
            }

            // check if image has been changed
            if($upload_file){
                $target_file = $path.$file_name;

                if(move_uploaded_file($file_tmp_name, "../" . $target_file)){
                    $statement = $api->prepare("UPDATE user SET user_email=?, user_avatar=? WHERE user_id=? AND client_id=?");
                    if($statement===false){
                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                    }
            
                    $mysqli_checks = $api->bind_params($statement, "ssss", array($email, $target_file, $user_id, $client_id));
                    if($mysqli_checks===false){
                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                    }

                    $_SESSION['user']['user_avatar'] = $target_file;
                } else {
                    throw new Exception("Sorry, an error occured during the file upload, please try again later.");
                }                
            } else {
                $statement = $api->prepare("UPDATE user SET user_email=? WHERE user_id=? AND client_id=?");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }
        
                $mysqli_checks = $api->bind_params($statement, "sss", array($email, $user_id, $client_id));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }
            }            
    
            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }
    
            $mysqli_checks = $api->close($statement);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/profile.php");
            exit();
        }
    }

    Header("Location: ../../client/profile.php");
}

// Update Password
if(isset($_POST['update_password'])){
    $errors = array();

    $user_id = $api->sanitize_data($_POST['user_id'], "string");
    $client_id = $api->sanitize_data($_POST['client_id'], "string");
    $password = trim($_POST['password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // new password validation
    if(empty($new_password)){
        $_SESSION['new_password_err'] = "New password is required.";
        array_push($errors, $_SESSION['new_password_err']);
    }

    elseif(ctype_space($new_password)){
        $_SESSION['new_password_err'] = "New password must not contain any spaces.";
        array_push($errors, $_SESSION['new_password_err']);
    }

    if(strcasecmp($new_password, $password) == 0){
        $_SESSION['new_password_err'] = "New password is already in use.";
        array_push($errors, $_SESSION['new_password_err']);
    }

    elseif(!preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $new_password)){
        $_SESSION['new_password_err'] = "New password must contain at least one special character.";
        array_push($errors, $_SESSION['new_password_err']);
    }

    elseif(!preg_match('/[A-Z]/', $new_password)){
        $_SESSION['new_password_err'] = "New password must contain at least one capital letter.";
        array_push($errors, $_SESSION['new_password_err']);
    }

    elseif(!preg_match('/[0-9]+/', $new_password)){
        $_SESSION['new_password_err'] = "New password must contain at least one numeric character.";
        array_push($errors, $_SESSION['new_password_err']);
    }

    // confirm password validation
    if(empty($confirm_password)){
        $_SESSION['confirm_password_err'] = "Confirm password field must not be empty.";
        array_push($errors, $_SESSION['confirm_password_err']);
    }

    elseif(strcasecmp($new_password, $confirm_password) != 0){
        $_SESSION['confirm_password_err'] = "Passwords must match. ";
        array_push($errors, $_SESSION['confirm_password_err']);
    }

    // password validation
    if(empty($password)){
        $_SESSION['password_err'] = "Old password is required.";
        array_push($errors, $_SESSION['password_err']);
    }

    if(empty($errors)){
        try {
            $hash_check = $api->prepare("SELECT user_password FROM user WHERE user_id=? AND client_id=?");
            if($hash_check===false){
                throw new Exception('prepare() error: The statement could not be prepared.');
            }

            $mysqli_checks = $api->bind_params($hash_check, "ss", array($user_id, $client_id));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }

            $mysqli_checks = $api->execute($hash_check);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }

            $res = $api->get_result($hash_check);
            if($res===false){
                throw new Exception('get_result() error: Getting result set from statement failed.');
            }

            if($api->num_rows($res) > 0){ 
                $user = $api->fetch_assoc($res);
                $hash = $user['user_password'];
            } else {
                throw new Exception('Error: Retrieving user failed.');
            }

            $api->free_result($hash_check);
            $mysqli_checks = $api->close($hash_check);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }

            if(password_verify($password, $hash)){
                $new_password = password_hash($new_password, PASSWORD_BCRYPT);
                $statement = $api->prepare("UPDATE user SET user_password=? WHERE user_id=? AND client_id=?");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }
        
                $mysqli_checks = $api->bind_params($statement, "sss", array($new_password, $user_id, $client_id));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }
        
                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                }
            } else {
                $_SESSION['password_err'] = "Old password verification unsuccessful.";
                throw new Exception($_SESSION['password_err']);
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/profile.php");
            exit();
        }
    }

    Header("Location: ../../client/profile.php");
}

// User Logout
if(isset($_POST['logout'])){
    $api->logout();
    Header("Location: ../../client/index.php");
}

/******** ORDERING TATTOOS ********/

// Ordering Tattoos
if(isset($_POST['order_item'])){
    $cstrong = true;
    $errors = array();

    $id = $api->sanitize_data($_POST['tattoo_id'], 'string');
    $name = $api->sanitize_data($_POST['tattoo_name'], 'string');
    $width = $api->sanitize_data($_POST['width'], 'int');
    $height = $api->sanitize_data($_POST['height'], 'int');
    $quantity = $api->sanitize_data($_POST['quantity'], 'int');
    $client_id = $_SESSION['user']['client_id'];
    $order_id = "";

    // validations
    if(empty($width)){
        $_SESSION['width_err'] = "Item width is required.";
        array_push($errors, $_SESSION['width_err']);
    }
    
    elseif(!$api->validate_data($width, 'int')){
        $_SESSION['width_err'] = "Item width must be an integer.";
        array_push($errors, $_SESSION['width_err']);
    }
    
    elseif($width < 0){
        $_SESSION['width_err'] = "Item width must not be negative.";
        array_push($errors, $_SESSION['width_err']);
    }

    elseif($width > 24){
        $_SESSION['width_err'] = "Item width must not exceed 24 inches.";
        array_push($errors, $_SESSION['width_err']);
    }

    if(empty($height)){
        $_SESSION['height_err'] = "Item height is required.";
        array_push($errors, $_SESSION['height_err']);
    }
    
    elseif(!$api->validate_data($height, 'int')){
        $_SESSION['width_err'] = "Item height must be an integer.";
        array_push($errors, $_SESSION['height_err']);
    }
    
    elseif($height < 0){
        $_SESSION['height_err'] = "Item height must not be negative.";
        array_push($errors, $_SESSION['height_err']);
    }

    elseif($height > 36){
        $_SESSION['height_err'] = "Item width must not exceed 36 inches.";
        array_push($errors, $_SESSION['height_err']);
    }

    if(empty($quantity)){
        $_SESSION['quantity_err'] = "Item quantity is required.";
        array_push($errors, $_SESSION['quantity_err']);
    }
    
    elseif(!$api->validate_data($quantity, 'int')){
        $_SESSION['quantity_err'] = "Item quantity must be an integer.";
        array_push($errors, $_SESSION['quantity_err']);
    }
    
    elseif($quantity < 0){
        $_SESSION['quantity_err'] = "Item quantity must not be negative.";
        array_push($errors, $_SESSION['quantity_err']);
    }

    if(empty($errors)){
        if(isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
            $order_id = $_SESSION['order']['order_id'];
            $total = (double) 0.00; 

            try {
                // find existing order item
                $statement = $api->prepare("SELECT order_item.item_id, tattoo_quantity FROM ((workorder INNER JOIN order_item ON workorder.order_id=order_item.order_id) INNER JOIN tattoo ON order_item.tattoo_id=tattoo.tattoo_id) WHERE client_id=? AND workorder.order_id=? AND paid=? AND item_status=? AND order_item.tattoo_width=? AND order_item.tattoo_height=? LIMIT 1");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "ssssii", array($_SESSION['user']['client_id'], $order_id, "Unpaid", "Standing", $width, $height));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }
                
                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $res = $api->get_result($statement);
                if($res===false){
                    throw new Exception('get_result() error: Getting result set from statement failed.');
                } else {
                    if($api->num_rows($res) > 0){
                        // existing similar order item found
                        $row = $api->fetch_assoc($res);

                        $api->free_result($statement);
                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }

                        // updating existing order item
                        $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE item_id=?");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $quantity += $row['tattoo_quantity'];
                        $mysqli_checks = $api->bind_params($statement, "is", array($quantity, $row['item_id']));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }

                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }
                    } else {
                        // no existing similar order item found, creating new order item
                        $item_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));

                        $api->free_result($statement);
                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }

                        // creating order_item                
                        $statement = $api->prepare("INSERT INTO order_item (item_id, order_id, tattoo_id, tattoo_quantity, tattoo_width, tattoo_height, item_status, paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $mysqli_checks = $api->bind_params($statement, "sssiiiss", array($item_id, $order_id, $id, $quantity, $width, $height, "Standing", "Unpaid"));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }

                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }
                    }
                }
            } catch (Exception $e) {
                $_SESSION['res'] = $e->getMessage();
                Header("Location: ../../client/explore.php#".$name);
                exit();
            }
        } else {
            // creating new order
            $order_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
            $item_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
            $_SESSION['order']['order_id'] = $order_id;
            $total = (double) 0.00;

            try {
                $statement = $api->prepare("INSERT INTO workorder (order_id, client_id, incentive, status) VALUES (?, ?, ?, ?)");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "ssss", array($order_id, $client_id, "None", "Ongoing"));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $statement = null;
                }

                // creating order_item                
                $statement = $api->prepare("INSERT INTO order_item (item_id, order_id, tattoo_id, tattoo_quantity, tattoo_width, tattoo_height, item_status, paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "sssiiiss", array($item_id, $order_id, $id, $quantity, $width, $height, "Standing", "Unpaid"));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $statement = null;
                }
            } catch (Exception $e) {
                $_SESSION['res'] = $e->getMessage();
                Header("Location: ../../client/explore.php#".$name);
                exit();
            }
        }

        try {
            // update amount due total for current order
            $mysqli_checks = $api->update_total($order_id, $_SESSION['user']['client_id']);
            if($mysqli_checks===false){
                throw new Exception('Error: Updating amount due total of current order failed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/explore.php");
            exit();
        }
    }

    Header("Location: ../../client/explore.php");
}

/******** REFERRAL MANAGEMENT ********/

// Make Referral
if(isset($_POST['refer']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    $errors = array();
    $cstrong = true;

    try {
        $client_id = $api->sanitize_data($_SESSION['user']['client_id'], 'string');
        $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');

        $first_name = $api->sanitize_data($_POST['first_name'], 'string');
        $mi = $api->sanitize_data($_POST['mi'], 'string');
        $last_name = $api->sanitize_data($_POST['last_name'], 'string');
        $age = $api->sanitize_data($_POST['age'], 'int');
        $email = $api->sanitize_data($_POST['email'], 'email');
        $contact_number = $api->sanitize_data($_POST['contact_number'], 'int');

        // first name validation
        if(empty($first_name)){
            $_SESSION['first_name_err'] = "Referral first name is required.";
            array_push($errors, $_SESSION['first_name_err']);
        }

        elseif(mb_strlen($first_name) < 2){
            $_SESSION['first_name_err'] = "Referral first name must be at least 2 characters long.";
            array_push($errors, $_SESSION['first_name_err']);
        }

        elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $first_name) || preg_match('/[0-9]+/', $first_name)){
            $_SESSION['first_name_err'] = "Referral first name must not contain any spaces or special characters.";
            array_push($errors, $_SESSION['first_name_err']);
        }

        // middle initial validation
        if(mb_strlen($mi) > 2){
            $_SESSION['mi_err'] = "Referral middle initial must not exceed 2 characters.";
            array_push($errors, $_SESSION['mi_err']);
        }

        elseif(ctype_space($mi) || preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $mi) || preg_match('/[0-9]+/', $mi)){
            $_SESSION['mi_err'] = "Referral middle initial must not contain any numbers, spaces or special characters.";
            array_push($errors, $_SESSION['mi_err']);
        }

        // last name validation
        if(empty($last_name)){
            $_SESSION['last_name_err'] = "Referral last name is required.";
            array_push($errors, $_SESSION['last_name_err']);
        }

        elseif(mb_strlen($last_name) < 2){
            $_SESSION['last_name_err'] = "Referral last must be at least 2 characters long.";
            array_push($errors, $_SESSION['last_name_err']);
        }

        elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $last_name) || preg_match('/[0-9]+/', $last_name)){
            $_SESSION['last_name_err'] = "Referral last must not contain any numbers or special characters.";
            array_push($errors, $_SESSION['last_name_err']);
        }

        // referral uniqueness check
        $statement = $api->prepare("SELECT * FROM referral WHERE client_id=? AND order_id=? AND referral_fname=? AND referral_lname=?");
        if($statement===false){
            throw new Exception('prepare() error: The statement could not be prepared.');
        }

        $mysqli_checks = $api->bind_params($statement, "ssss", array($client_id, $order_id, $first_name, $last_name));
        if($mysqli_checks===false){
            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
        }

        $mysqli_checks = $api->execute($statement);
        if($mysqli_checks===false){
            throw new Exception('Execute error: The prepared statement could not be executed.');
        }

        $api->store_result($statement);
        if($api->num_rows($statement) > 0){ 
            $_SESSION['referral_err'] = "You cannot make a referral to the same person for your current workorder more than once!";
            array_push($errors, $_SESSION['referral_err']);
        }

        $api->free_result($statement);
        $mysqli_checks = $api->close($statement);
        if($mysqli_checks===false){
            throw new Exception('The prepared statement could not be closed.');
        } else {
            $statement = null;
        }

        // email validation
        if(empty($email)){
            $_SESSION['email_err'] = "Referral email is required.";
            array_push($errors, $_SESSION['email_err']);
        }

        elseif(!$api->validate_data($email, 'email')){
            $_SESSION['email_err'] = "Invalid email.";
            array_push($errors, $_SESSION['email_err']);
        }

        $unique_email = $api->prepare("SELECT * FROM user WHERE user_email=?");
        if($unique_email===false){
            throw new Exception('prepare() error: The statement could not be prepared.');
        }

        $mysqli_checks = $api->bind_params($unique_email, "s", $email);
        if($mysqli_checks===false){
            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
        }

        $mysqli_checks = $api->execute($unique_email);
        if($mysqli_checks===false){
            throw new Exception('Execute error: The prepared statement could not be executed.');
        }

        $api->store_result($unique_email);
        if($api->num_rows($unique_email) >= 1){ 
            $_SESSION['email_err'] = "Referral email already in use.";
            array_push($errors, $_SESSION['email_err']);
        }

        $api->free_result($unique_email);
        $mysqli_checks = $api->close($unique_email);
        if($mysqli_checks===false){
            throw new Exception('The prepared statement could not be closed.');
        }

        // age validation
        if(empty($age)){
            $_SESSION['age_err'] = "Referral age is required.";
            array_push($errors, $_SESSION['age_err']);
        }
        
        elseif(!$api->validate_data($age, 'int')){
            $_SESSION['age_err'] = "Referral age must be an integer.";
            array_push($errors, $_SESSION['age_err']);
        }
        
        elseif($age < 0){
            $_SESSION['age_err'] = "Referral age must not be negative.";
            array_push($errors, $_SESSION['age_err']);
        }

        elseif($age < 18){
            $_SESSION['age_err'] = "Referral age must be at least 18 years old.";
            array_push($errors, $_SESSION['age_err']);
        }

        // contact number validation
        if(empty($contact_number)){
            $_SESSION['contact_number_err'] = "Referral contact number is required.";
            array_push($errors, $_SESSION['contact_number_err']);
        }

        elseif(!$api->validate_data($contact_number, 'int')){
            $_SESSION['contact_number_err'] = "Referral contact number must be an integer.";
            array_push($errors, $_SESSION['contact_number_err']);
        }

        elseif(mb_strlen($contact_number) < 7){
            $_SESSION['contact_number_err'] = "Referral contact number must be at least 7 numbers long.";
            array_push($errors, $_SESSION['contact_number_err']);
        }

        elseif(mb_strlen($contact_number) > 11){
            $_SESSION['contact_number_err'] = "Referral contact number must not exceed 11 numbers long.";
            array_push($errors, $_SESSION['contact_number_err']);
        }

        if(empty($errors)){
            $referral_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));

            $query = $api->insert();
            $query = $api->table($query, "referral");
            $query = $api->columns($query, array("referral_id", "client_id", "order_id", "referral_fname", "referral_mi", "referral_lname", "referral_contact_no", "referral_email", "referral_age", "confirmation_status"));
            $query = $api->values($query);
            $query = $api->columns($query, array("?", "?", "?", "?", "?", "?", "?", "?", "?", "?"));

            $statement = $api->prepare($query);
            if($statement===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }

            $mysqli_checks = $api->bind_params($statement, "ssssssssis",  array($referral_id, $client_id, $order_id, $first_name, $mi, $last_name, $contact_number, $email, $age, "Pending"));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }
            
            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }

            $mysqli_checks = $api->close($statement);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }
        }
    } catch (Exception $e) {
        $_SESSION['res'] = $e->getMessage();
        Header("Location: ../../client/orders.php");
        exit();
    }

    Header("Location: ../../client/orders.php");
}

// Availing Referral Incentives
if(isset($_POST['avail_incentive']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    $errors = array();
    $client_id = $api->sanitize_data($_SESSION['user']['client_id'], 'string');
    $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');
    $incentive = $api->sanitize_data($_POST['incentive'], 'string');
    $tattoo_id = $api->sanitize_data($_POST['tattoo_id'], 'string');

    // incentive type validations
    if(empty($incentive)){
        $_SESSION['incentive_err'] = "Workorder incentive to avail is required.";
        array_push($errors, $_SESSION['incentive_err']);
    }

    elseif(!in_array($incentive, array("15% Discount", "Free 3x3 Tattoo"))){
        $_SESSION['incentive_err'] = "Workorder incentive must be either 15% Discount, or Free 3x3 Tattoo.";
        array_push($errors, $_SESSION['incentive_err']);
    }

    // tattoo validations
    if(strcasecmp($incentive, "Free 3x3 Tattoo") == 0 && empty($tattoo_id)){
        array_push($errors, $_SESSION['incentive_tattoo_err']);
    }

    if(empty($errors)){
        try {
            $statement = $api->prepare("UPDATE workorder SET incentive=? WHERE order_id=? AND client_id=?");
            if($statement===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }

            $mysqli_checks = $api->bind_params($statement, "sss",  array($incentive, $order_id, $client_id));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }
            
            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }

            $mysqli_checks = $api->close($statement);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }

            if(strcasecmp($incentive, "15% discount") == 0){
                // update amount due total for current order
                $mysqli_checks = $api->update_total($order_id, $client_id);
                if($mysqli_checks===false){
                    throw new Exception('Error: Updating amount due total of current order failed.');
                }
            } elseif((strcasecmp($incentive, "Free 3x3 Tattoo")) == 0){
                $cstrong = true;
                $item_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));

                $statement = $api->prepare("INSERT INTO order_item (item_id, order_id, tattoo_id, tattoo_quantity, tattoo_width, tattoo_height, paid, item_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "sssiiiss",  array($item_id, $order_id, $tattoo_id, 1, 3, 3, "Fully Paid", "Standing"));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }
                
                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                }
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/orders.php");
            exit();
        }
    }

    Header("Location: ../../client/orders.php");
}

// Update Referral Details
if(isset($_POST['update_referrals']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    if(isset($_POST['referral']) && !empty($_POST['referral'])){
        $client_id = $api->sanitize_data($_SESSION['user']['client_id'], 'string');
        $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');

        try {
            foreach($_POST['referral'] as $item){
                $item = $api->sanitize_data($item, 'string');
                $index = array_search($item, $_POST['referral_index']);

                $first_name = $api->sanitize_data($_POST['referral_fname'][$index], 'string');
                $mi = $api->sanitize_data($_POST['referral_mi'][$index], 'string');
                $last_name = $api->sanitize_data($_POST['referral_lname'][$index], 'string');
                $age = $api->sanitize_data($_POST['referral_age'][$index], 'int');
                $email = $api->sanitize_data($_POST['referral_email'][$index], 'email');
                $contact_number = $api->sanitize_data($_POST['referral_contact_no'][$index], 'int');

                // first name validation
                if(empty($first_name)){
                    $_SESSION['first_name_err'] = "Referral first name is required.";
                    array_push($errors, $_SESSION['first_name_err']);
                }

                elseif(mb_strlen($first_name) < 2){
                    $_SESSION['first_name_err'] = "Referral first name must be at least 2 characters long.";
                    array_push($errors, $_SESSION['first_name_err']);
                }

                elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $first_name) || preg_match('/[0-9]+/', $first_name)){
                    $_SESSION['first_name_err'] = "Referral first name must not contain any numbers or special characters.";
                    array_push($errors, $_SESSION['first_name_err']);
                }

                // last name validation
                if(empty($last_name)){
                    $_SESSION['last_name_err'] = "Referral last name is required.";
                    array_push($errors, $_SESSION['last_name_err']);
                }

                elseif(mb_strlen($last_name) < 2){
                    $_SESSION['last_name_err'] = "Referral last name must be at least 2 characters long.";
                    array_push($errors, $_SESSION['last_name_err']);
                }

                elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $last_name) || preg_match('/[0-9]+/', $last_name)){
                    $_SESSION['last_name_err'] = "Referral last name must not contain any numbers or special characters.";
                    array_push($errors, $_SESSION['last_name_err']);
                }

                // email validation
                if(empty($email)){
                    $_SESSION['email_err'] = "Referral email is required.";
                    array_push($errors, $_SESSION['email_err']);
                }

                elseif(!$api->validate_data($email, 'email')){
                    $_SESSION['email_err'] = "Invalid email.";
                    array_push($errors, $_SESSION['email_err']);
                }

                // age validation
                if(empty($age)){
                    $_SESSION['age_err'] = "Referral age is required.";
                    array_push($errors, $_SESSION['age_err']);
                }
                
                elseif(!$api->validate_data($age, 'int')){
                    $_SESSION['age_err'] = "Referral age must be an integer.";
                    array_push($errors, $_SESSION['age_err']);
                }
                
                elseif($age < 0){
                    $_SESSION['age_err'] = "Referral age must not be negative.";
                    array_push($errors, $_SESSION['age_err']);
                }

                elseif($age < 17){
                    $_SESSION['age_err'] = "Referral age must be at least 17 years old.";
                    array_push($errors, $_SESSION['age_err']);
                }

                // contact number validation
                if(empty($contact_number)){
                    $_SESSION['contact_number_err'] = "Referral contact number is required.";
                    array_push($errors, $_SESSION['contact_number_err']);
                }

                elseif(!$api->validate_data($contact_number, 'int')){
                    $_SESSION['contact_number_err'] = "Referral contact number must be an integer.";
                    array_push($errors, $_SESSION['contact_number_err']);
                }

                elseif(mb_strlen($contact_number) < 7){
                    $_SESSION['contact_number_err'] = "Referral contact number must be at least 7 numbers long.";
                    array_push($errors, $_SESSION['contact_number_err']);
                }

                elseif(mb_strlen($contact_number) > 11){
                    $_SESSION['contact_number_err'] = "Referral contact number must not exceed 11 numbers long.";
                    array_push($errors, $_SESSION['contact_number_err']);
                }

                if(empty($errors)){
                    $statement = $api->prepare("UPDATE referral SET referral_fname=?, referral_mi=?, referral_lname=?, referral_contact_no=?, referral_email=?, referral_age=? WHERE referral_id=? AND order_id=? AND client_id=?");
                    if($statement===false){
                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                    }

                    $mysqli_checks = $api->bind_params($statement, "sssssisss",  array($first_name, $mi, $last_name, $contact_number, $email, $age, $item, $order_id, $client_id));
                    if($mysqli_checks===false){
                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                    }
                    
                    $mysqli_checks = $api->execute($statement);
                    if($mysqli_checks===false){
                        throw new Exception('Execute error: The prepared statement could not be executed.');
                    }

                    $mysqli_checks = $api->close($statement);
                    if($mysqli_checks===false){
                        throw new Exception('The prepared statement could not be closed.');
                    }
                }
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/orders.php");
            exit();
        }
    }

    Header("Location: ../../client/orders.php");
}

// Remove Referral
if(isset($_POST['remove_referrals']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    if(isset($_POST['referral']) && !empty($_POST['referral'])){
        $client_id = $api->sanitize_data($_SESSION['user']['client_id'], 'string');
        $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');

        try {
            foreach($_POST['referral'] as $item){
                $item = $api->sanitize_data($item, 'string');

                $statement = $api->prepare("DELETE FROM referral WHERE referral_id=? AND order_id=? AND client_id=?");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "sss",  array($item, $order_id, $client_id));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }
                
                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                }
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/orders.php");
            exit();
        }
    }

    Header("Location: ../../client/orders.php");
}

/******** ORDER MANAGEMENT ********/

// Update Tattoo Orders
if(isset($_POST['update_items']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    if(isset($_POST['item'])){
        try {
            $order_id = $_SESSION['order']['order_id'];

            foreach($_POST['item'] as $item){
                $errors = array();

                $item = $api->sanitize_data($item, 'string');
                $index = array_search($item, $_POST['index']);

                $width = $api->sanitize_data($_POST['width'][$index], 'int');
                $height = $api->sanitize_data($_POST['height'][$index], 'int');
                $quantity = $api->sanitize_data($_POST['quantity'][$index], 'int');
                $paid = $api->sanitize_data($_POST['paid'][$index], 'string');
                $status = $api->sanitize_data($_POST['status'][$index], 'string');

                // validations
                if(empty($width)){
                    $_SESSION['width_err'] = "Item width is required.";
                    array_push($errors, $_SESSION['width_err']);
                }
                
                elseif(!$api->validate_data($width, 'int')){
                    $_SESSION['width_err'] = "Item width must be an integer.";
                    array_push($errors, $_SESSION['width_err']);
                }
                
                elseif($width < 0){
                    $_SESSION['width_err'] = "Item width must not be negative.";
                    array_push($errors, $_SESSION['width_err']);
                }
            
                elseif($width > 24){
                    $_SESSION['width_err'] = "Item width must not exceed 24 inches.";
                    array_push($errors, $_SESSION['width_err']);
                }
            
                if(empty($height)){
                    $_SESSION['height_err'] = "Item height is required.";
                    array_push($errors, $_SESSION['height_err']);
                }
                
                elseif(!$api->validate_data($height, 'int')){
                    $_SESSION['width_err'] = "Item height must be an integer.";
                    array_push($errors, $_SESSION['height_err']);
                }
                
                elseif($height < 0){
                    $_SESSION['height_err'] = "Item height must not be negative.";
                    array_push($errors, $_SESSION['height_err']);
                }
            
                elseif($height > 36){
                    $_SESSION['height_err'] = "Item width must not exceed 36 inches.";
                    array_push($errors, $_SESSION['height_err']);
                }
            
                if(empty($quantity)){
                    $_SESSION['quantity_err'] = "Item quantity is required.";
                    array_push($errors, $_SESSION['quantity_err']);
                }
                
                elseif(!$api->validate_data($quantity, 'int')){
                    $_SESSION['quantity_err'] = "Item quantity must be an integer.";
                    array_push($errors, $_SESSION['quantity_err']);
                }
                
                elseif($quantity < 0){
                    $_SESSION['quantity_err'] = "Item quantity must not be negative.";
                    array_push($errors, $_SESSION['quantity_err']);
                }

                if(empty($paid)){
                    $_SESSION['paid_err'] = "Payment status is required. ";
                    array_push($errors, $_SESSION['paid_err']);
                }
        
                elseif(!in_array($paid, array("Unpaid", "Partially Paid"))){
                    $_SESSION['paid_err'] = "Payment status must be either Unpaid or Partially Paid. ";
                    array_push($errors, $_SESSION['paid_err']);
                }

                if(empty($status)){
                    $_SESSION['status_err'] = "Item status is required.";
                    array_push($errors, $_SESSION['status_err']);
                }
        
                elseif(strcasecmp($status, "Standing")){
                    $_SESSION['status_err'] = "Item status must be Standing.";
                    array_push($errors, $_SESSION['status_err']);
                }

                if(empty($errors)){
                    // find existing order item
                    $statement = $api->prepare("SELECT order_item.item_id, tattoo_quantity FROM ((workorder INNER JOIN order_item ON workorder.order_id=order_item.order_id) INNER JOIN tattoo ON order_item.tattoo_id=tattoo.tattoo_id) WHERE client_id=? AND workorder.order_id=? AND paid=? AND item_status=? AND order_item.tattoo_width=? AND order_item.tattoo_height=? AND item_id !=? LIMIT 1");
                    if($statement===false){
                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                    }

                    $mysqli_checks = $api->bind_params($statement, "ssssiis", array($_SESSION['user']['client_id'], $order_id, $paid, $status, $width, $height, $item));
                    if($mysqli_checks===false){
                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                    }
                    
                    $mysqli_checks = $api->execute($statement);
                    if($mysqli_checks===false){
                        throw new Exception('Execute error: The prepared statement could not be executed.');
                    }

                    $res = $api->get_result($statement);
                    if($res===false){
                        throw new Exception('get_result() error: Getting result set from statement failed.');
                    } else {
                        if($api->num_rows($res) > 0){
                            // existing similar order item found
                            $row = $api->fetch_assoc($res);

                            $api->free_result($statement);
                            $mysqli_checks = $api->close($statement);
                            if($mysqli_checks===false){
                                throw new Exception('The prepared statement could not be closed.');
                            } else {
                                $statement = null;
                            }

                            // updating existing order item
                            $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                            if($statement===false){
                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                            }

                            $quantity += $row['tattoo_quantity'];
                            $mysqli_checks = $api->bind_params($statement, "iss", array($quantity, $order_id, $row['item_id']));
                            if($mysqli_checks===false){
                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                            }

                            $mysqli_checks = $api->execute($statement);
                            if($mysqli_checks===false){
                                throw new Exception('Execute error: The prepared statement could not be executed.');
                            }

                            $mysqli_checks = $api->close($statement);
                            if($mysqli_checks===false){
                                throw new Exception('The prepared statement could not be closed.');
                            } else {
                                $statement = null;
                            }

                            // merging down order item
                            $statement = $api->prepare("DELETE FROM order_item WHERE order_id=? AND item_id=?");
                            if($statement===false){
                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                            }

                            $mysqli_checks = $api->bind_params($statement, "ss", array($order_id, $item));
                            if($mysqli_checks===false){
                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                            }

                            $mysqli_checks = $api->execute($statement);
                            if($mysqli_checks===false){
                                throw new Exception('Execute error: The prepared statement could not be executed.');
                            }

                            $mysqli_checks = $api->close($statement);
                            if($mysqli_checks===false){
                                throw new Exception('The prepared statement could not be closed.');
                            } else {
                                $statement = null;
                            }
                        } else {
                            // no existing similar order item found
                            $api->free_result($statement);
                            $mysqli_checks = $api->close($statement);
                            if($mysqli_checks===false){
                                throw new Exception('The prepared statement could not be closed.');
                            } else {
                                $statement = null;
                            }

                            $statement = $api->prepare("UPDATE order_item SET tattoo_width=?, tattoo_height=?, tattoo_quantity=? WHERE order_id=? AND item_id=?");
                            if($statement===false){
                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                            }
                    
                            $mysqli_checks = $api->bind_params($statement, "iiiss", array($width, $height, $quantity, $order_id, $item));
                            if($mysqli_checks===false){
                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                            }
                    
                            $mysqli_checks = $api->execute($statement);
                            if($mysqli_checks===false){
                                throw new Exception('Execute error: The prepared statement could not be executed.');
                            }
                    
                            $mysqli_checks = $api->close($statement);
                            if($mysqli_checks===false){
                                throw new Exception('The prepared statement could not be closed.');
                            } else {
                                $statement = null;
                            }
                        }
                    }
                } else {
                    $_SESSION['res'] = $errors;
                }
            }

            // update amount due total for current order
            $mysqli_checks = $api->update_total($order_id, $_SESSION['user']['client_id']);
            if($mysqli_checks===false){
                throw new Exception('Error: Updating amount due total of current order failed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/orders.php");
            exit();
        }
    } else {
        $_SESSION['res'] = "No rows selected.";
    }

    Header("Location: ../../client/orders.php");
}

// Remove Tattoo Orders
if(isset($_POST['remove_items']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    if(isset($_POST['item'])){
        $order_id = $_SESSION['order']['order_id'];

        try {
            foreach($_POST['item'] as $item){
                $item = $api->sanitize_data($item, 'string');

                $statement = $api->prepare("DELETE FROM order_item WHERE order_id=? AND item_id=?");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $statement->errno . ' - ' . $statement->error);
                }
        
                $mysqli_checks = $api->bind_params($statement, "ss", array($order_id, $item));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }
        
                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }
        
                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $statement = null;
                }
            }
        
            // update amount due total for current order
            $mysqli_checks = $api->update_total($order_id, $_SESSION['user']['client_id']);
            if($mysqli_checks===false){
                throw new Exception('Error: Updating amount due total of current order failed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/orders.php");
            exit();
        }
    } else {
        $_SESSION['res'] = "No rows selected.";
    }

    Header("Location: ../../client/orders.php");
}

/******** RESERVATION MANAGEMENT ********/

// Booking Reservation
if(isset($_POST['book']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    $errors = array();
    $cstrong = true;

    $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');
    $predecessor_id = $api->sanitize_data($_POST['item_id'], 'string');

    $quantity = $api->sanitize_data($_POST['quantity'], 'int');
    $original_quantity = $api->sanitize_data($_POST['original_quantity'], 'int');
    $service_type = $api->sanitize_data($_POST['service_type'], 'string');
    $scheduled_time = $_POST['scheduled_time'];
    $scheduled_date = $_POST['scheduled_date'];
    $address = $api->sanitize_data($_POST['address'], 'string');
    $reservation_description = (!empty($_POST['description'])) ? $api->sanitize_data($_POST['description'], 'string') : "None";

    // validations
    if(empty($quantity)){
        $_SESSION['quantity_err'] = "Reserved item quantity is required.";
        array_push($errors, $_SESSION['quantity_err']);
    }
    
    elseif(!$api->validate_data($quantity, 'int')){
        $_SESSION['quantity_err'] = "Reserved item quantity must be an integer.";
        array_push($errors, $_SESSION['quantity_err']);
    }
    
    elseif($quantity < 0){
        $_SESSION['quantity_err'] = "Reserved item quantity must not be negative.";
        array_push($errors, $_SESSION['quantity_err']);
    }

    elseif($quantity > $original_quantity){
        $_SESSION['quantity_err'] = "Reserved item quantity must not exceed the item's ordered quantity.";
        array_push($errors, $_SESSION['quantity_err']);
    }

    if(empty($service_type)){
        $_SESSION['service_type_err'] = "Service type is required.";
        array_push($errors, $_SESSION['service_type_err']);
    }

    elseif(!in_array($service_type, array("Walk-in", "Home Service"))){
        $_SESSION['service_type_err'] = "Service type must be either home service or walk-in.";
        array_push($errors, $_SESSION['service_type_err']);
    }

    elseif(strcasecmp($service_type, 'Home Service') == 0 && empty($address)){
        $_SESSION['address_err'] = "Address for home service is required.";
        array_push($errors, $_SESSION['address_err']);
    }

    if(!$api->validate_data($scheduled_date, 'date')){
        $_SESSION['scheduled_date_err'] = "Invalid date.";
        array_push($errors, $_SESSION['scheduled_date_err']);
    }

    if(!$api->validate_data($scheduled_time, 'time')){
        $_SESSION['scheduled_time_err'] = "Invalid time.";
        array_push($errors, $_SESSION['scheduled_time_err']);
    }

    if(empty($errors)){
        try {
            $item_id = $predecessor_id;
            // get order item
            $query = $api->select();
            $query = $api->params($query, array("tattoo_id", "tattoo_width", "tattoo_height", "paid"));
            $query = $api->from($query);
            $query = $api->table($query, "order_item");
            $query = $api->where($query, array("item_id", "order_id"), array("?", "?"));
            $query = $api->limit($query, 1);

            $statement = $api->prepare($query);
            if($statement===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }

            $mysqli_checks = $api->bind_params($statement, "ss", array($predecessor_id, $order_id));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }
            
            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }

            $res = $api->get_result($statement);
            if($res===false){
                throw new Exception('get_result() error: Getting result set from statement failed.');
            } else {
                if($api->num_rows($res) > 0){
                    $item = $api->fetch_assoc($res);

                    $api->free_result($res);
                    $mysqli_checks = $api->close($statement);
                    if($mysqli_checks===false){
                        throw new Exception('The prepared statement could not be closed.');
                    } else {
                        $statement = null;
                    }

                    $reservation_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
                    $reservation_addon = (double) 300.00;
                    $original_quantity -= $quantity;
                    $scheduled_date = date("Y:m:d", strtotime($scheduled_date));

                    // update predecessor item quantity
                    if($original_quantity == 0){
                        if(strcasecmp($service_type, "Walk-in") == 0 && strcasecmp($item['paid'], "Partially Paid") == 0){
                            $statement = $api->prepare("UPDATE order_item SET paid=?, item_status=? WHERE item_id=?");
                            if($statement===false){
                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                            }

                            $api->bind_params($statement, "sss", array("Fully Paid", "Reserved", $predecessor_id));
                            if($mysqli_checks===false){
                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                            }

                        } else{
                            $statement = $api->prepare("UPDATE order_item SET item_status=? WHERE item_id=?");
                            if($statement===false){
                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                            }

                            $mysqli_checks = $api->bind_params($statement, "ss", array("Reserved", $predecessor_id));
                            if($mysqli_checks===false){
                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                            }
                        }

                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }
                    } else {
                        $item_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));

                        // creating reserved item
                        $query = $api->insert();
                        $query = $api->table($query, "order_item");
                        $query = $api->columns($query, array("item_id", "order_id", "tattoo_id", "tattoo_quantity", "tattoo_width", "tattoo_height", "paid", "item_status"));
                        $query = $api->values($query);
                        $query = $api->columns($query, array("?", "?", "?", "?", "?", "?", "?", "?"));

                        $statement = $api->prepare($query);
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        if((strcasecmp($service_type, "Walk-in") == 0 && strcasecmp($item['paid'], "Partially Paid") == 0)){
                            $item['paid'] = "Fully Paid";
                        }

                        $mysqli_checks = $api->bind_params($statement, "sssiiiss", array($item_id, $order_id, $item['tattoo_id'], $quantity, $item['tattoo_width'], $item['tattoo_height'], $item['paid'], "Reserved"));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }

                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }

                        // updating predecessor item quantity
                        $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE item_id=?");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $mysqli_checks = $api->bind_params($statement, "is", array($original_quantity, $predecessor_id));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }

                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }
                    }

                    // creating reservation
                    $insert_reservation = $api->insert();
                    $insert_reservation = $api->table($insert_reservation, "reservation");
                    $insert_reservation = $api->columns($insert_reservation, array("reservation_id", "item_id", "reservation_description", "reservation_status", "service_type", "reservation_address", "scheduled_date", "scheduled_time", "amount_addon"));
                    $insert_reservation = $api->values($insert_reservation);
                    $insert_reservation = $api->columns($insert_reservation, array("?", "?", "?", "?", "?", "?", "?", "?", "?"));

                    $statement = $api->prepare($insert_reservation);
                    if($statement===false){
                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                    }

                    $mysqli_checks = strcasecmp($service_type, "Walk-in") == 0 ? $api->bind_params($statement, "ssssssssd", array($reservation_id, $item_id, $reservation_description, "Pending", $service_type, $address, $scheduled_date, $scheduled_time, 0.00)) : $api->bind_params($statement, "ssssssssd", array($reservation_id, $item_id, $reservation_description, "Pending", $service_type, $address, $scheduled_date, $scheduled_time, $reservation_addon));
                    if($mysqli_checks===false){
                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                    }

                    $mysqli_checks = $api->execute($statement);
                    if($mysqli_checks===false){
                        throw new Exception('Execute error: The prepared statement could not be executed.');
                    }

                    $mysqli_checks = $api->close($statement);
                    if($mysqli_checks===false){
                        throw new Exception('The prepared statement could not be closed.');
                    } else {
                        $statement = null;
                    }
                } else {
                    throw new Exception('No order item with the given ID could be found.');
                }
            }

            // update amount due total for current order
            $mysqli_checks = $api->update_total($order_id, $_SESSION['user']['client_id']);
            if($mysqli_checks===false){
                throw new Exception('Error: Updating amount due total of current order failed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/reservations.php");
            exit();
        }
    }

    Header("Location: ../../client/reservations.php");
}

// Revise Reservation Details
if(isset($_POST['update_reservation'])){
    $errors = array();

    $reservation_id = $api->sanitize_data($_POST['reservation_id'], 'string');
    $item_id = $api->sanitize_data($_POST['item_id'], 'string');
    $service_type = $api->sanitize_data($_POST['service_type'], 'string');
    $time = $_POST['scheduled_time'];
    $date = $_POST['scheduled_date'];   
    $address = $api->sanitize_data($_POST['reservation_address'], 'string');
    $demands = $api->sanitize_data($_POST['reservation_demands'], 'string');

    if(empty($address)){
        $_SESSION['address_err'] = "Reservation address is required.";
        array_push($errors, $_SESSION['address_err']);
    }

    if(empty($service_type)){
        $_SESSION['service_type_err'] = "Service type is required.";
        array_push($errors, $_SESSION['service_type_err']);
    }

    if(!$api->validate_data($date, 'date')){
        $_SESSION['scheduled_date_err'] = "Invalid date.";
        array_push($errors, $_SESSION['scheduled_date_err']);
    }

    if(!$api->validate_data($time, 'time')){
        $_SESSION['scheduled_time_err'] = "Invalid time.";
        array_push($errors, $_SESSION['scheduled_time_err']);
    }

    if(empty($errors)){
        $date = date("Y:m:d", strtotime($date));
        $amount_addon = strcasecmp($service_type, "Home service") == 0 ? 300.00 : 0;

        try {
            $statement = $api->prepare("UPDATE reservation SET service_type=?, amount_addon=?, reservation_address=?, reservation_description=?, scheduled_date=?, scheduled_time=? WHERE reservation_id=? AND item_id=?");
            if($statement===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }
    
            $mysqli_checks = $api->bind_params($statement, "sdssssss", array($service_type, $amount_addon, $address, $demands, $date, $time, $reservation_id, $item_id));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }
    
            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }
    
            $mysqli_checks = $api->close($statement);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }

            // update amount due total for current order
            $mysqli_checks = $api->update_total($_SESSION['order']['order_id'], $_SESSION['user']['client_id']);
            if($mysqli_checks===false){
                throw new Exception('Error: Updating amount due total of current order failed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/reservations.php");
            exit();
        }
    } else {
        $_SESSION['res'] = $errors;
    }

    Header("Location: ../../client/reservations.php");
}

// Confirm Reservation
if(isset($_POST['confirm_reservation'])){
    $errors = array();

    $reservation_id = $api->sanitize_data($_POST['reservation_id'], 'string');
    $item_id = $api->sanitize_data($_POST['item_id'], 'string');
    $service_type = $api->sanitize_data($_POST['service_type'], 'string');
    $scheduled_time = $_POST['scheduled_time'];
    $scheduled_date = $_POST['scheduled_date'];
    $address = $api->sanitize_data($_POST['reservation_address'], 'string');

    if(empty($address)){
        $_SESSION['address_err'] = "Reservation address is required.";
        array_push($errors, $_SESSION['address_err']);
    }

    if(empty($service_type)){
        $_SESSION['service_type_err'] = "Service type is required.";
        array_push($errors, $_SESSION['service_type_err']);
    }

    if(!$api->validate_data($scheduled_date, 'date')){
        $_SESSION['scheduled_date_err'] = "Invalid date.";
        array_push($errors, $_SESSION['scheduled_date_err']);
    }

    if(!$api->validate_data($scheduled_time, 'time')){
        $_SESSION['scheduled_time_err'] = "Invalid time.";
        array_push($errors, $_SESSION['scheduled_time_err']);
    }

    if(empty($errors)){
        try {
            $statement = $api->prepare("UPDATE reservation SET reservation_status=? WHERE reservation_id=? AND item_id=?");
            if($statement===false){
                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
            }
    
            $mysqli_checks = $api->bind_params($statement, "sss", array("Confirmed", $reservation_id, $item_id));
            if($mysqli_checks===false){
                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
            }
    
            $mysqli_checks = $api->execute($statement);
            if($mysqli_checks===false){
                throw new Exception('Execute error: The prepared statement could not be executed.');
            }
    
            $mysqli_checks = $api->close($statement);
            if($mysqli_checks===false){
                throw new Exception('The prepared statement could not be closed.');
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/reservations.php");
            exit();
        }
    } else {
        $_SESSION['res'] = $errors;
    }
    
    Header("Location: ../../client/reservations.php");
}

// Cancel Reservation
if(isset($_POST['cancel_reservation'])){
    $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');
    $reservation_id = $api->sanitize_data($_POST['reservation_id'], 'string');
    $item_id = $api->sanitize_data($_POST['item_id'], 'string');
    $quantity = $api->sanitize_data($_POST['quantity'], 'int');

    try {
        // get order item
        $statement = $api->prepare("SELECT tattoo_id, order_id, tattoo_width, tattoo_height, paid FROM order_item WHERE item_id=? AND order_id=? LIMIT 1");
        if($statement===false){
            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
        }

        $mysqli_checks = $api->bind_params($statement, "ss", array($item_id, $order_id));
        if($mysqli_checks===false){
            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
        }
        
        $mysqli_checks = $api->execute($statement);
        if($mysqli_checks===false){
            throw new Exception('Execute error: The prepared statement could not be executed.');
        }

        $res = $api->get_result($statement);
        if($res===false){
            throw new Exception('get_result() error: Getting result set from statement failed.');
        } else {
            if($api->num_rows($res) > 0){
                $item = $api->fetch_assoc($res);

                $api->free_result($res);
                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $res = $statement = null;
                }

                // deleting reservation
                $statement = $api->prepare("DELETE FROM reservation WHERE reservation_id=? AND item_id=?");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "ss", array($reservation_id, $item_id));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $statement = null;
                }

                // finding similar item
                $statement = $api->prepare("SELECT item_id, tattoo_quantity FROM order_item WHERE order_id=? AND tattoo_width=? AND tattoo_height=? AND paid=? AND item_id!=? AND item_status=? LIMIT ?");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "siisssi", array($order_id, $item['tattoo_width'], $item['tattoo_height'], $item['paid'], $item_id, "Standing", 1));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }
                
                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $res = $api->get_result($statement);
                if($res===false){
                    throw new Exception('get_result() error: Getting result set from statement failed.');
                } else {
                    if($api->num_rows($res) > 0){
                        // similar item found
                        $row = $api->fetch_assoc($res);
        
                        $api->free_result($res);
                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $res = $statement = null;
                        }

                        // updating order item_quantity
                        $row['tattoo_quantity'] += $quantity;
                        $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE item_id=?");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $mysqli_checks = $api->bind_params($statement, "is", array($row['tattoo_quantity'], $row['item_id']));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }
                        
                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }

                        $statement = $api->prepare("DELETE FROM order_item WHERE item_id=?");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $mysqli_checks = $api->bind_params($statement, "s", $item_id);
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }
                        
                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }
                    } else {
                        // no similar item found
                        $statement = $api->prepare("UPDATE order_item SET item_status=? WHERE item_id=?");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $mysqli_checks = $api->bind_params($statement, "ss", array("Standing", $item_id));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }
                        
                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $mysqli_checks = $api->close($statement);
                        if($mysqli_checks===false){
                            throw new Exception('The prepared statement could not be closed.');
                        } else {
                            $statement = null;
                        }
                    }
                }
            } else {
                throw new Exception('No order item with the given ID could be found.');
            }
        }

        // update amount due total for current order
        $mysqli_checks = $api->update_total($order_id, $_SESSION['user']['client_id']);
        if($mysqli_checks===false){
            throw new Exception('Error: Updating amount due total of current order failed.');
        }
    } catch (Exception $e) {
        $_SESSION['res'] = $e->getMessage();
        Header("Location: ../../client/reservations.php");
        exit();
    }

    Header("Location: ../../client/reservations.php");
}

/******** ORDER CHECKOUT ********/

// Order Checkout
if(isset($_POST['checkout']) && isset($_SESSION['order']['order_id']) && !empty($_SESSION['order']['order_id'])){
    if(isset($_POST['item']) && !empty($_POST['item'])){
        try {
            $errors = array();
            $cstrong = true;

            $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');
            $client_id = $api->sanitize_data($_SESSION['user']['client_id'], 'string');

            $first_name = $api->sanitize_data(ucfirst($_POST['first_name']), 'string');
            $last_name = $api->sanitize_data(ucfirst($_POST['last_name']), 'string');
            $street_address = $api->sanitize_data($_POST['street_address'], 'string');
            $city = $api->sanitize_data($_POST['city'], 'string');
            $province = $api->sanitize_data($_POST['province'], 'string');
            $zip = $api->sanitize_data($_POST['zip'], 'int');
            $amount_paid = $api->sanitize_data($_POST['amount_paid'], 'float');
            $payment_method = $api->sanitize_data($_POST['payment_method'], 'string');
            $card_number = $api->sanitize_data($_POST['card_number'], 'string');
            $pin = $api->sanitize_data($_POST['pin'], 'string');
            $bank_name = $api->sanitize_data($_POST['bank_name'], 'string');

            // validations
            // first name validation
            if(empty($first_name)){
                $_SESSION['first_name_err'] = "First name is required.";
                array_push($errors, $_SESSION['first_name_err']);
            }
            
            elseif(mb_strlen($first_name) < 2){
                $_SESSION['first_name_err'] = "First name must be at least 2 characters long.";
                array_push($errors, $_SESSION['first_name_err']);
            }
            
            elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $first_name) || preg_match('/[0-9]+/', $first_name)){
                $_SESSION['first_name_err'] = "First name must not contain any numbers or special characters.";
                array_push($errors, $_SESSION['first_name_err']);
            }

            // last name validation
            if(empty($last_name)){
                $_SESSION['last_name_err'] = "Last name is required.";
                array_push($errors, $_SESSION['last_name_err']);
            }
            
            elseif(mb_strlen($last_name) < 2){
                $_SESSION['last_name_err'] = "Last name must be at least 2 characters long.";
                array_push($errors, $_SESSION['last_name_err']);
            }
            
            elseif(preg_match("/['^£$%&*()}{@#~?><>,|=_+¬-]/", $last_name) || preg_match('/[0-9]+/', $last_name)){
                $_SESSION['last_name_err'] = "Last name must not contain any numbers or special characters.";
                array_push($errors, $_SESSION['last_name_err']);
            }

            // billing address validations
            if(empty($street_address)){
                $_SESSION['street_address_err'] = "Street address is required.";
                array_push($errors, $_SESSION['street_address_err']);
            }

            elseif(mb_strlen($street_address) > 255){
                $_SESSION['street_address_err'] = "Street address must not exceed 255 characters.";
                array_push($errors, $_SESSION['street_address_err']);
            }

            if(empty($city)){
                $_SESSION['city_err'] = "City name is required.";
                array_push($errors, $_SESSION['city_err']);
            }

            elseif(mb_strlen($city) > 35){
                $_SESSION['city_err'] = "City name must not exceed 35 characters.";
                array_push($errors, $_SESSION['city_err']);
            }

            if(empty($province)){
                $_SESSION['province_err'] = "Province name is required.";
                array_push($errors, $_SESSION['province_err']);
            }

            elseif(mb_strlen($province) > 35){
                $_SESSION['province_err'] = "Provice name must not exceed 35 characters.";
                array_push($errors, $_SESSION['province_err']);
            }

            if(empty($zip)){
                $_SESSION['zip_err'] = "ZIP code is required.";
                array_push($errors, $_SESSION['zip_err']);
            }

            elseif(mb_strlen($zip) > 4){
                $_SESSION['zip_err'] = "ZIP code 4 must not exceed characters.";
                array_push($errors, $_SESSION['zip_err']);
            }

            elseif(!$api->validate_data($zip, 'int')){
                $_SESSION['zip_err'] = "ZIP code must be an integer.";
                array_push($errors, $_SESSION['zip_err']);
            }

            // payment validations
            if(empty($amount_paid)){
                $_SESSION['amount_paid_err'] = "Payment amount is required.";
                array_push($errors, $_SESSION['amount_paid_err']);
            }

            elseif(!is_numeric($amount_paid)){
                $_SESSION['amount_paid_err'] = "Payment amount must be a numeric value.";
                array_push($errors, $_SESSION['amount_paid_err']);
            }

            elseif($amount_paid < 0){
                $_SESSION['amount_paid_err'] = "Payment amount must not be negative.";
                array_push($errors, $_SESSION['amount_paid_err']);
            }

            if(empty($payment_method)){
                $_SESSION['payment_method_err'] = "Payment method is required.";
                array_push($errors, $_SESSION['payment_method_err']);
            }

            elseif(!in_array($payment_method, array("Debit", "Credit", "Prepaid"))){
                $_SESSION['payment_method_err'] = "Card type must be Debit, Credit, or Prepaid. ";
                array_push($errors, $_SESSION['payment_method_err']);
            }

            // card validations
            if(empty($card_number)){
                $_SESSION['card_number_err'] = "Card number is required.";
                array_push($errors, $_SESSION['card_number_err']);
            }

            if(!is_numeric($api->sanitize_data($card_number, 'int'))){
                $_SESSION['card_number_err'] = "Card number must be numeric.";
                array_push($errors, $_SESSION['card_number_err']);
            }

            if(empty($pin)){
                $_SESSION['pin_err'] = "Card PIN is required.";
                array_push($errors, $_SESSION['pin_err']);
            }

            if(!is_numeric($api->sanitize_data($pin, 'string'))){
                $_SESSION['pin_err'] = "Card PIN must be numeric.";
                array_push($errors, $_SESSION['pin_err']);
            }

            if(empty($bank_name)){
                $_SESSION['bank_name_err'] = "Bank name is required.";
                array_push($errors, $_SESSION['bank_name_err']);
            }

            if(empty($errors)){
                $errors = [];
                $change = doubleval($amount_paid);

                // checking for discount
                $statement = $api->prepare("SELECT incentive FROM workorder WHERE order_id=? AND client_id=? AND status=? ORDER BY order_date ASC LIMIT 1");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "sss", array($order_id, $client_id, "Ongoing"));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $api->store_result($statement);
                if($api->num_rows($statement) > 0){
                    $discount = "";
                    $res = $api->bind_result($statement, array($discount));
                    $api->get_bound_result($discount, $res[0]);
                }

                $api->free_result($statement);
                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $res = $statement = null;
                }

                if(isset($discount) && !empty($discount) && strcasecmp($discount, "15% discount") == 0){
                    $total = (double) 0.00;
                    $change = doubleval(($change / 85) * 100);
                }

                foreach($_POST['item'] as $item){
                    $index = array_search($item, $_POST['index']);
                    
                    $checkout_quantity = $api->sanitize_data($_POST['checkout_quantity'][$index], 'int');
                    $quantity = $api->sanitize_data($_POST['quantity'][$index], 'int');

                    if(empty($checkout_quantity)){
                        $_SESSION['quantity_err'] = "Checkout quantity is required. ";
                        array_push($errors, $_SESSION['quantity_err']);
                    }
            
                    elseif(!$api->validate_data($checkout_quantity, 'int')){
                        $_SESSION['quantity_err'] = "Checkout quantity must be an integer. ";
                        array_push($errors, $_SESSION['quantity_err']);
                    }
            
                    elseif($checkout_quantity < 0){
                        $_SESSION['quantity_err'] = "Checkout quantity must not be negative. ";
                        array_push($errors, $_SESSION['quantity_err']);
                    }

                    elseif($checkout_quantity > $quantity){
                        $_SESSION['quantity_err'] = "Checkout quantity must not exceed the quantity of the ordered item. ";
                        array_push($errors, $_SESSION['quantity_err']);
                    }

                    if(empty($errors)){
                        $statement = $api->prepare("SELECT order_item.tattoo_id, tattoo_price, order_item.tattoo_width, order_item.tattoo_height, paid, item_status, amount_addon FROM ((order_item INNER JOIN tattoo ON order_item.tattoo_id=tattoo.tattoo_id) LEFT JOIN reservation ON order_item.item_id=reservation.item_id) WHERE order_id=? AND order_item.item_id=? LIMIT 1");
                        if($statement===false){
                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                        }

                        $mysqli_checks = $api->bind_params($statement, "ss", array($order_id, $item));
                        if($mysqli_checks===false){
                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                        }
                        
                        $mysqli_checks = $api->execute($statement);
                        if($mysqli_checks===false){
                            throw new Exception('Execute error: The prepared statement could not be executed.');
                        }

                        $res = $api->get_result($statement);
                        if($res===false){
                            throw new Exception('get_result() error: Getting result set from statement failed.');
                        }

                        if($api->num_rows($res) > 0){
                            $row = $api->fetch_assoc($res);

                            $api->free_result($statement);
                            $mysqli_checks = $api->close($statement);
                            if($mysqli_checks===false){
                                throw new Exception('The prepared statement could not be closed.');
                            } else {
                                $statement = null;
                            }

                            $tattoo_id = $api->sanitize_data($row['tattoo_id'], 'string');
                            $width = $api->sanitize_data($row['tattoo_width'], 'int');
                            $height = $api->sanitize_data($row['tattoo_height'], 'int');
                            $paid = $api->sanitize_data($row['paid'], 'string');
                            $item_status = $api->sanitize_data($row['item_status'], 'string');
                            $addon = (!empty($row['amount_addon']) && $row['amount_addon'] != 0) ? doubleval($row['amount_addon']) : 0.00;
                            $item_amount_due_total = doubleval($row['tattoo_price']) * $checkout_quantity;

                            if(in_array($item_status, array("Reserved", "Applied")) && strcasecmp($paid, "Partially Paid") == 0){
                                $item_amount_due_total += doubleval($row['tattoo_price']) + $addon;
                            }

                            if($change >= $item_amount_due_total){
                                $change -= $item_amount_due_total;
                                if(isset($discount) && !empty($discount) && strcasecmp($discount, "15% discount") == 0){
                                    $total += $item_amount_due_total;
                                }
    
                                // Case - Item Standing Unpaid
                                if(strcasecmp($item_status, "Standing") == 0){
                                    // Finding similar item
                                    $statement = $api->prepare("SELECT order_item.item_id, tattoo_quantity FROM (order_item INNER JOIN tattoo ON order_item.tattoo_id=tattoo.tattoo_id) WHERE order_id=? AND item_id!=? AND order_item.tattoo_id=? AND paid=? AND item_status=? AND order_item.tattoo_width=? AND order_item.tattoo_height=? LIMIT 1");
                                    if($statement===false){
                                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                    }

                                    $mysqli_checks = $api->bind_params($statement, "sssssii", array($order_id, $item, $tattoo_id, "Partially Paid", $item_status, $width, $height));
                                    if($mysqli_checks===false){
                                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                    }
    
                                    $mysqli_checks = $api->execute($statement);
                                    if($mysqli_checks===false){
                                        throw new Exception('Execute error: The prepared statement could not be executed.');
                                    }
    
                                    $res = $api->get_result($statement);
                                    if($res===false){
                                        throw new Exception('get_result() error: Getting result set from statement failed.');
                                    }
    
                                    if($api->num_rows($res) > 0){
                                        // Similar item found
                                        $successor = $api->fetch_assoc($res);
                                        $api->free_result($res);
    
                                        $mysqli_checks = $api->close($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('The prepared statement could not be closed.');
                                        } else {
                                            $res = $statement = null;
                                        }
    
                                        if($checkout_quantity == $quantity){
                                            // merging down checkout item
                                            $statement = $api->prepare("DELETE FROM order_item WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "ss", array($order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        } else {
                                            $quantity -= $checkout_quantity;
                                            $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "iss", array($quantity, $order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        }
    
                                        // updating found Standing Partially Paid item
                                        $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                                        if($statement===false){
                                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                        }
    
                                        $successor['tattoo_quantity'] += $checkout_quantity;
                                        $mysqli_checks = $api->bind_params($statement, "iss", array($successor['tattoo_quantity'], $order_id, $successor['item_id']));
                                        if($mysqli_checks===false){
                                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                        }
    
                                        $mysqli_checks = $api->execute($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('Execute error: The prepared statement could not be executed.');
                                        }
    
                                        $mysqli_checks = $api->close($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('The prepared statement could not be closed.');
                                        } else {
                                            $statement = null;
                                        }
                                    } else {
                                        // No similar item found
                                        $api->free_result($res);
    
                                        $mysqli_checks = $api->close($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('The prepared statement could not be closed.');
                                        } else {
                                            $statement = null;
                                        }
    
                                        if($checkout_quantity == $quantity){
                                            // updating item payment status
                                            $statement = $api->prepare("UPDATE order_item SET paid=? WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "sss", array("Partially Paid", $order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        } else {
                                            $item_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
                                            $quantity -= $checkout_quantity;
    
                                            $statement = $api->prepare("INSERT INTO order_item (item_id, order_id, tattoo_id, tattoo_quantity, tattoo_width, tattoo_height, paid, item_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "sssiiiss", array($item_id, $order_id, $tattoo_id, $checkout_quantity, $width, $height, "Partially Paid", "Standing"));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
    
                                            $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "iss", array($quantity, $order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        }
                                    }
                                }
    
                                // Case - Reserved
                                elseif(strcasecmp($item_status, "Reserved") == 0){
                                    // updating item payment status
                                    $statement = $api->prepare("UPDATE order_item SET paid=? WHERE item_id=?");
                                    if($statement===false){
                                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                    }
    
                                    $mysqli_checks = $api->bind_params($statement, "ss", array("Fully Paid", $item));
                                    if($mysqli_checks===false){
                                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                    }
    
                                    $mysqli_checks = $api->execute($statement);
                                    if($mysqli_checks===false){
                                        throw new Exception('Execute error: The prepared statement could not be executed.');
                                    }
    
                                    $mysqli_checks = $api->close($statement);
                                    if($mysqli_checks===false){
                                        throw new Exception('The prepared statement could not be closed.');
                                    } else {
                                        $statement = null;
                                    }
                                }
    
                                // Case - Applied
                                elseif(strcasecmp($item_status, "Applied") == 0){
                                    // Finding similar item
                                    $statement = $api->prepare("SELECT order_item.item_id, tattoo_quantity FROM (order_item INNER JOIN tattoo ON order_item.tattoo_id=tattoo.tattoo_id) WHERE order_id=? AND item_id!=? AND order_item.tattoo_id=? AND paid=? AND item_status=? AND order_item.tattoo_width=? AND order_item.tattoo_height=? LIMIT 1");
                                    if($statement===false){
                                        throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                    }
    
                                    $mysqli_checks = $api->bind_params($statement, "sssssii", array($order_id, $item, $tattoo_id, "Fully Paid", $item_status, $width, $height));
                                    if($mysqli_checks===false){
                                        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                    }
    
                                    $mysqli_checks = $api->execute($statement);
                                    if($mysqli_checks===false){
                                        throw new Exception('Execute error: The prepared statement could not be executed.');
                                    }
    
                                    $res = $api->get_result($statement);
                                    if($res===false){
                                        throw new Exception('get_result() error: Getting result set from statement failed.');
                                    }
    
                                    if($api->num_rows($res) > 0){
                                        // Similar item found
                                        $successor = $api->fetch_assoc($res);
                                        $api->free_result($res);
    
                                        $mysqli_checks = $api->close($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('The prepared statement could not be closed.');
                                        } else {
                                            $statement = null;
                                        }
    
                                        if($checkout_quantity == $quantity){
                                            // updating reservation foreign key
                                            $statement = $api->prepare("UPDATE reservation SET item_id=? WHERE item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "ss", array($successor['item_id'], $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
    
                                            // merging down checkout item
                                            $statement = $api->prepare("DELETE FROM order_item WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "ss", array($order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        } else {
                                            $quantity -= $checkout_quantity;
                                            $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "iss", array($item_quantity, $order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        }
    
                                        // updating found Applied Fully Paid item
                                        $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                                        if($statement===false){
                                            throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                        }
    
                                        $successor['tattoo_quantity'] += $checkout_quantity;
                                        $mysqli_checks = $api->bind_params($statement, "iss", array($successor['tattoo_quantity'], $order_id, $successor['item_id']));
                                        if($mysqli_checks===false){
                                            throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                        }
    
                                        $mysqli_checks = $api->execute($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('Execute error: The prepared statement could not be executed.');
                                        }
    
                                        $mysqli_checks = $api->close($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('The prepared statement could not be closed.');
                                        } else {
                                            $statement = null;
                                        }
                                    } else {
                                        // No similar item found
                                        $api->free_result($res);
    
                                        $mysqli_checks = $api->close($statement);
                                        if($mysqli_checks===false){
                                            throw new Exception('The prepared statement could not be closed.');
                                        } else {
                                            $statement = null;
                                        }
    
                                        if($checkout_quantity == $quantity){
                                            // updating item payment status
                                            $statement = $api->prepare("UPDATE order_item SET paid=? WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "sss", array("Fully Paid", $order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        } else {
                                            $item_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
                                            $quantity -= $checkout_quantity;
    
                                            $statement = $api->prepare("INSERT INTO order_item (item_id, order_id, tattoo_id, tattoo_quantity, tattoo_width, tattoo_height, paid, item_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "sssiiiss", array($item_id, $order_id, $tattoo_id, $checkout_quantity, $width, $height, "Fully Paid", "Applied"));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
    
                                            $statement = $api->prepare("UPDATE order_item SET tattoo_quantity=? WHERE order_id=? AND item_id=?");
                                            if($statement===false){
                                                throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                                            }
    
                                            $mysqli_checks = $api->bind_params($statement, "iss", array($quantity, $order_id, $item));
                                            if($mysqli_checks===false){
                                                throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                                            }
    
                                            $mysqli_checks = $api->execute($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('Execute error: The prepared statement could not be executed.');
                                            }
    
                                            $mysqli_checks = $api->close($statement);
                                            if($mysqli_checks===false){
                                                throw new Exception('The prepared statement could not be closed.');
                                            } else {
                                                $statement = null;
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            throw new Exception('No order item under the given IDs found.');
                        }
                    } else {
                        $errors = [];
                    }
                }

                // update amount due total for current order
                $mysqli_checks = $api->update_total($order_id, $client_id);
                if($mysqli_checks===false){
                    throw new Exception('Error: Updating amount due total of current order failed.');
                }

                // logging transaction - inserting in payment table
                $payment_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));
                $card_payment_id = bin2hex(openssl_random_pseudo_bytes(11, $cstrong));

                if(isset($discount) && !empty($discount) && strcasecmp($discount, "15% discount") == 0){
                    $change = $amount_paid - ($total - ($total * .15));
                }

                $statement = $api->prepare("INSERT INTO payment (payment_id, order_id, amount_paid, payment_method, payment_change, client_fname, client_lname, street_address, city, province, zip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "ssdsdssssss", array($payment_id, $order_id, $amount_paid, "Card", $change, $first_name, $last_name, $street_address, $city, $province, $zip));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                } else {
                    $statement = null;
                }

                // logging transaction - inserting in card table
                $statement = $api->prepare("INSERT INTO card (payment_id, card_payment_id, card_number, card_holder_fname, card_holder_lname, bank_name, card_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if($statement===false){
                    throw new Exception('prepare() error: ' . $api->errno() . ' - ' . $api->error());
                }

                $mysqli_checks = $api->bind_params($statement, "sssssss", array($payment_id, $card_payment_id, $card_number, $first_name, $last_name, $bank_name, $payment_method));
                if($mysqli_checks===false){
                    throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
                }

                $mysqli_checks = $api->execute($statement);
                if($mysqli_checks===false){
                    throw new Exception('Execute error: The prepared statement could not be executed.');
                }

                $mysqli_checks = $api->close($statement);
                if($mysqli_checks===false){
                    throw new Exception('The prepared statement could not be closed.');
                }
            }
        } catch (Exception $e) {
            $_SESSION['res'] = $e->getMessage();
            Header("Location: ../../client/checkout.php");
            exit();
        }
    } else {
        $_SESSION['res'] = "No items selected.";
    }

    Header("Location: ../../client/checkout.php");
}

/******** ILLEGAL ACCESS CATCHING ********/

// Navigation Guard
if(empty($_POST)){
    Header("Location: ../../client/index.php");
    die();
}
?>