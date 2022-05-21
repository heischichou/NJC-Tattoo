<?php
  session_name("sess_id");
  session_start();
  if(!isset($_SESSION['user']['user_id']) || strcasecmp($_SESSION['user']['user_type'], "User") != 0){
    Header("Location: ./index.php");
    die();
  } else {
    require_once '../api/api.php';
    $api = new api();
    $client_id = $api->sanitize_data($_SESSION['user']['client_id'], 'string');
    $order_id = $api->sanitize_data($_SESSION['order']['order_id'], 'string');
    $order_date = $_SESSION['order']['order_date'];
    $total = number_format($api->sanitize_data($_SESSION['order']['amount_due_total'], "float"), 2, '.', '');
    $incentive = $api->sanitize_data($_SESSION['order']['incentive'], 'string');
  }

  // navigation guard
  if(empty($_SESSION['order']['order_id'])){
    $_SESSION['res'] = "Illegal navigation error: You cannot checkout without an ongoing workorder.";
    Header("Location: ./orders.php");
  } else {
    try {
      // retrieving workorder details
      $statement = $api->prepare("SELECT client_fname, client_lname FROM (workorder JOIN client ON workorder.client_id=client.client_id) WHERE workorder.order_id=? AND workorder.client_id=?");
      if($statement===false){
        throw new Exception('prepare() error: ' . $conn->errno . ' - ' . $conn->error);
      }

      $mysqli_checks = $api->bind_params($statement, "ss", array($order_id, $client_id));
      if($mysqli_checks===false){
        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
      }

      $mysqli_checks = $api->execute($statement);
      if($mysqli_checks===false){
        throw new Exception('Execute error: The prepared statement could not be executed.');
      }

      $order = $api->get_result($statement);
      if($order===false){
        throw new Exception('get_result() error: Getting result set from statement failed.');
      }

      if($api->num_rows($order) > 0){
        $workorder = $api->fetch_assoc($order);
        $first_name = $api->sanitize_data($workorder['client_fname'], "string");
        $last_name = $api->sanitize_data($workorder['client_lname'], "string");
      }

      $api->free_result($statement);
      $mysqli_checks = $api->close($statement);
      ($mysqli_checks===false) ? throw new Exception('The prepared statement could not be closed.') : $statement = null;

      // retrieving order items
      $statement = $api->prepare("SELECT order_item.item_id, tattoo_name, tattoo_image, tattoo_price, tattoo_quantity, order_item.tattoo_width, order_item.tattoo_height, paid, item_status, amount_addon FROM ((order_item INNER JOIN tattoo ON order_item.tattoo_id=tattoo.tattoo_id) LEFT JOIN reservation ON order_item.item_id=reservation.item_id) WHERE order_id=? ORDER BY item_status ASC, paid ASC");
      if($statement===false){
        throw new Exception('prepare() error: ' . $conn->errno . ' - ' . $conn->error);
      }

      $mysqli_checks = $api->bind_params($statement, "s", $order_id);
      if($mysqli_checks===false){
        throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
      }

      $mysqli_checks = $api->execute($statement);
      if($mysqli_checks===false){
        throw new Exception('Execute error: The prepared statement could not be executed.');
      }

      $items = $api->get_result($statement);
      if($items===false){
        throw new Exception('get_result() error: Getting result set from statement failed.');
      }

      $api->free_result($statement);
      $mysqli_checks = $api->close($statement);
      ($mysqli_checks===false) ? throw new Exception('The prepared statement could not be closed.') : $statement = null;

      echo (strcasecmp($incentive, "15% Discount") == 0) ? "<script>var total = ". $total . " * .15;</script>" : "<script>var total = ". $total .";</script>";
      echo (strcasecmp($incentive, "15% Discount") == 0) ? "<script>const discounted = true;</script>" : "<script>const discounted = false;</script>";
    } catch (Exception $e) {
      $_SESSION['res'] = $e->getMessage();
      Header("Location: ./orders.php");
      exit();
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once '../common/meta.php'; ?>
  <link href="../style/checkout.css" rel="stylesheet" scoped>
  <title>Order Checkout | NJC Tattoo</title>
</head>
<body>
  <?php require_once '../common/header.php'; ?>
  <div class="Checkout content">
    <form id="Checkout__form" action="../scripts/php/queries.php" method="POST">
      <div class="Checkout__header">
        <h2 class="fw-bold display-3">Checkout</h2>
        <p class="d-inline fs-5 text-muted">Pay for your ongoing tattoo orders here. Tick the checkboxes of the items you wish to include in your payment.</p>
      </div>
      <?php
        $item_count = 0;

        if($api->num_rows($items) > 0){
          while($item = $api->fetch_assoc($items)){
            if((strcasecmp($item['item_status'], "Standing") == 0 && strcasecmp($item['paid'], "Unpaid") == 0) || (in_array($item['item_status'], array("Reserved", "Applied")) && in_array($item['paid'], array("Unpaid", "Partially Paid")))){
              $item_count++;
              $item_id = $api->sanitize_data($item['item_id'], "string");
              $tattoo_name = $api->sanitize_data($item['tattoo_name'], "string");
              $tattoo_image = $api->sanitize_data($item['tattoo_image'], "string");
              $price = number_format($api->sanitize_data($item['tattoo_price'], "float"), 2, '.', '');
              $quantity = $api->sanitize_data($item['tattoo_quantity'], "int");
              $width = $api->sanitize_data($item['tattoo_width'], "int");
              $height = $api->sanitize_data($item['tattoo_height'], "int");
              $paid = $api->sanitize_data($item['paid'], "string");
              $status = $api->sanitize_data($item['item_status'], "string");
              $addon = number_format($api->sanitize_data($item['amount_addon'], "float"), 2, '.', '');
      ?>
      <div class="Checkout__list__item">
        <div class="Checkout__list__item__preview">
          <div class="me-4">
            <input type="hidden" class="d-none" name="index[]" value="<?php echo $item_id; ?>" />
            <input type="checkbox" class="form-check-input p-2" name="item[]" value="<?php echo $item_id; ?>" checked/>
          </div>
          <!-- tattoo image -->
          <div class="Checkout__list__item__preview__image shadow-sm" style="background-image: url(<?php echo $tattoo_image; ?>)"></div>
        </div>
        <div class="Checkout__list__item__details">
          <div class="row my-5">
            <!-- tattoo name -->
            <div class="col">
              <label class="form-label fw-semibold">Item</label>
              <p><?php echo $width . "x" . $height . " " . $tattoo_name; ?></p>
            </div>
            <!-- item status -->
            <div class="col">
              <label for="status" class="form-label fw-semibold">Item Status</label>
              <p><?php echo $status; ?></p>
            </div>
            <!-- payment status -->
            <div class="col">
              <label class="form-label fw-semibold">Payment Status</label>
              <p><?php echo $paid; ?></p>
            </div>
          </div>
          <div class="row my-5">
            <!-- quantity -->
            <div class="col">
              <label for="quantity" class="form-label fw-semibold">Quantity</label>
              <input type="hidden" class="d-none" name="quantity[]" min="<?php echo $quantity; ?>" max="<?php echo $quantity; ?>" value="<?php echo $quantity; ?>"/>
              <input type="number" class="form-control" name="checkout_quantity[]" min="1" max="<?php echo $quantity; ?>" value="<?php echo $quantity; ?>"/>
              <label class="error-message quantity_err d-none"><span class="material-icons-outlined fs-6 me-1">info</span><span></span></label>
            </div>
            <!-- price -->
            <div class="col">
              <label for="quantity" class="form-label fw-semibold">Price</label>
              <p class="prices">₱<?php echo $price; ?></p>
            </div>
            <!-- amount_addon -->
            <div class="col">
              <label for="status" class="form-label fw-semibold">Reservation Addon</label>
              <p class="addons"><?php echo ($addon == 0) ? "N/A" : "₱" . $addon; ?></p>
            </div>
          </div>
        </div>
      </div>
      <?php }}} 
        if($item_count == 0){ ?>
          <div class="border-bottom px-5 py-8 no-select">
            <h1 class="display-3 fst-italic text-muted">No items available for payment.</h1>
          </div>
      <?php } ?>
      <div class="Checkout__summary">
        <div class="row">
          <div class="col">
            <!-- order id -->
            <label class="form-label fw-semibold">Order ID</label>
            <p class="w-auto"><?php echo $order_id; ?></p>
          </div>
          <?php
            $timestamp = explode(' ', $api->sanitize_data($order_date, 'string'));
            $date = date("M:d:Y", strtotime($timestamp[0]));
            $time = date("g:i A", strtotime($timestamp[1]));
            $date = explode(':', $date);
          ?>
          <div class="col">
            <!-- order date -->
            <label class="form-label fw-semibold">Placed on</label>
            <p class="w-auto"><?php echo $api->sanitize_data($date[0], 'string') . " " . $api->sanitize_data($date[1], 'int') . ", " . $api->sanitize_data($date[2], 'int') . ", " . $api->sanitize_data($time, 'string') ?></p>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <!-- incentive -->
            <label class="form-label fw-semibold">Discounted?</label>
            <p class="<?php echo (strcasecmp($incentive, "15% Discount") == 0) ? "text-success" : "text-muted"; ?> fw-semibold" id="discount"><?php echo (strcasecmp($incentive, "15% Discount") == 0) ? "Yes" : "No"; ?></p>
          </div>
          <div class="col">
            <!-- amount due total -->
            <label for="status" class="form-label fw-semibold">Amount Due Total</label>
            <p id="total">₱<?php echo number_format($api->sanitize_data($total, "float"), 2, '.', ''); ?></p>
          </div>
        </div>
      </div>
      <hr class="mb-5" />
      <div class="Checkout__billing-form">
        <div class="row my-4">
          <h4 class="mb-3">Client Name</h4>
          <div class="col">
            <label class="form-label text-muted" for="first_name">First Name</label>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="first_name" id="first_name" minlength="2" maxlength="50" value="<?php echo $first_name; ?>" required/>
            <label id="first_name_err" class="error-message <?php echo isset($_SESSION['first_name_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['first_name_err'])) { echo $_SESSION['first_name_err']; } ?></span></label>
          </div>
          <div class="col">
            <label class="form-label text-muted" for="last_name">Last Name</label>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="last_name" id="last_name" minlength="2" maxlength="50" value="<?php echo $last_name; ?>" required/>
            <label id="last_name_err" class="error-message <?php echo isset($_SESSION['last_name_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['last_name_err'])) { echo $_SESSION['last_name_err']; } ?></span></label>
          </div>
        </div>
        <div class="row my-3">
          <h4 class="mb-3">Billing Address</h4>
          <div class="col">
            <label class="form-label text-muted" for="street_address">Street Address</label>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="street_address" id="street_address" maxlength="255" required/>
            <label id="street_address_err" class="error-message <?php echo isset($_SESSION['street_address_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['street_address_err'])) { echo $_SESSION['street_address_err']; } ?></span></label>
          </div>
          <div class="col">
            <label class="form-label text-muted" for="city">City</label>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="city" id="city" maxlength="35" required/>
            <label id="city_err" class="error-message <?php echo isset($_SESSION['city_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['city_err'])) { echo $_SESSION['city_err']; } ?></span></label>
          </div>
        </div>
        <div class="row my-3">
          <div class="col">
            <label class="form-label text-muted" for="province">Province</label>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="province" id="province" maxlength="35" required />
            <label id="province_err" class="error-message <?php echo isset($_SESSION['province_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['province_err'])) { echo $_SESSION['province_err']; } ?></span></label>
          </div>
          <div class="col">
            <label class="form-label text-muted" for="zip">Postal / Zip Code</label>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="zip" id="zip" minlength="4" maxlength="4"  value="6000" required/>
            <label id="zip_err" class="error-message <?php echo isset($_SESSION['zip_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['zip_err'])) { echo $_SESSION['zip_err']; } ?></span></label>
          </div>
        </div>
        <div class="row my-4">
          <div class="col">
            <h4 class="mb-3">Payment Amount</h4>
            <div class="input-group">
              <span class="input-group-text">₱</span>
              <input type="number" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="amount_paid" id="amount_paid" required/>
            </div>
            <label id="amount_paid_err" class="error-message <?php echo isset($_SESSION['amount_paid_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['amount_paid_err'])) { echo $_SESSION['amount_paid_err']; } ?></span></label>
          </div>
          <div class="col">
            <h4 class="mb-3">Payment Method</h4>
            <div class="form-check form-check-inline mt-2">
              <input class="form-check-input" type="radio" <?php if($item_count == 0){ echo "disabled"; }?> name="payment_method" value="Debit" required/>
              <label class="form-check-label" for="inlineRadio1">Debit Card</label>
            </div>
            <div class="form-check form-check-inline mt-2">
              <input class="form-check-input" type="radio" <?php if($item_count == 0){ echo "disabled"; }?> name="payment_method" value="Credit" required/>
              <label class="form-check-label" for="inlineRadio2">Credit Card</label>
            </div>
            <div class="form-check form-check-inline mt-2">
              <input class="form-check-input lh-base" type="radio" <?php if($item_count == 0){ echo "disabled"; }?> name="payment_method" value="Prepaid" required/>
              <label class="form-check-label" for="inlineRadio2">Prepaid Card</label>
            </div>
            <label id="payment_method_err" class="error-message <?php echo isset($_SESSION['payment_method_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['payment_method_err'])) { echo $_SESSION['payment_method_err']; } ?></span></label>
          </div>
        </div>
        <div class="row my-4">
          <div class="col">
            <h4 class="mb-3">Card Number</h4>
            <input type="text" inputmode="numeric" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="card_number" id="card_number" minlength="11" maxlength="16" required/>
            <label id="card_number_err" class="error-message <?php echo isset($_SESSION['card_number_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['card_number_err'])) { echo $_SESSION['card_number_err']; } ?></span></label>
          </div>
          <div class="col">
            <h4 class="mb-3">PIN</h4>
            <input type="password" inputmode="numeric" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="pin" id="pin" minlength="6" maxlength="6" required/>
            <label id="pin_err" class="error-message <?php echo isset($_SESSION['pin_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['pin_err'])) { echo $_SESSION['pin_err']; } ?></span></label>
          </div>
          <div class="col">
            <h4 class="mb-3">Bank Name</h4>
            <input type="text" class="form-control" <?php if($item_count == 0){ echo "disabled"; }?> name="bank_name" id="bank_name" required/>
            <label id="bank_name_err" class="error-message <?php echo isset($_SESSION['bank_name_err']) ? "d-flex": "d-none"; ?>"><span class="material-icons-outlined fs-6 me-1">info</span><span><?php if(isset($_SESSION['bank_name_err'])) { echo $_SESSION['bank_name_err']; } ?></span></label>
          </div>
        </div>
      </div>
      <hr class="my-5" />
      <button type="submit" class="btn btn-primary btn-lg rounded-pill" <?php if($item_count == 0){ echo "disabled"; }?> name="checkout" id="Checkout__checkout">Checkout</button>
      <?php if(isset($_SESSION['res'])){ ?>
        <label class="error-message d-flex"><?php echo $_SESSION['res']; ?></label>
      <?php } ?>
    </form>
  </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
<script src="../api/api.js"></script>
<script src="../scripts/js/checkout.js"></script>
</html>
<?php
  if(isset($_SESSION['quantity_err'])){
    unset($_SESSION['quantity_err']);
  }
  if(isset($_SESSION['first_name_err'])){
    unset($_SESSION['first_name_err']);
  }
  if(isset($_SESSION['last_name_err'])){
    unset($_SESSION['last_name_err']);
  }
  if(isset($_SESSION['street_address_err'])){
    unset($_SESSION['street_address_err']);
  }
  if(isset($_SESSION['city_err'])){
    unset($_SESSION['city_err']);
  }
  if(isset($_SESSION['province_err'])){
    unset($_SESSION['province_err']);
  }
  if(isset($_SESSION['zip_err'])){
    unset($_SESSION['zip_err']);
  }
  if(isset($_SESSION['amount_paid_err'])){
    unset($_SESSION['amount_paid_err']);
  }
  if(isset($_SESSION['payment_method_err'])){
    unset($_SESSION['payment_method_err']);
  }
  if(isset($_SESSION['card_number_err'])){
    unset($_SESSION['card_number_err']);
  }
  if(isset($_SESSION['pin_err'])){
    unset($_SESSION['pin_err']);
  }
  if(isset($_SESSION['bank_name_err'])){
    unset($_SESSION['bank_name_err']);
  }
  if(isset($_SESSION['res'])){
    unset($_SESSION['res']);
  }
?>