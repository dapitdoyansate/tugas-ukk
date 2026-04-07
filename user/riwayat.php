<?php
/**
 * File: user/riwayat.php
 * Fitur: Tracking status pesanan visual untuk Toko Elektronik
 * Alur: Pesanan Dibuat → Pembayaran Diverifikasi → Dicek & Dikemas Aman → Dikirim via Kurir → Pesanan Diterima
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'user') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int)$_SESSION['id'];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE user_id = $user_id";
if ($search_query) {
    $where .= " AND (id LIKE '%$search_query%' OR nama_penerima LIKE '%$search_query%')";
}

// Ambil semua orders + items langsung dari PHP (tanpa AJAX)
$orders_query = mysqli_query($koneksi, "
    SELECT id, total_bayar, tanggal_transaksi, status, nama_penerima, metode_pembayaran, virtual_account
    FROM orders 
    $where 
    ORDER BY tanggal_transaksi DESC
");

$orders = [];
while($row = mysqli_fetch_assoc($orders_query)) {
    $items_query = mysqli_query($koneksi, "
        SELECT od.*, p.nama_produk, p.gambar 
        FROM order_details od 
        JOIN products p ON od.product_id = p.id 
        WHERE od.order_id = {$row['id']}
    ");
    $items = [];
    while($item = mysqli_fetch_assoc($items_query)) $items[] = $item;
    $row['items'] = $items;
    $orders[] = $row;
}

$orders_json = json_encode($orders);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - MasElektro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#2563eb;--primary-dark:#1d4ed8;--success:#10b981;--warning:#f59e0b;--gray-100:#f3f4f6;--gray-200:#e5e7eb;--gray-300:#d1d5db;--gray-600:#4b5563;--gray-800:#1f2937;--white:#fff;--shadow:0 1px 3px rgba(0,0,0,0.1);--radius:12px}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{background:var(--gray-100);color:var(--gray-800);line-height:1.6}
        
        .header{background:var(--white);padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:var(--shadow);position:sticky;top:0;z-index:100}
        .header__logo{display:flex;align-items:center;gap:0.75rem;text-decoration:none;color:var(--gray-800)}
        .logo__icon{width:40px;height:40px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:20px}
        .logo__text{font-size:20px;font-weight:700;color:var(--gray-800)}
        .logo__text span{color:var(--primary)}
        
        .container{max-width:800px;margin:2rem auto;padding:0 1.5rem}
        .page-title{font-size:22px;font-weight:700;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px}
        
        .search-box{background:var(--white);padding:1rem;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:1.5rem;display:flex;gap:10px}
        .search-input{flex:1;padding:10px 15px;border:2px solid var(--gray-200);border-radius:8px;font-size:14px;outline:none}
        .search-input:focus{border-color:var(--primary)}
        .search-btn{padding:10px 20px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer}
        .search-btn:hover{background:var(--primary-dark)}
        .clear-search{padding:10px 15px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none}
        
        .order-list{display:flex;flex-direction:column;gap:10px}
        .order-card{background:var(--white);border-radius:var(--radius);padding:1rem;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:.2s;text-decoration:none;color:inherit}
        .order-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.1)}
        .order-info{display:flex;flex-direction:column;gap:4px}
        .order-id{font-size:14px;font-weight:700;color:var(--gray-800)}
        .order-meta{font-size:12px;color:var(--gray-600)}
        .order-total{font-size:15px;font-weight:700;color:var(--primary)}
        .order-status{display:flex;flex-direction:column;align-items:flex-end;gap:8px}
        .status-badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        
        .empty-state{text-align:center;padding:3rem 2rem;background:var(--white);border-radius:var(--radius)}
        .empty-state i{font-size:48px;color:var(--gray-300);margin-bottom:1rem}
        
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:1000}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:12px;padding:20px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto;animation:fadeIn .2s ease}
        @keyframes fadeIn{from{opacity:0;transform:scale(0.98)}to{opacity:1;transform:scale(1)}}
        .modal-title{font-size:16px;font-weight:700;margin-bottom:15px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center}
        .modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-600)}
        .modal-row{display:flex;justify-content:space-between;padding:8px 0;font-size:13px;border-bottom:1px solid var(--gray-100)}
        .modal-row:last-child{border-bottom:none}
        .modal-label{color:var(--gray-600)}
        .modal-value{font-weight:600;color:var(--gray-800);text-align:right}
        .va-box{background:#f9fafb;padding:10px;border-radius:8px;margin:10px 0;text-align:center}
        .va-num{font-size:14px;font-weight:700;font-family:monospace;letter-spacing:1px}
        .total-box{background:#eff6ff;padding:12px;border-radius:8px;margin:10px 0;text-align:center}
        .total-amount{font-size:18px;font-weight:800;color:var(--primary)}
        
        /* ✅ TRACKING TIMELINE CSS */
        .tracking{margin:20px 0;padding:0 10px}
        .tracking-steps{display:flex;justify-content:space-between;position:relative;margin-bottom:25px}
        .tracking-steps::before{content:'';position:absolute;top:16px;left:0;right:0;height:3px;background:var(--gray-200);z-index:0}
        .tracking-step{display:flex;flex-direction:column;align-items:center;position:relative;z-index:1;width:20%}
        .step-icon{width:34px;height:34px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-300);font-size:14px;margin-bottom:6px;transition:.3s}
        .step-label{font-size:11px;color:var(--gray-400);text-align:center;font-weight:500}
        .tracking-step.completed .step-icon{background:var(--success);color:#fff}
        .tracking-step.completed .step-label{color:var(--success)}
        .tracking-step.active .step-icon{background:var(--primary);color:#fff;box-shadow:0 0 0 4px rgba(37,99,235,0.2)}
        .tracking-step.active .step-label{color:var(--primary);font-weight:700}
        .tracking-step.cancelled .step-icon{background:var(--danger);color:#fff}
        .tracking-step.cancelled .step-label{color:var(--danger)}
        
        .items-section{margin:15px 0}
        .items-title{font-size:13px;font-weight:700;margin-bottom:10px;color:var(--gray-800)}
        .item-row{display:flex;gap:10px;padding:10px;background:var(--gray-50);border-radius:8px;margin-bottom:8px}
        .item-img{width:50px;height:50px;background:var(--gray-200);border-radius:6px;overflow:hidden;flex-shrink:0}
        .item-img img{width:100%;height:100%;object-fit:cover}
        .item-info{flex:1}
        .item-name{font-size:12px;font-weight:600;margin-bottom:4px}
        .item-meta{font-size:11px;color:var(--gray-600)}
        .item-price{font-size:12px;font-weight:700;color:var(--primary);white-space:nowrap}
        
        @media(max-width:600px){.tracking-steps::before{top:14px}.step-icon{width:28px;height:28px;font-size:12px}.step-label{font-size:9px}.header{padding:0.75rem 1rem}.logo__text{font-size:18px}.logo__icon{width:36px;height:36px;font-size:18px}.order-card{flex-direction:column;align-items:flex-start;gap:10px}.order-status{align-items:flex-start;flex-direction:row;flex-wrap:wrap}.search-box{flex-direction:column}}
    </style>
</head>
<body>
    <header class="header">
        <a href="dashboard.php" class="header__logo">
            <div class="logo__icon">M</div>
            <div class="logo__text">Mas<span>Elektro</span></div>
        </a>
        <a href="dashboard.php" style="color:var(--primary);text-decoration:none;font-weight:600;font-size:14px">← Kembali</a>
    </header>

    <div class="container">
        <h1 class="page-title"><i class="fas fa-history"></i> Riwayat Pesanan</h1>
        
        <div class="search-box">
            <form method="GET" action="" style="display:flex;gap:10px;flex:1">
                <input type="text" name="search" class="search-input" placeholder="Cari berdasarkan Order ID atau Nama Penerima..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> Cari</button>
                <?php if($search_query): ?>
                <a href="riwayat.php" class="clear-search"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="order-list">
            <?php if(empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3 style="font-size:18px;margin-bottom:0.5rem"><?php echo $search_query ? 'Pesanan Tidak Ditemukan' : 'Belum Ada Pesanan'; ?></h3>
                    <p style="color:var(--gray-600);font-size:14px"><?php echo $search_query ? 'Coba kata kunci lain' : 'Mulai belanja untuk melihat riwayat'; ?></p>
                </div>
            <?php else: ?>
                <?php foreach($orders as $order): 
                    $badge = ['pending_payment'=>['bg'=>'#fef3c7','color'=>'#92400e','label'=>'⏳ Menunggu Bayar'],'paid'=>['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'💳 Sudah Bayar'],'processing'=>['bg'=>'#e0e7ff','color'=>'#3730a3','label'=>'🔄 Diproses'],'shipped'=>['bg'=>'#cffafe','color'=>'#155e75','label'=>'🚚 Dikirim'],'completed'=>['bg'=>'#d1fae5','color'=>'#065f46','label'=>'✅ Selesai'],'cancelled'=>['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'❌ Dibatalkan']];
                    $b = $badge[$order['status']] ?? $badge['pending_payment'];
                    $date = date('d M Y', strtotime($order['tanggal_transaksi']));
                ?>
                <div class="order-card" onclick="showDetail(<?php echo $order['id']; ?>)">
                    <div class="order-info">
                        <div class="order-id">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
                        <div class="order-meta"><?php echo $date; ?> • <?php echo htmlspecialchars($order['nama_penerima']); ?></div>
                        <div class="order-total">Rp. <?php echo number_format($order['total_bayar'], 0, ',', '.'); ?></div>
                    </div>
                    <div class="order-status">
                        <span class="status-badge" style="background:<?php echo $b['bg']; ?>;color:<?php echo $b['color']; ?>"><?php echo $b['label']; ?></span>
                        <span style="font-size:12px;color:var(--primary);font-weight:600">Detail →</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="modal">
        <div class="modal-content">
            <div class="modal-title">
                Detail Pesanan
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        const allOrders = <?php echo $orders_json; ?>;
        
        function showDetail(orderId) {
            const order = allOrders.find(o => o.id == orderId);
            if(!order) return alert('Order tidak ditemukan');
            renderModalContent(order);
            document.getElementById('modal').classList.add('show');
        }
        
        function renderModalContent(order) {
            // ✅ FIX: Label tracking khusus Toko Elektronik
            const statusMap = {
                'pending_payment': 'Pesanan Dibuat',
                'paid': 'Pembayaran Diverifikasi',
                'processing': 'Dicek & Dikemas Aman',
                'shipped': 'Dikirim via Kurir',
                'completed': 'Pesanan Diterima',
                'cancelled': 'Dibatalkan'
            };
            
            const statusOrder = ['pending_payment', 'paid', 'processing', 'shipped', 'completed'];
            const currentIdx = statusOrder.indexOf(order.status);
            const isCancelled = order.status === 'cancelled';
            
            // ✅ FIX: Tracking steps dengan icon & label elektronik
            let trackingHtml = '<div class="tracking"><div class="tracking-steps">';
            const steps = [
                {key:'pending_payment', icon:'fa-file-invoice', label:'Pesanan Dibuat'},
                {key:'paid', icon:'fa-shield-check', label:'Pembayaran Diverifikasi'},
                {key:'processing', icon:'fa-box-check', label:'Dicek & Dikemas Aman'},
                {key:'shipped', icon:'fa-truck-moving', label:'Dikirim via Kurir'},
                {key:'completed', icon:'fa-home', label:'Pesanan Diterima'}
            ];
            
            steps.forEach((step, i) => {
                let cls = '';
                if(isCancelled) {
                    cls = (i === 0) ? 'cancelled' : '';
                } else if(i < currentIdx) {
                    cls = 'completed';
                } else if(i === currentIdx) {
                    cls = 'active';
                }
                trackingHtml += `<div class="tracking-step ${cls}"><div class="step-icon"><i class="fas ${step.icon}"></i></div><div class="step-label">${step.label}</div></div>`;
            });
            trackingHtml += '</div></div>';
            
            const date = new Date(order.tanggal_transaksi);
            const dateStr = date.toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
            const vaHtml = order.virtual_account ? `<div class="va-box"><div style="font-size:11px;color:#6b7280">Virtual Account</div><div class="va-num">${order.virtual_account}</div></div>` : '';
            
            let itemsHtml = '';
            if(order.items && order.items.length > 0) {
                itemsHtml = '<div class="items-section"><div class="items-title">📦 Produk yang Dibel ('+order.items.length+' item)</div>';
                order.items.forEach(item => {
                    const imgUrl = item.gambar ? '../uploads/'+item.gambar : 'https://via.placeholder.com/50x50/2563eb/ffffff?text=No';
                    itemsHtml += '<div class="item-row"><div class="item-img"><img src="'+imgUrl+'" onerror="this.src=\'https://via.placeholder.com/50x50/2563eb/ffffff?text=No\'"></div><div class="item-info"><div class="item-name">'+item.nama_produk+'</div><div class="item-meta">Qty: '+item.quantity+' × Rp. '+Number(item.harga_satuan).toLocaleString('id-ID')+'</div></div><div class="item-price">Rp. '+Number(item.subtotal).toLocaleString('id-ID')+'</div></div>';
                });
                itemsHtml += '</div>';
            }
            
            document.getElementById('modalBody').innerHTML = `
                ${trackingHtml}
                <div class="modal-row"><span class="modal-label">Status Saat Ini</span><span class="modal-value" style="color:${isCancelled ? 'var(--danger)' : 'var(--primary)'};font-weight:700">${statusMap[order.status] || order.status}</span></div>
                <div class="modal-row"><span class="modal-label">Order ID</span><span class="modal-value">#${String(order.id).padStart(6,'0')}</span></div>
                <div class="modal-row"><span class="modal-label">Tanggal</span><span class="modal-value">${dateStr}</span></div>
                <div class="modal-row"><span class="modal-label">Penerima</span><span class="modal-value">${order.nama_penerima}</span></div>
                <div class="modal-row"><span class="modal-label">Metode</span><span class="modal-value">${order.metode_pembayaran}</span></div>
                ${vaHtml}
                ${itemsHtml}
                <div class="total-box"><div style="font-size:12px;color:#6b7280">Total Pembayaran</div><div class="total-amount">Rp. ${Number(order.total_bayar).toLocaleString('id-ID')}</div></div>
            `;
        }
        
        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        document.getElementById('modal').addEventListener('click', function(e) { if(e.target === this) closeModal(); });
        document.addEventListener('keydown', function(e) { if(e.key === 'Escape') closeModal(); });
    </script>
</body>
</html>