<?php
session_start();
require_once '../config.php';

// 1. Cek Login
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header('Location: ../auth/login.php'); exit();
}

$uid = (int)$_SESSION['id'];

// 2. Ambil Data User (Untuk Autofill)
$user = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_lengkap, no_hp, alamat FROM users WHERE id=$uid"));

// 3. Ambil Item & Hitung Total OTOMATIS
$res_items = mysqli_query($koneksi, "SELECT c.quantity, p.nama_produk, p.harga FROM cart c JOIN products p ON c.product_id=p.id WHERE c.user_id=$uid");
$items = [];
$total = 0;

while($row = mysqli_fetch_assoc($res_items)) {
    $row['subtotal'] = $row['harga'] * $row['quantity'];
    $total += $row['subtotal'];
    $items[] = $row;
}

if(empty($items)) { header('Location: keranjang.php'); exit(); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MasElektro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--p:#2563eb;--pd:#1d4ed8;--g1:#f3f4f6;--g2:#e5e7eb;--g6:#4b5563;--g8:#1f2937;--sh:0 1px 3px rgba(0,0,0,.1);--r:12px}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{background:var(--g1);color:var(--g8);padding:20px}
        .wrap{max-width:1000px;margin:0 auto;display:grid;grid-template-columns:1.2fr 1fr;gap:20px}
        .box{background:#fff;padding:25px;border-radius:var(--r);box-shadow:var(--sh)}
        h2{margin-bottom:15px;color:var(--g8);font-size:20px;display:flex;align-items:center;gap:10px}
        h2 i{color:var(--p)}
        .fg{margin-bottom:15px}
        label{display:block;font-weight:600;margin-bottom:5px;font-size:14px;color:var(--g6)}
        input,textarea{width:100%;padding:12px;border:2px solid var(--g2);border-radius:8px;font-size:14px;transition:.3s}
        input:focus,textarea:focus{border-color:var(--p);outline:none}
        
        /* Payment Options */
        .pay{padding:15px;border:2px solid var(--g2);border-radius:8px;margin:8px 0;cursor:pointer;display:flex;align-items:center;gap:10px;transition:.2s}
        .pay.active{border-color:var(--p);background:#eff6ff}
        .pay input{width:auto;margin:0}
        
        /* Nested Payment Details */
        .pay-details{margin-left:20px;margin-top:10px;padding:15px;background:var(--g1);border-radius:8px;display:none}
        .pay-details.show{display:block}
        .pay-details label{display:block;margin:8px 0;font-size:13px}
        .pay-details .pay{padding:10px 15px;margin:5px 0;border-width:1px}
        .pay-details .pay.active{border-color:var(--p);background:#fff}
        .pay-details small{color:var(--g6)}
        
        .btn{width:100%;padding:15px;background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;margin-top:20px;transition:.3s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(37,99,235,0.4)}
        .si{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--g1);font-size:14px}
        .tot{font-size:22px;font-weight:800;color:var(--p);margin-top:15px;padding-top:15px;border-top:2px solid var(--p);display:flex;justify-content:space-between}
        .back{display:inline-block;margin-bottom:20px;color:var(--p);text-decoration:none;font-weight:600}
        @media(max-width:768px){.wrap{grid-template-columns:1fr}}
    </style>
</head>
<body>

<div style="max-width:1000px;margin:0 auto">
    <a href="keranjang.php" class="back"><i class="fas fa-arrow-left"></i> Kembali ke Keranjang</a>
</div>

<div class="wrap">
    <!-- FORM DATA PEMBELI -->
    <div class="box">
        <h2><i class="fas fa-truck"></i> Informasi Pengiriman</h2>
        
        <form method="POST" action="process_checkout.php">
            
            <div class="fg">
                <label>Nama Penerima *</label>
                <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>" placeholder="Masukkan nama lengkap" required>
            </div>

            <div class="fg">
                <label>No. Telepon *</label>
                <input type="tel" name="telp" value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>" placeholder="08xxxxxxxxxx" required>
            </div>

            <div class="fg">
                <label>Alamat Lengkap *</label>
                <textarea name="alamat" rows="3" placeholder="Jalan, No. Rumah, RT/RW, Kecamatan" required><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
            </div>
            
            <!-- ✅ METODE PEMBAYARAN DENGAN PILIHAN SPESIFIK -->
            <h2 style="margin-top:25px"><i class="fas fa-credit-card"></i> Metode Pembayaran</h2>
            
            <!-- M-Banking Option -->
            <label class="pay active" id="label-mbanking" onclick="togglePayment('m-banking')">
                <input type="radio" name="metode_utama" value="m-banking" checked>
                <div><strong>M-Banking</strong><br><small style="color:var(--g6)">Transfer via Mobile Banking</small></div>
            </label>
            
            <!-- Bank Options (Nested) -->
            <div class="pay-details show" id="bank-options">
                <label style="font-weight:700;margin-bottom:10px;display:block">Pilih Bank:</label>
                
                <label class="pay active" onclick="selectDetail('BCA')">
                    <input type="radio" name="metode_detail" value="BCA" checked>
                    <span><strong>BCA</strong><br><small>Mobile Banking BCA</small></span>
                </label>
                <label class="pay" onclick="selectDetail('Mandiri')">
                    <input type="radio" name="metode_detail" value="Mandiri">
                    <span><strong>Mandiri</strong><br><small>Livin' by Mandiri</small></span>
                </label>
                <label class="pay" onclick="selectDetail('BNI')">
                    <input type="radio" name="metode_detail" value="BNI">
                    <span><strong>BNI</strong><br><small>BNI Mobile Banking</small></span>
                </label>
                <label class="pay" onclick="selectDetail('BRI')">
                    <input type="radio" name="metode_detail" value="BRI">
                    <span><strong>BRI</strong><br><small>BRImo</small></span>
                </label>
                <label class="pay" onclick="selectDetail('CIMB')">
                    <input type="radio" name="metode_detail" value="CIMB">
                    <span><strong>CIMB Niaga</strong><br><small>Octo Mobile</small></span>
                </label>
            </div>
            
            <!-- E-Wallet Option -->
            <label class="pay" id="label-ewallet" onclick="togglePayment('e-wallet')">
                <input type="radio" name="metode_utama" value="e-wallet">
                <div><strong>E-Wallet</strong><br><small style="color:var(--g6)">GoPay, OVO, Dana, ShopeePay</small></div>
            </label>
            
            <!-- E-Wallet Options (Nested) -->
            <div class="pay-details" id="ewallet-options">
                <label style="font-weight:700;margin-bottom:10px;display:block">Pilih E-Wallet:</label>
                
                <label class="pay" onclick="selectDetail('GoPay')">
                    <input type="radio" name="metode_detail" value="GoPay">
                    <span><strong>GoPay</strong><br><small>Gojek</small></span>
                </label>
                <label class="pay" onclick="selectDetail('OVO')">
                    <input type="radio" name="metode_detail" value="OVO">
                    <span><strong>OVO</strong><br><small>OVO Premier</small></span>
                </label>
                <label class="pay" onclick="selectDetail('Dana')">
                    <input type="radio" name="metode_detail" value="Dana">
                    <span><strong>Dana</strong><br><small>Dana Indonesia</small></span>
                </label>
                <label class="pay" onclick="selectDetail('ShopeePay')">
                    <input type="radio" name="metode_detail" value="ShopeePay">
                    <span><strong>ShopeePay</strong><br><small>Shopee</small></span>
                </label>
                <label class="pay" onclick="selectDetail('LinkAja')">
                    <input type="radio" name="metode_detail" value="LinkAja">
                    <span><strong>LinkAja</strong><br><small>LinkAja Syariah</small></span>
                </label>
            </div>
            
            <!-- Hidden input untuk metode lengkap -->
            <input type="hidden" name="metode_pembayaran" id="metode_lengkap" value="M-Banking (BCA)">
            <input type="hidden" name="total_bayar" value="<?php echo $total; ?>">
            
            <button type="submit" class="btn"><i class="fas fa-lock"></i> Konfirmasi & Bayar</button>
        </form>
    </div>

    <!-- RINGKASAN PESANAN -->
    <div class="box">
        <h2><i class="fas fa-box-open"></i> Ringkasan Pesanan</h2>
        <?php foreach($items as $i): ?>
        <div class="si">
            <div><strong><?php echo htmlspecialchars($i['nama_produk']); ?></strong><br><small>Qty: <?php echo $i['quantity']; ?></small></div>
            <div style="font-weight:700">Rp. <?php echo number_format($i['subtotal'],0,',','.'); ?></div>
        </div>
        <?php endforeach; ?>
        
        <div class="tot">
            <span>Total</span>
            <span>Rp. <?php echo number_format($total,0,',','.'); ?></span>
        </div>
    </div>
</div>

<script>
// Toggle between M-Banking and E-Wallet
function togglePayment(type) {
    const bankOptions = document.getElementById('bank-options');
    const ewalletOptions = document.getElementById('ewallet-options');
    const labelMBanking = document.getElementById('label-mbanking');
    const labelEWallet = document.getElementById('label-ewallet');
    
    if (type === 'm-banking') {
        bankOptions.classList.add('show');
        ewalletOptions.classList.remove('show');
        labelMBanking.classList.add('active');
        labelEWallet.classList.remove('active');
    } else if (type === 'e-wallet') {
        bankOptions.classList.remove('show');
        ewalletOptions.classList.add('show');
        labelMBanking.classList.remove('active');
        labelEWallet.classList.add('active');
    }
    updateMetodeLengkap();
}

// Select specific bank/e-wallet
function selectDetail(value) {
    // Update visual active state
    document.querySelectorAll('.pay-details .pay').forEach(opt => {
        opt.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // Update hidden input value
    document.querySelector('input[name="metode_detail"]').value = value;
    updateMetodeLengkap();
}

// Update the combined payment method
function updateMetodeLengkap() {
    const utama = document.querySelector('input[name="metode_utama"]:checked').value;
    const detail = document.querySelector('input[name="metode_detail"]:checked').value;
    const labelUtama = utama === 'm-banking' ? 'M-Banking' : 'E-Wallet';
    
    document.getElementById('metode_lengkap').value = labelUtama + ' (' + detail + ')';
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    updateMetodeLengkap();
});

// Update on form submit (fallback)
document.querySelector('form').addEventListener('submit', function() {
    updateMetodeLengkap();
});
</script>
</body>
</html>