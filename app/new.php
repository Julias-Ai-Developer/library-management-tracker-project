<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Books - Library Management System</title>
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
                        <a class="nav-link active" href="add-books.php">
                            <i class="fas fa-plus-circle"></i> Add New Book
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-books.php">
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
                    <h1><i class="fas fa-plus-circle me-2"></i> Add New Books</h1>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <!-- Add Book Form -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle me-2"></i> Add New Book
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="book_id" class="form-label">Book ID</label>
                                    <input type="text" class="form-control" id="book_id" name="book_id" value="<?php echo isset($book_id) ? $book_id : ''; ?>" required>
                                    <small class="text-muted">Unique identifier for the book (e.g., B001)</small>
                                </div>
                                <div class="col-md-8">
                                    <label for="title" class="form-label">Book Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($title) ? $title : ''; ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="author" name="author" value="<?php echo isset($author) ? $author : ''; ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Book
                            </button>
                            <a href="manage-books.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-list"></i> View All Books
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Recently Added Books -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-clock me-2"></i> Recently Added Books
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get recently added books
                                    $result = $conn->query("SELECT * FROM books ORDER BY created_at DESC LIMIT 5");
                                    
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
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>No books added yet</td></tr>";
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