<?php
session_start();

if (isset($_POST['pass_but']) && isset($_SESSION['userId'])) {
    require '../helpers/init_conn_db.php';  

    // Initialize variables
    $mobile_flag = false;
    $flight_id = intval($_POST['flight_id']);
    $passengers = $_POST['passengers'];

    // Validate mobile numbers (10 digits expected)
    foreach ($_POST['mobile'] as $mobile) {
        if (strlen($mobile) !== 10) {  // Updated to check for 10 digits
            $mobile_flag = true;
            break;
        }
    }

    // Redirect if invalid mobile numbers are found
    if ($mobile_flag) {
        header('Location: ../pass_form.php?error=moblen');
        exit();         
    }

    // Validate date of birth - should not be a future date
    $date_len = count($_POST['date']);
    for ($i = 0; $i < $date_len; $i++) {        
        $date_mnth = (int)substr($_POST['date'][$i], 5, 2);
        $date_day = (int)substr($_POST['date'][$i], 8, 2);
        $current_month = (int)date('m');
        $current_day = (int)date('d');
        $flag = false;

        if ($date_mnth > $current_month) {
            $flag = true;
        } else if ($date_mnth === $current_month && $date_day >= $current_day) {
            $flag = true;
        }

        if ($flag) {
            header('Location: ../pass_form.php?error=invdate');
            exit();
        }      
    }

    // Check if passenger data already exists
    $stmt = mysqli_stmt_init($conn);
    $sql = 'SELECT * FROM Passenger_profile WHERE flight_id = ? AND user_id = ?';
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header('Location: ../pass_form.php?error=sqlerror');
        exit();            
    } else {
        $uid = intval($_SESSION['userId']);
        mysqli_stmt_bind_param($stmt, 'ii', $flight_id, $uid);            
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $pass_id = null;

        while ($row = mysqli_fetch_assoc($result)) {
            $pass_id = $row['passenger_id'];
        }
    } 

    // If no passenger profile exists, reset auto-increment
    if (is_null($pass_id)) {
        $pass_id = 0;
        $sql = 'ALTER TABLE Passenger_profile AUTO_INCREMENT = 1';
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../pass_form.php?error=sqlerror');
            exit();            
        } else {         
            mysqli_stmt_execute($stmt);
        }        
    }

    // Insert new passenger data
    $flag = false;
    for ($i = 0; $i < $date_len; $i++) {
        $sql = 'INSERT INTO Passenger_profile (user_id, mobile, dob, f_name, m_name, l_name, flight_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)';
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../pass_form.php?error=sqlerror');
            exit();
        } else {
            mysqli_stmt_bind_param($stmt, 'iissssi', 
                $_SESSION['userId'], 
                $_POST['mobile'][$i], 
                $_POST['date'][$i], 
                $_POST['firstname'][$i], 
                $_POST['midname'][$i], 
                $_POST['lastname'][$i], 
                $flight_id
            );                           
            mysqli_stmt_execute($stmt);  
            $flag = true;        
        }
    }

    // Redirect to payment if data insertion is successful
    if ($flag) {
        $_SESSION['flight_id'] = $flight_id;
        $_SESSION['class'] = $_POST['class'];
        $_SESSION['passengers'] = $passengers;
        $_SESSION['price'] = $_POST['price'];
        $_SESSION['type'] = $_POST['type'];
        $_SESSION['ret_date'] = $_POST['ret_date'];
        $_SESSION['pass_id'] = $pass_id + 1;

        header('Location: ../payment.php');
        exit();          
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

} else {
    header('Location: ../pass_form.php');
    exit();
}
