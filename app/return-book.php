<?php
require_once '../config.php';
require_once '../auth.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Initialize messages
$success_message = '';
$error_message = '';

// Borrow a book
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['borrow_book'])) {
    $book_id = sanitize($conn, $_POST['borrow_book_id']);
    $user_id = $_SESSION['user_id'];

    if (!empty($book_id)) {
        $check = $conn->query("SELECT * FROM books WHERE book_id = '$book_id' AND status = 'Available'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE books SET status = 'Borrowed' WHERE book_id = ?");
            $stmt->bind_param("s", $book_id);
            if ($stmt->execute()) {
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
        $stmt_check = $conn->prepare("SELECT t.*, b.title FROM transactions t 
                                     JOIN books b ON t.book_id = b.book_id 
                                     WHERE t.book_id = ? AND t.user_id = ? AND t.status = 'Borrowed' AND b.status = 'Borrowed'");
        $stmt_check->bind_param("ss", $book_id, $user_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result && $result->num_rows > 0) {
            $transaction = $result->fetch_assoc();

            $due_date = new DateTime($transaction['due_date']);
            $today = new DateTime();
            $late_fee = 0;

            if ($today > $due_date) {
                $days_late = $today->diff($due_date)->days;
                $late_fee = $days_late * 1.00; // $1/day
            }

            $stmt_update = $conn->prepare("UPDATE books SET status = 'Available' WHERE book_id = ?");
            $stmt_update->bind_param("s", $book_id);
            if ($stmt_update->execute()) {
                $trans_stmt = $conn->prepare("UPDATE transactions SET return_date = NOW(), status = 'Returned', late_fee = ? WHERE id = ?");
                $trans_stmt->bind_param("di", $late_fee, $transaction['id']);
                $trans_stmt->execute();
                $trans_stmt->close();

                $success_message = "Book '" . htmlspecialchars($transaction['title']) . "' returned successfully!";
                if ($late_fee > 0) {
                    $success_message .= " Late fee: $" . number_format($late_fee, 2);
                }
            } else {
                $error_message = "Error returning book: " . $conn->error;
            }
            $stmt_update->close();
        } else {
            $error_message = "Book is either not borrowed by you or does not exist!";
        }

        $stmt_check->close();
    } else {
        $error_message = "Please enter a Book ID!";
    }
}

// Get borrowed books by current user
$borrowed_books_stmt = $conn->prepare("SELECT b.book_id, b.title, t.borrow_date, t.due_date 
                                      FROM books b 
                                      JOIN transactions t ON b.book_id = t.book_id 
                                      WHERE t.user_id = ? AND t.status = 'Borrowed' AND b.status = 'Borrowed'
                                      ORDER BY t.borrow_date DESC");
$borrowed_books_stmt->bind_param("s", $_SESSION['user_id']);
$borrowed_books_stmt->execute();
$borrowed_books_result = $borrowed_books_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Return Book - Library System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .header { background: linear-gradient(135deg, #6f42c1, #e83e8c); color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; border-radius: 10px; }
    .overdue { border-left: 4px solid #dc3545; background-color: #f8d7da; }
    .due-soon { border-left: 4px solid #ffc107; background-color: #fff3cd; }
</style>
</head>
<body>
<div class="header text-center">
    <div class="container">
        <h1><i class="fas fa-undo"></i> Return a Book</h1>
        <p class="lead">Return borrowed books to the library</p>
        <a href="/dashboard.php" class="btn btn-light me-2"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

<div class="container">
    <!-- Messages -->
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Return Book Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white"><h4><i class="fas fa-undo"></i> Return a Book</h4></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="return_book_id" class="form-label">Enter Book ID to Return *</label>
                            <input type="text" class="form-control" id="return_book_id" name="return_book_id" placeholder="Enter book ID" required>
                            <div class="form-text">Enter the Book ID from your borrowed books list on the right.</div>
                        </div>
                        <button type="submit" name="return_book" class="btn btn-success"><i class="fas fa-undo"></i> Return Book</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Borrowed Books List -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-danger text-white"><h6><i class="fas fa-list"></i> Your Borrowed Books</h6></div>
                <div class="card-body">
                    <?php if ($borrowed_books_result->num_rows == 0): ?>
                        <p class="text-muted">You have no borrowed books.</p>
                        <a href="dashboard.php?action=borrow" class="btn btn-primary btn-sm"><i class="fas fa-hand-holding"></i> Borrow Books</a>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php while ($book = $borrowed_books_result->fetch_assoc()): 
                                $due_date = new DateTime($book['due_date']);
                                $today = new DateTime();
                                $is_overdue = $today > $due_date;
                                $days_diff = $today->diff($due_date)->days;
                                $is_due_soon = !$is_overdue && $days_diff <= 2;
                                $card_class = $is_overdue ? 'overdue' : ($is_due_soon ? 'due-soon' : '');
                            ?>
                                <div class="mb-2 p-3 bg-light rounded border <?php echo $card_class; ?>">
                                    <div>
                                        <strong class="text-primary"><?php echo htmlspecialchars($book['book_id']); ?></strong><br>
                                        <small class="fw-bold"><?php echo htmlspecialchars($book['title']); ?></small><br>
                                        <small class="text-muted"><i class="fas fa-calendar"></i> Borrowed: <?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></small><br>
                                        <small class="<?php echo $is_overdue ? 'text-danger fw-bold' : ($is_due_soon ? 'text-warning fw-bold' : 'text-muted'); ?>">
                                            <i class="fas fa-clock"></i> Due: <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                            <?php if ($is_overdue): ?> <br><i class="fas fa-exclamation-triangle"></i> OVERDUE! <?php elseif ($is_due_soon): ?> <br><i class="fas fa-exclamation-circle"></i> Due soon! <?php endif; ?>
                                        </small>
                                    </div>
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
