<?php
session_start();
require_once '../config.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) { header('Location: dashboard.php'); exit(); }

// Ambil data order + VA number
$order = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM orders WHERE id = $order_id"));
if (!$order) { header('Location: dashboard.php'); exit(); }

// Ambil item pesanan
$items = [];
$res = mysqli_query($koneksi, "SELECT od.*, p.nama_produk FROM order_details od JOIN products p ON od.product_id=p.id WHERE od.order_id=$order_id");
while($r = mysqli_fetch_assoc($res)) $items[] = $r;

// ✅ FIX: Extract bank name & VA number
$metode = $order['metode_pembayaran'] ?? 'M-Banking (BCA)';
$va_number = $order['virtual_account'] ?? '';

// Extract bank short name from "M-Banking (BCA)"
preg_match('/\(([^)]+)\)/', $metode, $matches);
$bank_name = $matches[1] ?? 'BCA';

// Bank instructions & logo
$bank_info = [
    'BCA' => ['name' => 'BCA Virtual Account', 'color' => '#0066cc', 'instr' => 'Login BCA Mobile → Transfer → Virtual Account'],
    'Mandiri' => ['name' => 'Mandiri Virtual Account', 'color' => '#00783c', 'instr' => 'Login Livin\' Mandiri → Transfer → VA'],
    'BNI' => ['name' => 'BNI Virtual Account', 'color' => '#e31e24', 'instr' => 'Login BNI Mobile → Transfer → Virtual Account'],
    'BRI' => ['name' => 'BRI Virtual Account', 'color' => '#008000', 'instr' => 'Login BRImo → Transfer → Virtual Account'],
    'CIMB' => ['name' => 'CIMB Virtual Account', 'color' => '#e30613', 'instr' => 'Login Octo Mobile → Transfer → VA'],
    'GoPay' => ['name' => 'GoPay', 'color' => '#00aa13', 'instr' => 'Buka Gojek → GoPay → Transfer'],
    'OVO' => ['name' => 'OVO', 'color' => '#4c3494', 'instr' => 'Buka OVO → Transfer → Input VA'],
    'Dana' => ['name' => 'Dana', 'color' => '#118eea', 'instr' => 'Buka Dana → Kirim → Input VA'],
    'ShopeePay' => ['name' => 'ShopeePay', 'color' => '#ee4d2d', 'instr' => 'Buka Shopee → ShopeePay → Transfer'],
    'LinkAja' => ['name' => 'LinkAja', 'color' => '#e31e24', 'instr' => 'Buka LinkAja → Transfer → Input VA']
];

