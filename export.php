<?php
session_start();

// Check if there are books to export
if (!isset($_SESSION['books']) || empty($_SESSION['books'])) {
    header("Location: index.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="library_books_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, array('Book ID', 'Title', 'Author', 'Status', 'Borrowed Date'));

// Write book data
foreach ($_SESSION['books'] as $book) {
    $borrowed_date = isset($book['borrowed_date']) && $book['borrowed_date'] ? 
                     date('Y-m-d H:i:s', strtotime($book['borrowed_date'])) : 'N/A';
                     
    fputcsv($output, array(
        $book['id'],
        $book['title'],
        $book['author'],
        $book['status'],
        $borrowed_date
    ));
}

fclose($output);
exit();
?>