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

// Process delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $book_id = sanitize($conn, $_GET['delete']);
    
    // Check if book is borrowed
    $check = $conn->query("SELECT * FROM books WHERE book_id = '$book_id' AND status = 'Borrowed'");
    if ($check->num_rows > 0) {
        $error_message = "Cannot delete a book that is currently borrowed";
    } else {
        // Delete the book
        $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("s", $book_id);
        
        if ($stmt->execute()) {
            $success_message = "Book deleted successfully!";
        } else {
            $error_message = "Error deleting book: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Process update request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_book'])) {
    $book_id = sanitize($conn, $_POST['book_id']);
    $title = sanitize($conn, $_POST['title']);
    $author = sanitize($conn, $_POST['author']);
    
    // Validation
    $error_message = "";
    
    if (empty($book_id)) {
        $error_message = "Book ID is required";
    } else if (empty($title)) {
        $error_message = "Book title is required";
    } else if (empty($author)) {
        $error_message = "Author name is required";
    }

    if (empty($error_message)) {
        // Update book in database
        $stmt = $conn->prepare("UPDATE books SET title = ?, author = ? WHERE book_id = ?");
        $stmt->bind_param("sss", $title, $author, $book_id);
        
        if ($stmt->execute()) {
            $success_message = "Book updated successfully!";
        } else {
            $error_message = "Error updating book: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get book details for edit
$edit_book = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $book_id = sanitize($conn, $_GET['edit']);
    $result = $conn->query("SELECT * FROM books WHERE book_id = '$book_id'");
    
    if ($result->num_rows > 0) {
        $edit_book = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(135deg, #6f42c1, #e83e8c);
            min-height: 100vh;
            color: white;
            padding-top: 2rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.8rem 1rem;
            margin: 0.2rem 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .content {
            padding: 2rem;
        }
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border-radius: 10px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: linear-gradient(135deg, #6f42c1, #e83e8c);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6f42c1, #e83e8c);
            border: none;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        .logo i {
            margin-right: 10px;
        }
        .user-info {
            padding: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .user-info .user-name {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .user-info .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="logo">
                    <i class="fas fa-book-reader"></i> LibraryMS
                </div>
                
                <div class="user-info">
                    <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                    <div class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></div>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php?action=books">
                            <i class="fas fa-book"></i> Browse Books
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php?action=my_books">
                            <i class="fas fa-bookmark"></i> My Borrowed Books
                        </a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="add-books.php">
                            <i class="fas fa-plus-circle"></i> Add New Book
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage-books.php">
                            <i class="fas fa-edit"></i> Manage Books
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php?action=transactions">
                            <i class="fas fa-exchange-alt"></i> All Transactions
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item mt-5">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <!-- Header -->
                <div class="header d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-edit me-2"></i> Manage Books</h1>
                    <a href="add-books.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Add New Book
                    </a>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($edit_book): ?>
                <!-- Edit Book Form -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-edit me-2"></i> Edit Book
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="book_id" class="form-label">Book ID</label>
                                    <input type="text" class="form-control" id="book_id" name="book_id" value="<?php echo $edit_book['book_id']; ?>" readonly>
                                    <small class="text-muted">Book ID cannot be changed</small>
                                </div>
                                <div class="col-md-8">
                                    <label for="title" class="form-label">Book Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_book['title']; ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="author" name="author" value="<?php echo $edit_book['author']; ?>" required>
                            </div>
                            <button type="submit" name="update_book" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Book
                            </button>
                            <a href="manage-books.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Book List -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list me-2"></i> Book Inventory
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Book ID</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Status</th>
                                        <th>Added On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all books from database
                                    $result = $conn->query("SELECT * FROM books ORDER BY created_at DESC");
                                    
                                    if ($result->num_rows > 0) {
                                        while ($book = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>{$book['book_id']}</td>";
                                            echo "<td>{$book['title']}</td>";
                                            echo "<td>{$book['author']}</td>";
                                            echo "<td>";
                                            if ($book['status'] == 'Available') {
                                                echo "<span class='badge bg-success'>Available</span>";
                                            } else {
                                                echo "<span class='badge bg-danger'>Borrowed</span>";
                                            }
                                            echo "</td>";
                                            echo "<td>" . date('M d, Y', strtotime($book['created_at'])) . "</td>";
                                            echo "<td class='action-buttons'>";
                                            echo "<a href='?edit={$book['book_id']}' class='btn btn-sm btn-primary me-1'><i class='fas fa-edit'></i></a>";
                                            if ($book['status'] == 'Available') {
                                                echo "<a href='?delete={$book['book_id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this book?\")'><i class='fas fa-trash'></i></a>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center'>No books found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>