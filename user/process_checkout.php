<?php
/**
 * File: user/process_checkout.php
 * Deskripsi: Proses checkout + Generate Virtual Account Number
 * Alur: Form → Proses → Generate VA → Simpan DB → Redirect ke Bill
 */

// Tampilkan error agar kita tahu kalau ada yang salah
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config.php';

// Cek keamanan
if (!isset($_SESSION['login']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Akses ditolak. Silakan login dan isi form checkout.');
}

$uid = (int)$_SESSION['id'];

// Ambil & sanitasi input dari form
$nama = trim($_POST['nama'] ?? '');
$telp = trim($_POST['telp'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');

// ✅ FIX: Terima metode_pembayaran (format: "M-Banking (BCA)" atau "E-Wallet (GoPay)")
$metode = trim($_POST['metode_pembayaran'] ?? 'M-Banking (BCA)');

$total = (float)($_POST['total_bayar'] ?? 0);

// Validasi data kosong
if (empty($nama) || empty($alamat)) {
    echo "Data tidak lengkap!";
    exit();
}

// ============================================================================
// ✅ FUNGSI: Generate Virtual Account Number
// ============================================================================
function generateVirtualAccount($bank_full_name, $order_id, $total) {
    // Mapping kode bank untuk Virtual Account
    $bank_codes = [
        'BCA' => '70015',
        'Mandiri' => '88508', 
        'BNI' => '88810',
        'BRI' => '20017',
        'CIMB' => '022',
        'GoPay' => '90088',
        'OVO' => '90099',
        'Dana' => '90077',
        'ShopeePay' => '90066',
        'LinkAja' => '90055'
    ];
    
    // Extract nama bank pendek dari format "M-Banking (BCA)"
    // Contoh input: "M-Banking (BCA)" → output: "BCA"
    preg_match('/\(([^)]+)\)/', $bank_full_name, $matches);
    $bank_short = isset($matches[1]) ? $matches[1] : 'BCA';
    
    // Ambil kode bank, default ke BCA jika tidak ditemukan
    $bank_code = $bank_codes[$bank_short] ?? '70015';
    
    // Generate VA Number: KodeBank + OrderID (5 digit, zero-padded)
    // Contoh: BCA (70015) + Order #4 (00004) = 7001500004
    $va_number = $bank_code . str_pad($order_id, 5, '0', STR_PAD_LEFT);
    
    return $va_number;
}

// ============================================================================
// 1. SIMPAN ORDER KE DATABASE (Status: pending_payment)
// ============================================================================

// Escape string untuk keamanan (mencegah SQL injection)
$nama_esc = mysqli_real_escape_string($koneksi, $nama);
$telp_esc = mysqli_real_escape_string($koneksi, $telp);
$alamat_esc = mysqli_real_escape_string($koneksi, $alamat);
$metode_esc = mysqli_real_escape_string($koneksi, $metode);

// Insert order dengan status 'pending_payment'
$insert_order = mysqli_query($koneksi, "
    INSERT INTO orders (
        user_id, 
        total_bayar, 
        tanggal_transaksi, 
        status, 
        nama_penerima, 
        no_telp, 
        alamat, 
        metode_pembayaran
    ) VALUES (
        $uid, 
        $total, 
        NOW(), 
        'pending_payment', 
        '$nama_esc', 
        '$telp_esc', 
        '$alamat_esc', 
        '$metode_esc'
    )
");

if (!$insert_order) {
    echo "Gagal menyimpan pesanan: " . mysqli_error($koneksi);
    exit();
}

// Ambil order_id yang baru saja dibuat
$oid = mysqli_insert_id($koneksi);

// ============================================================================
// 2. GENERATE & SIMPAN VIRTUAL ACCOUNT NUMBER
// ============================================================================

// Generate VA number berdasarkan bank yang dipilih
$va_number = generateVirtualAccount($metode, $oid, $total);

// Simpan VA number ke database (pastikan kolom virtual_account sudah ada)
mysqli_query($koneksi, "
    UPDATE orders 
    SET virtual_account = '$va_number',
        updated_at = NOW()
    WHERE id = $oid
");

// ============================================================================
// 3. PINDAHKAN CART KE ORDER_DETAILS & KURANGI STOK PRODUK
// ============================================================================

$cart = mysqli_query($koneksi, "
    SELECT c.*, p.harga, p.stok 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = $uid
");

while ($c = mysqli_fetch_assoc($cart)) {
    $sub = $c['quantity'] * $c['harga'];
    
    // Insert ke order_details
    mysqli_query($koneksi, "
        INSERT INTO order_details (
            order_id, 
            product_id, 
            quantity, 
            harga_satuan, 
            subtotal
        ) VALUES (
            $oid, 
            {$c['product_id']}, 
            {$c['quantity']}, 
            {$c['harga']}, 
            $sub
        )
    ");
    
    // Kurangi stok produk
    mysqli_query($koneksi, "
        UPDATE products 
        SET stok = stok - {$c['quantity']} 
        WHERE id = {$c['product_id']}
    ");
}

// ============================================================================
// 4. KOSONGKAN KERANJANG USER
// ============================================================================
mysqli_query($koneksi, "DELETE FROM cart WHERE user_id = $uid");

// ============================================================================
// 5. REDIRECT KE HALAMAN BILL (WAJIB ADA exit())
// ============================================================================
header('Location: bill.php?order_id=' . $oid);
exit();
?>  