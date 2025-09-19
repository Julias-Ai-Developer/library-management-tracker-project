<?php
require_once 'config.php';
require_once 'auth.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Get user information for welcome message
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT full_name FROM users WHERE id = '$user_id'");
$user_name = $user_query->fetch_assoc()['full_name'];

// Process menu actions
$current_action = isset($_GET['action']) ? $_GET['action'] : 'menu';

// Handle success message from other pages (for toast notifications)
$toast_success_message = '';
if (isset($_GET['success'])) {
    $toast_success_message = htmlspecialchars($_GET['success']);
}

// Initialize alert messages
$success_message = '';
$error_message = '';

// Borrow a book
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['borrow_book'])) {
    $book_id = sanitize($conn, $_POST['borrow_book_id']);
    $user_id = $_SESSION['user_id'];

    if (!empty($book_id)) {
        // Check if book exists and is available
        $check = $conn->query("SELECT * FROM books WHERE book_id = '$book_id' AND status = 'Available'");

        if ($check->num_rows > 0) {
            // Update book status
            $stmt = $conn->prepare("UPDATE books SET status = 'Borrowed' WHERE book_id = ?");
            $stmt->bind_param("s", $book_id);

            if ($stmt->execute()) {
                // Record transaction
                $due_date = date('Y-m-d', strtotime('+14 days'));
                $trans_stmt = $conn->prepare("INSERT INTO transactions (book_id, user_id, borrow_date, due_date, status) VALUES (?, ?, NOW(), ?, 'Borrowed')");
                $trans_stmt->bind_param("sss", $book_id, $user_id, $due_date);
                $trans_stmt->execute();
                $trans_stmt->close();

                $success_message = "Book borrowed successfully! Due date: " . date('M d, Y', strtotime($due_date));
            } else {
                $error_message = "Error borrowing book: " . $conn->error;
            }

            $stmt->close();
        } else {
            $error_message = "Book is either not available or does not exist!";
        }
    } else {
        $error_message = "Please enter a Book ID!";
    }
}

// Return a book
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_book'])) {
    $book_id = sanitize($conn, $_POST['return_book_id']);
    $user_id = $_SESSION['user_id'];

    if (!empty($book_id)) {
        // Check if book exists and is borrowed by this user
        $check_query = "SELECT t.* FROM transactions t 
                        JOIN books b ON t.book_id = b.book_id 
                        WHERE t.book_id = '$book_id' AND t.user_id = '$user_id' 
                        AND t.status = 'Borrowed' AND b.status = 'Borrowed'";
        $check = $conn->query($check_query);

        if ($check->num_rows > 0) {
            $transaction = $check->fetch_assoc();

            // Calculate late fee if any
            $due_date = new DateTime($transaction['due_date']);
            $today = new DateTime();
            $late_fee = 0;

            if ($today > $due_date) {
                $days_late = $today->diff($due_date)->days;
                $late_fee = $days_late * 1.00; // $1 per day late fee
            }

            // Update book status
            $stmt = $conn->prepare("UPDATE books SET status = 'Available' WHERE book_id = ?");
            $stmt->bind_param("s", $book_id);

            if ($stmt->execute()) {
                // Update transaction
                $trans_stmt = $conn->prepare("UPDATE transactions SET return_date = NOW(), status = 'Returned', late_fee = ? WHERE id = ?");
                $trans_stmt->bind_param("di", $late_fee, $transaction['id']);
                $trans_stmt->execute();
                $trans_stmt->close();

                $success_message = "Book returned successfully!";
                if ($late_fee > 0) {
                    $success_message .= " Late fee: $" . number_format($late_fee, 2);
                }
            } else {
                $error_message = "Error returning book: " . $conn->error;
            }

            $stmt->close();
        } else {
            $error_message = "Book is either not borrowed by you or does not exist!";
        }
    } else {
        $error_message = "Please enter a Book ID!";
    }
}