$bank = $bank_info[$bank_name] ?? $bank_info['BCA'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - MasElektro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{background:#f3f4f6;min-height:100vh;padding:20px}
        .container{max-width:600px;margin:0 auto}
        
        /* Header */
        .header{text-align:center;padding:20px;background:#fff;border-radius:16px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        .header h1{font-size:24px;color:#1f2937;margin-bottom:5px}
        .header p{color:#6b7280;font-size:14px}
        
        /* VA Card */
        .va-card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:20px}
        .va-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:15px;border-bottom:2px dashed #e5e7eb}
        .va-logo{width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px}
        .va-title{font-size:18px;font-weight:700;color:#1f2937}
        .va-subtitle{font-size:13px;color:#6b7280}
        
        /* VA Number Display */
        .va-number{background:#f9fafb;padding:20px;border-radius:12px;text-align:center;margin-bottom:20px;border:2px dashed #e5e7eb}
        .va-number__label{font-size:13px;color:#6b7280;margin-bottom:8px}
        .va-number__value{font-size:28px;font-weight:800;color:#1f2937;letter-spacing:3px;font-family:monospace}
        .va-number__copy{margin-top:12px}
        .btn-copy{background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s}
        .btn-copy:hover{background:#1d4ed8}
        .btn-copy.copied{background:#10b981}
        
        /* Payment Instructions */
        .instructions{background:#f9fafb;padding:20px;border-radius:12px;margin-bottom:20px}
        .instructions h3{font-size:16px;font-weight:700;color:#1f2937;margin-bottom:12px;display:flex;align-items:center;gap:8px}
        .instructions ol{padding-left:20px;font-size:14px;color:#4b5563;line-height:1.8}
        .instructions li{margin-bottom:8px}
        .instructions .note{background:#fff3cd;color:#856404;padding:10px;border-radius:8px;font-size:13px;margin-top:12px;border-left:4px solid #ffc107}
        
        /* Order Summary */
        .summary{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px}
        .summary h3{font-size:16px;font-weight:700;color:#1f2937;margin-bottom:15px}
        .summary-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:14px}
        .summary-item:last-child{border-bottom:none}
        .summary-total{font-size:18px;font-weight:800;color:#2563eb;margin-top:10px;padding-top:10px;border-top:2px solid #2563eb}
        
        /* Confirm Payment Button */
        .btn-confirm{width:100%;padding:16px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;transition:.3s;display:flex;align-items:center;justify-content:center;gap:8px}
        .btn-confirm:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,0.4)}
        .btn-confirm:disabled{background:#9ca3af;cursor:not-allowed;transform:none}
        
        /* Status Badge */
        .status{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600}
        .status-pending{background:#fef3c7;color:#92400e}
        .status-paid{background:#d1fae5;color:#065f46}
        
        /* Modal Konfirmasi */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:16px;padding:30px;max-width:400px;width:90%;text-align:center;animation:slideIn .3s ease}
        @keyframes slideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
        .modal-icon{width:70px;height:70px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;color:#10b981;font-size:32px}
        .modal-title{font-size:20px;font-weight:700;color:#1f2937;margin-bottom:10px}
        .modal-text{color:#6b7280;font-size:14px;margin-bottom:25px}
        .modal-btns{display:flex;gap:10px}
        .modal-btn{flex:1;padding:12px;border:none;border-radius:10px;font-weight:600;cursor:pointer}
        .modal-btn-primary{background:#2563eb;color:#fff}
        .modal-btn-secondary{background:#e5e7eb;color:#374151}
        
        @media(max-width:480px){.va-number__value{font-size:22px;letter-spacing:1px}.btn-confirm{padding:14px}}
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>💳 Pembayaran</h1>
            <p>Selesaikan pembayaran untuk melanjutkan pesanan</p>
            <span class="status status-pending"><i class="fas fa-clock"></i> Menunggu Pembayaran</span>
        </div>

        <!-- Virtual Account Card -->
        <div class="va-card">
            <div class="va-header">
                <div class="va-logo" style="background:<?php echo $bank['color']; ?>">
                    <?php echo strtoupper(substr($bank_name, 0, 3)); ?>
                </div>
                <div>
                    <div class="va-title"><?php echo $bank['name']; ?></div>
                    <div class="va-subtitle">Transfer tepat sesuai nominal</div>
                </div>
            </div>

            <!-- VA Number Display -->
            <div class="va-number">
                <div class="va-number__label">Nomor Virtual Account</div>
                <div class="va-number__value" id="vaNumber"><?php echo $va_number; ?></div>
                <button class="btn-copy" id="btnCopy" onclick="copyVA()">
                    <i class="fas fa-copy"></i> Salin Nomor VA
                </button>
            </div>

            <!-- Payment Instructions -->
            <div class="instructions">
                <h3><i class="fas fa-list-check"></i> Cara Pembayaran</h3>
                <ol>
                    <li><?php echo $bank['instr']; ?></li>
                    <li>Pilih menu <strong>Transfer ke Virtual Account</strong></li>
                    <li>Masukkan nomor VA: <strong><?php echo $va_number; ?></strong></li>
                    <li>Transfer dengan nominal <strong>tepat</strong>: <strong>Rp. <?php echo number_format($order['total_bayar'], 0, ',', '.'); ?></strong></li>
                    <li>Selesaikan transaksi dan simpan bukti pembayaran</li>
                </ol>
                <div class="note">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Penting:</strong> Transfer dengan nominal <u>tepat</u> agar sistem dapat mendeteksi pembayaran Anda secara otomatis.
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="summary">
            <h3>📦 Ringkasan Pesanan #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></h3>
            <?php foreach($items as $i): ?>
            <div class="summary-item">
                <span><?php echo htmlspecialchars($i['nama_produk']); ?> (x<?php echo $i['quantity']; ?>)</span>
                <span>Rp. <?php echo number_format($i['subtotal'], 0, ',', '.'); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="summary-item summary-total">
                <span>Total Pembayaran</span>
                <span>Rp. <?php echo number_format($order['total_bayar'], 0, ',', '.'); ?></span>
            </div>
        </div>

        <!-- Confirm Payment Button -->
        <button class="btn-confirm" onclick="showConfirmModal()">
            <i class="fas fa-check-circle"></i> Saya Sudah Bayar
        </button>

        <p style="text-align:center;color:#6b7280;font-size:13px;margin-top:15px">
            <i class="fas fa-shield-alt"></i> Pesanan akan diproses setelah pembayaran terverifikasi
        </p>
    </div>

    <!-- Modal Konfirmasi Pembayaran -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-check"></i></div>
            <h3 class="modal-title">Konfirmasi Pembayaran</h3>
            <p class="modal-text">
                Apakah Anda sudah melakukan transfer ke Virtual Account <strong><?php echo $va_number; ?></strong> dengan nominal <strong>Rp. <?php echo number_format($order['total_bayar'], 0, ',', '.'); ?></strong>?
            </p>
            <div class="modal-btns">
                <button class="modal-btn modal-btn-secondary" onclick="closeModal()">Batal</button>
                <button class="modal-btn modal-btn-primary" onclick="confirmPayment()">Ya, Sudah Bayar</button>
            </div>
        </div>
    </div>

    <script>
        // Copy VA Number to Clipboard
        function copyVA() {
            const vaNumber = document.getElementById('vaNumber').textContent;
            navigator.clipboard.writeText(vaNumber).then(() => {
                const btn = document.getElementById('btnCopy');
                btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Salin Nomor VA';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }

        // Show Confirm Modal
        function showConfirmModal() {
            document.getElementById('confirmModal').classList.add('show');
        }

        // Close Modal
        function closeModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }

        // Confirm Payment & Redirect to Success
        function confirmPayment() {
            const orderId = <?php echo $order_id; ?>;
            
            // ✅ FIX: Update status order ke 'paid' (untuk demo)
            // Dalam produksi, ini akan diverifikasi oleh payment gateway
            fetch('update_payment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `order_id=${orderId}&action=confirm_payment`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect ke halaman sukses
                    window.location.href = 'checkout_success.php?order_id=' + orderId;
                } else {
                    alert('⚠️ ' + data.message);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                // Fallback: langsung redirect untuk demo
                window.location.href = 'checkout_success.php?order_id=' + orderId;
            });
            
            closeModal();
        }

        // Close modal when clicking outside
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>