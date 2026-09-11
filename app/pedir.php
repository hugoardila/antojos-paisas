<?php
session_start();
require_once __DIR__ . '/config.php';

define('EPAYCO_CUST_ID', getenv('EPAYCO_CUST_ID') ?: '');
define('EPAYCO_P_KEY', getenv('EPAYCO_P_KEY') ?: '');
const EPAYCO_TEST_REQUEST = 'FALSE';

function normalize_phone(string $phone): string {
    return preg_replace('/\D+/', '', $phone);
}

function next_web_order_number(): string {
    return 'WEB-' . date('ymd-His');
}

function store_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'tecnoxpert.com';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/antojos/pedir.php', '?') ?: '/antojos/pedir.php';
    if (str_ends_with($path, '/')) {
        $path .= 'pedir.php';
    }
    return $scheme . '://' . $host . $path;
}

function epayco_payment_signature(string $invoice, int $amount): string {
    return md5(EPAYCO_CUST_ID . '^' . EPAYCO_P_KEY . '^' . $invoice . '^' . $amount . '^COP');
}

function epayco_response_signature(array $data): string {
    return hash('sha256', EPAYCO_CUST_ID . '^' . EPAYCO_P_KEY . '^' . ($data['x_ref_payco'] ?? '') . '^' . ($data['x_transaction_id'] ?? '') . '^' . ($data['x_amount'] ?? '') . '^' . ($data['x_currency_code'] ?? ''));
}

