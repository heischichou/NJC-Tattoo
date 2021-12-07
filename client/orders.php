<?php
  session_name("sess_id");
  session_start();
  if(!isset($_SESSION['user_id'])){
    Header("Location: ../client/index.php");
    die();
  } else {
    require_once '../api/api.php';
    $api = new api();
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Yeseva+One&display=swap" rel="stylesheet">

    <!-- bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    
    <!-- native style -->
    <link href="./style/style.css" rel="stylesheet">
    <title>Orders | NJC Tattoo</title>
</head>
<body>
<?php
  if(!isset($amount_total)){
    $amount_total = 0.00;
  }

  // $left_table = $api->join("INNER", "order_item", "tattoo", "order_item.tattoo_id","tattoo.tattoo_id");
  // $joined_table = $api->join("INNER", $left_table, "workorder", "workorder.order_id","order_item.order_id");
  $query = $api->select();
  // $query = $api->params($query, array("workorder.order_id","order_item.item_id","workorder.order_date","tattoo.tattoo_id","tattoo.tattoo_name","tattoo.tattoo_description","order_item.tattoo_quantity","tattoo.tattoo_price"));
  $query = $api->params($query, "*");
  $query = $api->from($query);
  // $query = $api->table($query, $joined_table);
  $query = $api->table($query, "orders");
  $query = $api->where($query, "client_id", "?");

  try {
    $statement = $api->prepare($query);
    if($statement===false){
      throw new Exception("prepare() error: The statement could not be prepared.");
    }

    $mysqli_checks = $api->bind_params($statement, "s", array($_SESSION['client_id']));
    if($mysqli_checks===false){
      throw new Exception('bind_param() error: A variable could not be bound to the prepared statement.');
    }

    $mysqli_checks = $api->execute($statement);
    if($mysqli_checks===false){
      throw new Exception('Execute error: The prepared statement could not be executed.');
    }

    $res = $api->get_result($statement);
    if($res===false){
      throw new Exception('get_result() error: Getting result set from statement failed.');
    }
  } catch (Exception $e){
    exit;
    Header("Location: ./index.php");
    echo $e->getMessage();
  }
?>
<table class="table w-75 mx-auto">
  <thead class="align-middle" style="height: 4em;">
    <tr>
      <th scope="col">Actions</th>
      <th scope="col">Order ID</th>
      <th scope="col">Date Ordered</th>
      <th scope="col">Tattoo</th>
      <th scope="col">Description</th>
      <th scope="col">Quantity</th>
      <th scope="col">Price</th>
    </tr>
  </thead>
  <tbody>
    <?php
      if($api->num_rows($res) > 0){
        while($row = $api->fetch_assoc($res)){
          if(strcasecmp($row['status'], 'Ongoing') == 0){
            $amount_total += $row['tattoo_price'] * $row['tattoo_quantity'];
    ?>
    <tr class="align-middle" style="height: 5em;">
      <form method="POST" action="../api/queries.php">
        <td scope="row">
          <input type="hidden" class="d-none" name="item_id" value="<?php echo $row['item_id']?>"/>
          <button class="btn btn-outline-info" name="update_item">Update</button>
          <button class="btn btn-outline-danger" name="remove_item">Remove</button>
        </td>
        <th><?php echo $api->clean($row['order_id']) ?></th>
        <td><?php echo $api->clean($row['order_date']) ?></td>
        <td>
          <?php
            echo $api->clean($row['tattoo_id']);
            echo $api->clean($row['tattoo_name'])
          ?>
        </td>
        <td><?php echo $api->clean($row['tattoo_description'])?></td>
        <td>
          <input type="number" class="form-control" name="quantity" min="1" value="<?php echo $row['tattoo_quantity']?>"/>
        </td>
        <td>Php <?php echo number_format($row['tattoo_price'], 2, '.', '') ?></td>
      </form>
    </tr>
    <?php }} ?>
    <tfoot style="height: 4em;">
        <td colspan="5"></td>
        <td class="fw-bold">Amount Total</td>
        <td>Php <?php echo number_format($amount_total, 2, '.', '') ?></td>
    </tfoot>
    <?php } else { ?>
      <tfoot>
        <td colspan="7" class="p-5"><h1 class="m-3 display-4 fst-italic text-muted">No tattoos ordered.</h1></td>
      </tfoot>
    <?php } ?>
  </tbody>
</table>
</body>
</html>
<?php
  try {
    $mysqli_checks = $api->close($statement);
    if($mysqli_checks===false){
      throw new Exception('The prepared statement could not be closed.');
    }
  } catch (Exception $e){
    exit;
    Header("Location: ./index.php");
    echo $e->getMessage();
  }
?>