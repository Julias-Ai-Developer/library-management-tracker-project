<?php
require_once '../config.php';
require_once '../auth.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Process borrow book form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['borrow_book'])) {
    $book_id = sanitize($conn, $_POST['borrow_book_id']);
    $user_id = $_SESSION['user_id'];
    
    $errors = array();
    
    if (empty($book_id)) {
        $errors[] = "Please enter a Book ID";
    }
    
    if (empty($errors)) {
        // Check if book exists and is available
        $stmt_check = $conn->prepare("SELECT * FROM books WHERE book_id = ? AND status = 'Available'");
        $stmt_check->bind_param("s", $book_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            // Update book status
            $stmt_update = $conn->prepare("UPDATE books SET status = 'Borrowed' WHERE book_id = ?");
            $stmt_update->bind_param("s", $book_id);
            
            if ($stmt_update->execute()) {
                // Record transaction
                $due_date = date('Y-m-d', strtotime('+14 days'));
                $trans_stmt = $conn->prepare("INSERT INTO transactions (book_id, user_id, borrow_date, due_date, status) VALUES (?, ?, NOW(), ?, 'Borrowed')");
                $trans_stmt->bind_param("sss", $book_id, $user_id, $due_date);
                
                if ($trans_stmt->execute()) {
                    // Redirect to dashboard with success message
                    $success_msg = "Book borrowed successfully! Due date: " . date('M d, Y', strtotime($due_date));
                    header("Location: dashboard.php?success=" . urlencode($success_msg));
                    exit;
                } else {
                    $error_message = "Error recording transaction: " . $conn->error;
                }
                
                $trans_stmt->close();
            } else {
                $error_message = "Error borrowing book: " . $conn->error;
            }
            
            $stmt_update->close();
        } else {
            $errors[] = "Book is either not available or does not exist";
        }
        
        $stmt_check->close();
    }
    
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

// Get available books for display
$available_books = $conn->query("SELECT book_id, title FROM books WHERE status = 'Available' ORDER BY title");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Book - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #6f42c1, #e83e8c);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header text-center">
        <div class="container">
            <h1><i class="fas fa-hand-holding"></i> Borrow a Book</h1>
            <p class="lead">Select a book to borrow from our library</p>
            <a href="../dashboard.php" class="btn btn-light me-2">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Error Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Borrow Book Form -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h4><i class="fas fa-hand-holding"></i> Borrow a Book</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="borrow_book_id" class="form-label">Enter Book ID to Borrow *</label>
                                <input type="text" class="form-control" id="borrow_book_id" name="borrow_book_id" 
                                       placeholder="Enter book ID" required>
                                <div class="form-text">Enter the Book ID from the available books list on the right.</div>
                            </div>
                            
                            <button type="submit" name="borrow_book" class="btn btn-warning">
                                <i class="fas fa-hand-holding"></i> Borrow Book
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Available Books List -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6><i class="fas fa-list"></i> Available Books</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($available_books->num_rows == 0): ?>
                            <p class="text-muted">No books available for borrowing.</p>
                            <a href="dashboard.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Books
                            </a>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php while ($book = $available_books->fetch_assoc()): ?>
                                    <div class="mb-2 p-2 bg-light rounded border">
                                        <strong class="text-primary"><?php echo htmlspecialchars($book['book_id']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($book['title']); ?></small>
                                        <button class="btn btn-outline-primary btn-sm float-end" 
                                                onclick="document.getElementById('borrow_book_id').value='<?php echo htmlspecialchars($book['book_id']); ?>'">
                                            <i class="fas fa-hand-holding"></i>
                                        </button>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>