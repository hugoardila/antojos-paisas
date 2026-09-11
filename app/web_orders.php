<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['antojos_user'])) { header('Location: index.php?r=login'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['fulfillment_status'] ?? 'recibido';
    $allowed = ['recibido','confirmado','preparando','en_camino','listo','entregado','cancelado'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        $stmt = db()->prepare('UPDATE orders SET fulfillment_status=?, status=IF(?, "pagado", status) WHERE id=?');
        $stmt->execute([$status, $status === 'entregado' ? 1 : 0, $id]);
    }
    header('Location: web_orders.php'); exit;
}

$orders = db()->query("SELECT o.*, c.orders_count, c.total_spent FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.source='web' ORDER BY o.created_at DESC LIMIT 100")->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pedidos web | Antojos Paisas</title><link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet"><link href="assets/customer.css?v=20260620c" rel="stylesheet"></head><body class="admin-orders">
<header class="orders-top"><img src="assets/logo-antojos.svg" alt=""><div><h1>Pedidos web</h1><p>Clientes, domicilios, adiciones y estados de preparacion.</p></div><a href="index.php">Volver al sistema</a><a href="pedir.php" target="_blank">Ver tienda</a></header>
<main class="orders-board">
<?php foreach ($orders as $o): ?>
  <?php $it=db()->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$o['id']]); $items=$it->fetchAll(); ?>
  <article class="order-card">
    <div class="order-head"><div><strong><?php echo e($o['order_number']); ?></strong><span><?php echo e($o['created_at']); ?></span></div><b><?php echo money($o['total']); ?></b></div>
    <div class="customer-line"><i class="bi bi-person"></i><span><?php echo e($o['customer_name']); ?></span><a href="https://wa.me/<?php echo e($o['customer_phone']); ?>" target="_blank"><?php echo e($o['customer_phone']); ?></a></div>
    <div class="customer-line"><i class="bi bi-geo-alt"></i><span><?php echo e(($o['order_type']==='domicilio' ? $o['delivery_address'] : 'Recoge en local') ?: 'Sin direccion'); ?></span></div>
    <ul class="order-products">
      <?php foreach ($items as $item): ?>
        <?php $ad=db()->prepare('SELECT * FROM order_item_addons WHERE order_item_id=?'); $ad->execute([$item['id']]); $adds=$ad->fetchAll(); ?>
        <li><strong><?php echo number_format($item['quantity']); ?> x <?php echo e($item['product_name']); ?></strong><?php if($adds): ?><small>Adiciones: <?php echo e(implode(', ', array_map(fn($a)=>$a['addon_name'], $adds))); ?></small><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if($o['notes']): ?><p class="order-notes"><?php echo e($o['notes']); ?></p><?php endif; ?>
    <form method="post" class="status-row"><input type="hidden" name="id" value="<?php echo $o['id']; ?>"><select name="fulfillment_status"><?php foreach(['recibido'=>'Recibido','confirmado'=>'Confirmado','preparando'=>'Preparando','en_camino'=>'En camino','listo'=>'Listo','entregado'=>'Entregado','cancelado'=>'Cancelado'] as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $o['fulfillment_status']===$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?></select><button>Actualizar</button></form>
    <?php if($o['fulfillment_status']==='confirmado'): ?><a class="print-ticket-btn" href="index.php?r=ticket&id=<?php echo $o['id']; ?>&print=1" target="_blank"><i class="bi bi-printer"></i> Imprimir ticket</a><?php endif; ?>
  </article>
<?php endforeach; ?>
<?php if(!$orders): ?><div class="empty-orders">Aun no hay pedidos web.</div><?php endif; ?>
</main></body></html>
