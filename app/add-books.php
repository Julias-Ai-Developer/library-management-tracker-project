<?php
require_once '../config.php';
require_once '../auth.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Only admin can access this page
if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

$success_message = '';
$error_message = '';

// Initialize variables to avoid undefined variable warnings
$book_id = '';
$title = '';
$author = '';
// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_book'])) {
    $book_id = sanitize($conn, $_POST['book_id']);
    $title = sanitize($conn, $_POST['title']);
    $author = sanitize($conn, $_POST['author']);
    
    // Validation
    $errors = array();
    
    if (empty($book_id)) {
        $errors[] = "Book ID is required";
    }
    if (empty($title)) {
        $errors[] = "Book title is required";
    }
    if (empty($author)) {
        $errors[] = "Author name is required";
    }
    
    // Check for duplicate ID only if book_id is not empty
    if (!empty($book_id)) {
        $stmt_check = $conn->prepare("SELECT book_id FROM books WHERE book_id = ?");
        $stmt_check->bind_param("s", $book_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Book ID already exists";
        }
        $stmt_check->close();
    }
    
    if (empty($errors)) {
        // Add book to database using prepared statement
        $stmt = $conn->prepare("INSERT INTO books (book_id, title, author, status) VALUES (?, ?, ?, 'Available')");
        $stmt->bind_param("sss", $book_id, $title, $author);
        
        if ($stmt->execute()) {
            $success_message = "Book added successfully!";
            // Clear form fields after successful insertion
            $book_id = $title = $author = "";
        } else {
            $error_message = "Error adding book: " . $conn->error;
        }
        
        $stmt->close();
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>