<?php

include '../config/config.php';

date_default_timezone_set('Asia/Jakarta');

/**
 *  @var $connection PDO
 */

// Debug: Cek semua data yang dikirimkan
print_r($_POST);
print_r($_FILES);
error_log("Debugging API: Data POST - " . json_encode($_POST));
error_log("Debugging API: Data FILES - " . json_encode($_FILES));

// Cek metode request
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    sendResponse(400, "POST method required", false);
    exit();
}

// Input data
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$price = isset($_POST['price']) ? (int)$_POST['price'] : null;
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
$status = $stock > 0 ? 'Ready' : 'No Stock';
$created_at = date('Y-m-d H:i:s');
$updated_at = date('Y-m-d H:i:s');
$picture = $_FILES['picture'] ?? null;

// Debug: Cek nilai input yang diproses
error_log("Nama: $name, Harga: $price, Kategori: $category_id, Stok: $stock");

// Aktifkan error reporting (hanya untuk debugging, jangan di produksi)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Debug: Cek semua data yang diterima dari request
error_log("DEBUG: Data yang diterima: " . json_encode($_POST));

// Validasi input
if (empty($name)) {
    error_log("ERROR: Nama produk kosong!");
    sendResponse(400, "Nama produk harus diisi", false);
}

if (empty($description)) {
    error_log("ERROR: Deskripsi produk kosong!");
    sendResponse(400, "Deskripsi harus diisi", false);
}

if (empty($price) || $price <= 0) {
    error_log("ERROR: Harga produk tidak valid! Price: " . json_encode($price));
    sendResponse(400, "Harga harus angka positif", false);
}

if (empty($category_id)) {
    error_log("ERROR: Kategori produk kosong!");
    sendResponse(400, "Kategori produk harus dipilih", false);
}

if ($stock < 0) {
    error_log("ERROR: Stok produk negatif! Stock: " . json_encode($stock));
    sendResponse(400, "Stok tidak boleh negatif", false);
}

if (empty($picture) || $picture['error'] != UPLOAD_ERR_OK) {
    error_log("ERROR: Gambar produk tidak diunggah atau terjadi error! Picture: " . json_encode($picture));
    sendResponse(400, "Gambar produk harus diunggah", false);
}

// Debug: Jika semua validasi lolos
error_log("DEBUG: Semua data valid, lanjutkan proses.");

// Debug: Cek detail gambar yang diunggah
error_log("File name: " . $picture['name'] . ", Size: " . $picture['size'] . " bytes");

// Cek format gambar
$allowedTypes = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif'];
$fileMimeType = mime_content_type($picture['tmp_name']);
if (!in_array($fileMimeType, $allowedTypes)) {
    sendResponse(400, "Format file tidak valid. Hanya JPG, PNG, dan GIF yang diperbolehkan.", false);
}
if ($picture['size'] > 5 * 1024 * 1024) {
    sendResponse(400, "Ukuran file terlalu besar. Maksimal 5MB.", false);
}