function process_epayco_order_confirmation(array $data): void {
    $invoice = (string)($data['x_id_invoice'] ?? '');
    if ($invoice === '') return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND source = 'web' AND payment_method = 'epayco' FOR UPDATE");
        $stmt->execute([$invoice]);
        $order = $stmt->fetch();
        if (!$order) {
            $pdo->commit();
            return;
        }
        $amount = (int)round((float)($data['x_amount'] ?? $data['x_amount_ok'] ?? 0));
        $expected = (int)round((float)$order['total']);
        $isAccepted = (string)($data['x_cod_response'] ?? '') === '1' || strtolower((string)($data['x_response'] ?? '')) === 'aceptada';
        $newStatus = $isAccepted && $amount === $expected ? 'pagado' : ((string)($data['x_cod_response'] ?? '') === '3' ? 'pendiente' : 'rechazada');
        $pdo->prepare('UPDATE orders SET status=?, epayco_ref=?, epayco_transaction_id=?, epayco_response=? WHERE id=?')
            ->execute([$newStatus, $data['x_ref_payco'] ?? null, $data['x_transaction_id'] ?? null, json_encode($data, JSON_UNESCAPED_UNICODE), (int)$order['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function active_products(): array {
    return db()->query("SELECT * FROM products WHERE active=1 ORDER BY sort_order, category, name")->fetchAll();
}

function active_addons(): array {
    return db()->query("SELECT * FROM product_addons WHERE active=1 ORDER BY sort_order, category, name")->fetchAll();
}

$flash = null;
$createdOrder = null;

if (($_GET['r'] ?? '') === 'epayco_confirmation') {
    $data = array_merge($_GET, $_POST);
    $expected = epayco_response_signature($data);
    if (!hash_equals($expected, (string)($data['x_signature'] ?? ''))) {
        http_response_code(400);
        echo 'INVALID_SIGNATURE';
        exit;
    }
    process_epayco_order_confirmation($data);
    echo 'OK';
    exit;
}

if (($_GET['r'] ?? '') === 'epayco_response') {
    $response = $_GET['x_response'] ?? $_GET['x_response_reason_text'] ?? 'Respuesta recibida';
    $accepted = strtolower((string)$response) === 'aceptada' || (string)($_GET['x_cod_response'] ?? '') === '1';
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pago ePayco | Antojos Paisas</title><link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link href="assets/customer.css?v=20260620c" rel="stylesheet"></head><body><main class="confirmation"><i class="bi <?php echo $accepted ? 'bi-check2-circle' : 'bi-info-circle'; ?>"></i><h2><?php echo $accepted ? 'Pago recibido' : 'Pago en revision'; ?></h2><p><?php echo e((string)$response); ?></p><a href="pedir.php">Volver a la tienda</a></main></body></html>
<?php exit; }

if (($_GET['r'] ?? '') === 'epayco_pay') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare("SELECT * FROM orders WHERE id=? AND source='web' AND payment_method='epayco' LIMIT 1");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        $flash = 'Pedido no encontrado para pago ePayco.';
    } else {
        $amount = (int)round((float)$order['total']);
        $invoice = (string)$order['order_number'];
        $signature = epayco_payment_signature($invoice, $amount);
        $baseUrl = store_base_url();
        ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Enviar a ePayco | Antojos Paisas</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link href="assets/customer.css?v=20260620c" rel="stylesheet">
</head>
<body>
<main class="confirmation">
  <i class="bi bi-credit-card"></i>
  <h2>Pago seguro ePayco</h2>
  <p>Te estamos enviando al checkout para pagar <?php echo money($amount); ?>.</p>
  <form id="epaycoForm" method="post" action="https://secure.payco.co/checkout.php">
    <input type="hidden" name="p_cust_id_cliente" value="<?php echo e(EPAYCO_CUST_ID); ?>">
    <input type="hidden" name="p_key" value="<?php echo e(EPAYCO_P_KEY); ?>">
    <input type="hidden" name="p_id_invoice" value="<?php echo e($invoice); ?>">
    <input type="hidden" name="p_description" value="Pedido Antojos Paisas <?php echo e($invoice); ?>">
    <input type="hidden" name="p_currency_code" value="COP">
    <input type="hidden" name="p_amount" value="<?php echo $amount; ?>">
    <input type="hidden" name="p_tax" value="0">
    <input type="hidden" name="p_amount_base" value="<?php echo $amount; ?>">
    <input type="hidden" name="p_test_request" value="<?php echo e(EPAYCO_TEST_REQUEST); ?>">
    <input type="hidden" name="p_url_response" value="<?php echo e($baseUrl . '?r=epayco_response'); ?>">
    <input type="hidden" name="p_url_confirmation" value="<?php echo e($baseUrl . '?r=epayco_confirmation'); ?>">
    <input type="hidden" name="p_confirm_method" value="POST">
    <input type="hidden" name="p_signature" value="<?php echo e($signature); ?>">
    <input type="hidden" name="p_extra1" value="<?php echo (int)$order['id']; ?>">
    <input type="hidden" name="p_billing_name" value="<?php echo e((string)$order['customer_name']); ?>">
    <input type="hidden" name="p_billing_email" value="cliente@antojospaisas.com">
    <input type="hidden" name="p_billing_phone" value="<?php echo e((string)$order['customer_phone']); ?>">
    <input type="hidden" name="p_billing_cellphone" value="<?php echo e((string)$order['customer_phone']); ?>">
    <input type="hidden" name="p_billing_document" value="<?php echo e((string)$order['customer_phone']); ?>">
    <button class="checkout-btn">Continuar a ePayco</button>
  </form>
  <a href="pedir.php">Cancelar y volver</a>
</main>
<script>setTimeout(function(){document.getElementById('epaycoForm').submit();},700);</script>
</body>
</html>
<?php exit; } }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items) || count($items) === 0) {
            throw new RuntimeException('Agrega productos al pedido.');
        }

        $name = trim($_POST['customer_name'] ?? '');
        $phone = normalize_phone($_POST['customer_phone'] ?? '');
        $fulfillment = ($_POST['fulfillment'] ?? 'domicilio') === 'recoger' ? 'recoger' : 'domicilio';
        $address = trim($_POST['address'] ?? '');
        $neighborhood = trim($_POST['neighborhood'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $payment = trim($_POST['payment_method'] ?? 'efectivo');
        if (!in_array($payment, ['efectivo','nequi','daviplata','transferencia','epayco'], true)) {
            $payment = 'efectivo';
        }

        if ($name === '' || strlen($phone) < 7) {
            throw new RuntimeException('Nombre y telefono son obligatorios.');
        }
        if ($fulfillment === 'domicilio' && $address === '') {
            throw new RuntimeException('Para domicilio necesitamos la direccion.');
        }

        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO customers (name, phone, address, neighborhood, notes)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              name=VALUES(name), address=VALUES(address), neighborhood=VALUES(neighborhood),
              notes=VALUES(notes), updated_at=NOW()
        ");
        $stmt->execute([$name, $phone, $address ?: null, $neighborhood ?: null, $notes ?: null]);
        $customerId = (int)$pdo->lastInsertId();
        if ($customerId === 0) {
            $find = $pdo->prepare('SELECT id FROM customers WHERE phone=?');
            $find->execute([$phone]);
            $customerId = (int)$find->fetchColumn();
        }

        $subtotal = 0;
        $prepared = [];
        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id=? AND active=1');
        $addonStmt = $pdo->prepare('SELECT * FROM product_addons WHERE id=? AND active=1');

        foreach ($items as $item) {
            $productId = (int)($item['id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch();
            if (!$product) {
                throw new RuntimeException('Uno de los productos ya no esta disponible.');
            }
            $addons = [];
            $addonsTotalPerUnit = 0;
            foreach (($item['addons'] ?? []) as $addonId) {
                $addonStmt->execute([(int)$addonId]);
                $addon = $addonStmt->fetch();
                if (!$addon) continue;
                $addons[] = $addon;
                $addonsTotalPerUnit += (float)$addon['price'];
            }
            $line = $qty * ((float)$product['price'] + $addonsTotalPerUnit);
            $subtotal += $line;
            $prepared[] = [$product, $qty, $addons, $line];
        }

        $deliveryFee = $fulfillment === 'domicilio' ? (float)setting('delivery_fee', '0') : 0;
        $total = $subtotal + $deliveryFee;
        $orderType = $fulfillment === 'domicilio' ? 'domicilio' : 'para_llevar';
        $tableName = $fulfillment === 'domicilio' ? 'Domicilio' : 'Recoge en local';

        $stmt = $pdo->prepare("
            INSERT INTO orders (
              order_number, customer_name, customer_id, customer_phone, order_type, table_name,
              delivery_address, delivery_neighborhood, payment_method, subtotal, discount,
              delivery_fee, total, status, source, fulfillment_status, notes, user_id, created_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NOW())
        ");
        $stmt->execute([
            next_web_order_number(), $name, $customerId, $phone, $orderType, $tableName,
            $address ?: null, $neighborhood ?: null, $payment, $subtotal, 0,
            $deliveryFee, $total, 'pendiente', 'web', 'recibido', $notes ?: null
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, unit_cost, line_total) VALUES (?,?,?,?,?,?,?)');
        $itemAddonStmt = $pdo->prepare('INSERT INTO order_item_addons (order_item_id, addon_id, addon_name, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)');
        foreach ($prepared as [$product, $qty, $addons, $line]) {
            $itemStmt->execute([$orderId, $product['id'], $product['name'], $qty, $product['price'], $product['cost'], $line]);
            $orderItemId = (int)$pdo->lastInsertId();
            foreach ($addons as $addon) {
                $addonLine = $qty * (float)$addon['price'];
                $itemAddonStmt->execute([$orderItemId, $addon['id'], $addon['name'], $qty, $addon['price'], $addonLine]);
            }
        }

        $pdo->prepare('UPDATE customers SET orders_count=orders_count+1, total_spent=total_spent+?, last_order_at=NOW() WHERE id=?')->execute([$total, $customerId]);
        $pdo->commit();
        if ($payment === 'epayco') {
            header('Location: pedir.php?r=epayco_pay&id=' . $orderId);
            exit;
        }
        $createdOrder = ['id' => $orderId, 'number' => $pdo->query('SELECT order_number FROM orders WHERE id=' . (int)$orderId)->fetchColumn(), 'total' => $total];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $flash = $e->getMessage();
    }
}

$products = active_products();
$addons = active_addons();
$categories = [];
foreach ($products as $p) {
    $categories[$p['category'] ?: 'General'][] = $p;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pedir | Antojos Paisas</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/customer.css?v=20260620d" rel="stylesheet">
</head>
<body>
  <header class="store-hero">
    <nav>
      <img src="assets/logo-antojos.svg" alt="Antojos Paisas">
      <a href="index.php">Administracion</a>
    </nav>
    <section>
      <span>Arepas paisas & chorizos</span>
      <h1>Antojos Paisas</h1>
      <p>Arma tu pedido, agrega adiciones y envialo directo al negocio. Nosotros lo confirmamos por WhatsApp o llamada.</p>
    </section>
  </header>

  <?php if ($createdOrder): ?>
    <main class="confirmation">
      <i class="bi bi-check2-circle"></i>
      <h2>Pedido recibido</h2>
      <p>Tu orden <strong><?php echo e($createdOrder['number']); ?></strong> quedo registrada por <?php echo money($createdOrder['total']); ?>.</p>
      <a href="pedir.php">Crear otro pedido</a>
    </main>
  <?php else: ?>
  <main class="store-layout">
    <section class="menu">
      <?php if ($flash): ?><div class="alert"><?php echo e($flash); ?></div><?php endif; ?>
      <?php foreach ($categories as $category => $items): ?>
        <div class="category-block">
          <h2><?php echo e($category); ?></h2>
          <div class="product-grid">
            <?php foreach ($items as $p): ?>
              <article class="menu-card">
                <?php if (!empty($p['image_path'])): ?>
                  <img src="<?php echo e($p['image_path']); ?>" alt="<?php echo e($p['name']); ?>">
                <?php else: ?>
                  <div class="menu-card__icon"><i class="bi <?php echo e($p['icon'] ?: 'bi-basket'); ?>"></i></div>
                <?php endif; ?>
                <div>
                  <small><?php echo e($p['category']); ?></small>
                  <h3><?php echo e($p['name']); ?></h3>
                  <strong><?php echo money($p['price']); ?></strong>
                </div>
                <button type="button" class="add-product" data-id="<?php echo $p['id']; ?>" data-name="<?php echo e($p['name']); ?>" data-price="<?php echo $p['price']; ?>">Agregar</button>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="category-block addons-menu-block">
        <h2>Adicionales</h2>
        <div class="product-grid">
          <?php foreach ($addons as $a): ?>
            <article class="menu-card addon-menu-card">
              <?php if (!empty($a['image_path'] ?? '')): ?>
                <img src="<?php echo e($a['image_path']); ?>" alt="<?php echo e($a['name']); ?>">
              <?php else: ?>
                <div class="menu-card__icon"><i class="bi bi-plus-circle"></i></div>
              <?php endif; ?>
              <div>
                <small><?php echo e($a['category']); ?></small>
                <h3><?php echo e($a['name']); ?></h3>
                <strong><?php echo money($a['price']); ?></strong>
              </div>
              <button type="button" class="pick-addon" data-addon-id="<?php echo $a['id']; ?>">Agregar al siguiente producto</button>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <aside class="cart-panel">
      <form method="post" id="customerOrderForm">
        <input type="hidden" name="items_json" id="itemsJson">
        <h2>Tu pedido</h2>
        <div id="cartItems" class="cart-items empty">Agrega productos del menu</div>
        <section class="addons-box">
          <h3>Adiciones disponibles</h3>
          <div class="addons-list">
            <?php foreach ($addons as $a): ?>
              <label><input type="checkbox" value="<?php echo $a['id']; ?>" data-name="<?php echo e($a['name']); ?>" data-price="<?php echo $a['price']; ?>"> <span><?php echo e($a['name']); ?></span><b><?php echo money($a['price']); ?></b></label>
            <?php endforeach; ?>
          </div>
          <small>Selecciona adiciones antes de tocar “Agregar” en un producto.</small>
        </section>
        <div class="totals"><span>Subtotal</span><strong id="subtotalText">$0</strong></div>
        <div class="totals"><span>Domicilio</span><strong id="deliveryText">$0</strong></div>
        <div class="totals grand"><span>Total</span><strong id="totalText">$0</strong></div>
        <div class="form-grid">
          <label>Nombre completo<input name="customer_name" required></label>
          <label>Telefono WhatsApp<input name="customer_phone" inputmode="tel" required></label>
          <label class="wide">Como quieres recibirlo?
            <select name="fulfillment" id="fulfillment">
              <option value="domicilio">Domicilio</option>
              <option value="recoger">Recoger en el local</option>
            </select>
          </label>
          <label class="wide delivery-field">Direccion<input name="address" placeholder="Barrio, calle, casa, referencia"></label>
          <label class="delivery-field">Barrio<input name="neighborhood"></label>
          <label>Pago
            <select name="payment_method">
              <option value="efectivo">Efectivo</option>
              <option value="nequi">Nequi</option>
              <option value="daviplata">Daviplata</option>
              <option value="transferencia">Transferencia</option>
              <option value="epayco">ePayco</option>
            </select>
          </label>
          <label class="wide">Notas<textarea name="notes" rows="2" placeholder="Sin cebolla, mas salsa, punto de entrega..."></textarea></label>
        </div>
        <button class="checkout-btn">Enviar pedido</button>
      </form>
    </aside>
  </main>
  <?php endif; ?>
  <script>
    window.deliveryFee = <?php echo (float)setting('delivery_fee', '0'); ?>;
  </script>
  <script src="assets/customer.js?v=20260620d"></script>
</body>
</html>
