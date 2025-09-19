<?php
session_start();
require_once 'config.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Login function
function loginUser($username, $password) {
    global $conn;
    
    $username = sanitize($conn, $username);
    
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            return true;
        }
    }
    return false;
}

// Register new user
function registerUser($username, $password, $full_name, $email) {
    global $conn;
    
    $username = sanitize($conn, $username);
    $full_name = sanitize($conn, $full_name);
    $email = sanitize($conn, $email);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if username already exists
    $check = $conn->query("SELECT * FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        return "Username already exists";
    }
    
    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email = '$email'");
    if ($check->num_rows > 0) {
        return "Email already exists";
    }
    
    $sql = "INSERT INTO users (username, password, full_name, email, role) 
            VALUES ('$username', '$hashed_password', '$full_name', '$email', 'student')";
    
    if ($conn->query($sql)) {
        return true;
    } else {
        return "Registration failed: " . $conn->error;
    }
}

// Logout function
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit;
}
?>