try {
    $transactionStarted = false;

    // Debug: Cek apakah kategori ada di database
    $categoryQuery = "SELECT COUNT(*) FROM category WHERE id = :category_id";
    $categoryStmt = $connection->prepare($categoryQuery);
    $categoryStmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
    $categoryStmt->execute();
    $categoryExists = $categoryStmt->fetchColumn();

    error_log("Kategori ditemukan: " . $categoryExists);
    var_dump($categoryExists);

    if ($categoryExists == 0) {
        sendResponse(400, "Kategori tidak ditemukan", false);
    }

    // Debug: Buat direktori jika belum ada
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        sendResponse(500, "Gagal membuat direktori upload", false);
    }

    $uniqueName = uniqid() . '-' . htmlspecialchars(basename($picture['name']));
    $uploadFile = realpath($uploadDir) . DIRECTORY_SEPARATOR . $uniqueName;

    // Debug: Cek lokasi file tujuan
    error_log("File upload path: " . $uploadFile);

    if (!move_uploaded_file($picture['tmp_name'], $uploadFile)) {
        throw new Exception("Gagal mengunggah gambar.");
    }

    $connection->beginTransaction();
    $transactionStarted = true;

    // Debug: Insert ke database
    error_log("Memasukkan produk ke database...");
    $insertProduct = "INSERT INTO product 
        (name, description, price, picture, status, stock, category_id, created_at, updated_at) 
        VALUES 
        (:name, :description, :price, :picture, :status, :stock, :category_id, :created_at, :updated_at)";
    $statement = $connection->prepare($insertProduct);

    $statement->bindValue(":name", $name);
    $statement->bindValue(":description", $description);
    $statement->bindValue(":price", $price, PDO::PARAM_INT);
    $statement->bindValue(":picture", $uniqueName);
    $statement->bindValue(":status", $status);
    $statement->bindValue(":stock", $stock, PDO::PARAM_INT);
    $statement->bindValue(":category_id", $category_id, PDO::PARAM_INT);
    $statement->bindValue(":created_at", $created_at);
    $statement->bindValue(":updated_at", $updated_at);

    if ($statement->execute()) {
        // Cek apakah insert berhasil
        error_log("Query insert berhasil!");
    
        // Ambil ID terakhir dengan metode alternatif
        $productQuery = "SELECT id FROM product ORDER BY id DESC LIMIT 1";
        $productStmt = $connection->prepare($productQuery);
        $productStmt->execute();
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
        if ($product) {
            $product_id = $product['id'];
            error_log("Last Insert ID via SELECT: " . $product_id);
        } else {
            error_log("Gagal mendapatkan last insert ID!");
            sendResponse(400, "Produk berhasil ditambahkan, tapi ID tidak ditemukan", false);
        }
    
        $connection->commit();
        $transactionStarted = false;
        // $productQuery = "SELECT * FROM product WHERE id = :product_id";
        // $productStmt = $connection->prepare($productQuery);
        // $productStmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);
        // $productStmt->execute();
        // $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        $productQuery = "SELECT * FROM product WHERE id = :product_id";
        $productStmt = $connection->prepare($productQuery);
        $productStmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);

        if ($productStmt->execute()) { 
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                $debug = [
                    "post_data" => $_POST, // Data yang dikirim dari Postman
                    "file_data" => $_FILES, // Data file yang diunggah
                    "category_exists" => isset($categoryExists) ? $categoryExists : "Tidak dicek",
                    "file_upload_path" => isset($uploadFile) ? $uploadFile : "File tidak diunggah"
                ];
                
                // Tambahkan debug ke dalam response
                $data = [
                    "product" => $product,
                    "debug" => $debug
                ];
                
                // Kirim response dengan debug
                sendResponse(200, "Produk berhasil ditambahkan", true, $data);                
            } else {
                sendResponse(200, "Produk berhasil ditambahkan, tapi tidak bisa mengambil datanya", true, [
                    "debug" => ["product_id" => $product_id, "file_upload" => $uploadFile]
                ]);
            }
        } else {
            sendResponse(400, "Gagal mengambil data produk", false, ["error" => $productStmt->errorInfo()]);
        }

        error_log("Produk berhasil ditambahkan: " . json_encode($product));

        sendResponse(200, "Produk berhasil ditambahkan", true, [
            'product' => $product,
            'debug' => [
                'file_upload' => $uploadFile,
                'category_exists' => $categoryExists,
            ]
        ]);
    } else {
        throw new Exception($statement->errorInfo()[2]);
    }
} catch (Exception $exception) {
    if (isset($transactionStarted) && $transactionStarted) {
        $connection->rollBack();
    }
    error_log("Error: " . $exception->getMessage());
    sendResponse(400, "Error: " . $exception->getMessage(), false);
}

function sendResponse($code, $message, $status, $data = null)
{
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode([
        'meta' => [
            'message' => $message,
            'status' => $status,
        ],
        'data' => $data
    ]);
    exit();
}