// Calculate statistics
$stats = $conn->query("SELECT 
                      (SELECT COUNT(*) FROM books) as total_books,
                      (SELECT COUNT(*) FROM books WHERE status = 'Available') as available_books,
                      (SELECT COUNT(*) FROM books WHERE status = 'Borrowed') as borrowed_books");

// Initialize default values
$total_books = 0;
$available_books = 0;
$borrowed_books = 0;

// Extract statistics if query was successful
if ($stats && $statistics = $stats->fetch_assoc()) {
    $total_books = $statistics['total_books'];
    $available_books = $statistics['available_books'];
    $borrowed_books = $statistics['borrowed_books'];
}

// Get all books for display in view section
$all_books = $conn->query("SELECT book_id, title, author, status FROM books ORDER BY title");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Book Borrowing Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --accent-color: #36b9cc;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --dark-color: #5a5c69;
            --light-bg: #f8f9fc;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 700;
        }

        .menu-card {
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            color: white;
            text-align: center;
            padding: 2rem;
            border-radius: 15px;
        }

        .menu-link {
            color: white;
            text-decoration: none;
            display: block;
            padding: 1.2rem;
            border-radius: 10px;
            margin: 0.7rem 0;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-link:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: scale(1.02);
            border-left: 4px solid white;
        }

        .status-available {
            color: var(--secondary-color);
            font-weight: bold;
        }

        .status-borrowed {
            color: var(--danger-color);
            font-weight: bold;
        }

        .stats-card {
            border-left: 4px solid;
        }

        .stats-card.primary {
            border-left-color: var(--primary-color);
        }

        .stats-card.success {
            border-left-color: var(--secondary-color);
        }

        .stats-card.danger {
            border-left-color: var(--danger-color);
        }

        .stats-icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
        }

        .welcome-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .welcome-text {
            color: var(--dark-color);
            font-size: 1.2rem;
            margin-bottom: 0;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #224abe;
            border-color: #224abe;
        }

        .table th {
            border-top: none;
            font-weight: 700;
            color: var(--dark-color);
        }

        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 1rem;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }

        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }

        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }

        .content-wrapper {
            width: 100%;
        }

        @media (min-width: 768px) {
            .sidebar {
                width: 250px;
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 100;
            }

            .content-wrapper {
                margin-left: 250px;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar d-none d-md-block">
            <div class="p-4">
                <h4 class="text-white text-center mb-4">
                    <i class="fas fa-book-reader"></i> Library System
                </h4>
                <hr class="bg-light">
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_action == 'menu') ? 'active' : ''; ?>" href="?action=menu">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_action == 'add') ? 'active' : ''; ?>" href="?action=add">
                            <i class="fas fa-plus-circle"></i> Add Book
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_action == 'view') ? 'active' : ''; ?>" href="?action=view">
                            <i class="fas fa-eye"></i> View Books
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_action == 'borrow') ? 'active' : ''; ?>" href="?action=borrow">
                            <i class="fas fa-hand-holding"></i> Borrow Book
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_action == 'return') ? 'active' : ''; ?>" href="?action=return">
                            <i class="fas fa-undo"></i> Return Book
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Header -->
            <div class="header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h1><i class="fas fa-book"></i> Library Book Borrowing Tracker</h1>
                            <p class="lead mb-0">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="index.php" class="btn btn-light me-2">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                            <a href="logout.php" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <!-- Welcome Message -->
                <div class="welcome-container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3>Hello, <?php echo htmlspecialchars($user_name); ?>!</h3>
                            <p class="welcome-text">Welcome to your library dashboard. Here you can manage books, track borrowings, and more.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary">
                                    <i class="fas fa-user-circle"></i> My Account
                                </button>
                                <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                                    <span class="visually-hidden">Toggle Dropdown</span>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a>
                                    <a class="dropdown-item" href="#"><i class="fas fa-history me-2"></i>Borrowing History</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Books</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_books; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-book fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card success h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Books</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $available_books; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card danger h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Borrowed Books</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $borrowed_books; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-hand-holding fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card h-100" style="border-left-color: var(--accent-color);">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">My Borrowed Books</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $my_borrowed = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id = '$user_id' AND status = 'Borrowed'");
                                            echo $my_borrowed->fetch_assoc()['count'];
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300 stats-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($current_action == 'menu' || empty($current_action)): ?>
                    <!-- Main Menu -->
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card menu-card">
                                <div class="card-header bg-transparent border-0 text-center">
                                    <h2><i class="fas fa-list"></i> Library Menu</h2>
                                    <p class="lead mb-0">Choose an operation</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <a href="?action=add" class="menu-link">
                                                <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                                <h5>Add New Book</h5>
                                                <small>Add a new book to the library</small>
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="?action=view" class="menu-link">
                                                <i class="fas fa-eye fa-2x mb-2"></i>
                                                <h5>View All Books</h5>
                                                <small>See all books in the library</small>
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="?action=borrow" class="menu-link">
                                                <i class="fas fa-hand-holding fa-2x mb-2"></i>
                                                <h5>Borrow a Book</h5>
                                                <small>Mark a book as borrowed</small>
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="?action=return" class="menu-link">
                                                <i class="fas fa-undo fa-2x mb-2"></i>
                                                <h5>Return a Book</h5>
                                                <small>Mark a book as available</small>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_action == 'add'): ?>
                    <!-- Add New Book -->
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h4><i class="fas fa-plus-circle"></i> Add New Book</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="app/add-books.php">
                                        <div class="mb-3">
                                            <label for="book_id" class="form-label">Book ID *</label>
                                            <input type="text" class="form-control" id="book_id" name="book_id"
                                                placeholder="Enter unique book ID" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="title" class="form-label">Book Title *</label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                placeholder="Enter book title" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="author" class="form-label">Author *</label>
                                            <input type="text" class="form-control" id="author" name="author"
                                                placeholder="Enter author name" required>
                                        </div>

                                        <button type="submit" name="add_book" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Add Book
                                        </button>
                                        <a href="?action=menu" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Back to Menu
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_action == 'view'): ?>
                    <!-- View All Books -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                    <h4><i class="fas fa-eye"></i> All Books</h4>
                                    <a href="?action=add" class="btn btn-light btn-sm">
                                        <i class="fas fa-plus"></i> Add New Book
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($total_books == 0): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-book fa-4x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Books Found</h5>
                                            <p class="text-muted">Add some books to your library.</p>
                                            <a href="?action=add" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Add First Book
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Book ID</th>
                                                        <th>Title</th>
                                                        <th>Author</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($book = $all_books->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($book['book_id']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                            <td>
                                                                <span class="status-<?php echo strtolower($book['status']); ?>">
                                                                    <i class="fas fa-<?php echo $book['status'] == 'Available' ? 'check-circle' : 'hand-holding'; ?>"></i>
                                                                    <?php echo $book['status']; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_action == 'borrow'): ?>
                    <!-- Borrow a Book -->
                    <div class="row">
                        <div class="col-lg-8">
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
                                        </div>

                                        <button type="submit" name="borrow_book" class="btn btn-warning">
                                            <i class="fas fa-hand-holding"></i> Borrow Book
                                        </button>
                                        <a href="?action=view" class="btn btn-secondary">
                                            <i class="fas fa-book"></i> View All Books
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6><i class="fas fa-books"></i> Available Books</h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php if ($available_books == 0): ?>
                                        <p class="text-muted">No books available for borrowing.</p>
                                    <?php else: ?>
                                        <?php 
                                        $available_books_query = $conn->query("SELECT book_id, title FROM books WHERE status = 'Available' ORDER BY title");
                                        while ($book = $available_books_query->fetch_assoc()): ?>
                                            <div class="mb-2 p-2 bg-light rounded">
                                                <strong><?php echo htmlspecialchars($book['book_id']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($book['title']); ?></small>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_action == 'return'): ?>
                    <!-- Return a Book -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h4><i class="fas fa-undo"></i> Return a Book</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="return_book_id" class="form-label">Enter Book ID to Return *</label>
                                            <input type="text" class="form-control" id="return_book_id" name="return_book_id"
                                                placeholder="Enter book ID" required>
                                        </div>

                                        <button type="submit" name="return_book" class="btn btn-success">
                                            <i class="fas fa-undo"></i> Return Book
                                        </button>
                                        <a href="?action=view" class="btn btn-secondary">
                                            <i class="fas fa-book"></i> View All Books
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-danger text-white">
                                    <h6><i class="fas fa-clock"></i> My Borrowed Books</h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php 
                                    $borrowed_books_query = $conn->query("SELECT b.book_id, b.title, t.borrow_date, t.due_date FROM books b JOIN transactions t ON b.book_id = t.book_id WHERE b.status = 'Borrowed' AND t.status = 'Borrowed' AND t.user_id = '".$_SESSION['user_id']."' ORDER BY t.borrow_date DESC");
                                    
                                    if ($borrowed_books_query->num_rows == 0): ?>
                                        <p class="text-muted">No books currently borrowed.</p>
                                    <?php else: ?>
                                        <?php while ($book = $borrowed_books_query->fetch_assoc()): 
                                            $due_date = new DateTime($book['due_date']);
                                            $today = new DateTime();
                                            $is_overdue = $today > $due_date;
                                        ?>
                                            <div class="mb-2 p-2 bg-light rounded <?php echo $is_overdue ? 'border border-danger' : ''; ?>">
                                                <strong><?php echo htmlspecialchars($book['book_id']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($book['title']); ?></small><br>
                                                <small class="text-muted">Borrowed: <?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></small><br>
                                                <small class="<?php echo $is_overdue ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                    Due: <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                                    <?php if ($is_overdue): ?>
                                                        <i class="fas fa-exclamation-triangle"></i> Overdue
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>

            <footer class="text-center py-4 mt-5 text-muted bg-white">
                <div class="container">
                    <p class="mb-0">
                        <i class="fas fa-book"></i> Library Book Borrowing Tracker &copy; <?php echo date('Y'); ?>
                    </p>
                </div>
            </footer>
        </div>
    </div>

    <!-- Toast HTML structure -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <i class="fas fa-check-circle me-2"></i>
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                <!-- Message will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Toast JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($toast_success_message)): ?>
                // Set the toast message
                document.getElementById('toastMessage').textContent = '<?php echo $toast_success_message; ?>';

                // Show the toast
                var successToast = new bootstrap.Toast(document.getElementById('successToast'));
                successToast.show();

                // Clean up URL by removing the success parameter
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url);
            <?php endif; ?>
        });
    </script>
</body>

</html>