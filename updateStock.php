<?php

$col = '8-,#5uml!!#v'; // Colima warehouse keycode
$gdl = '8--20u$¼!!#«'; // Guadalajara warehouse keycode

/* Connects with databases */
$dbPs = new mysqli('','','',''); // PrestaShop
$dbGdl = new mysqli('','','',''); // Lúmika GDL

/* Required for encryption issues */
header('Content-Type: application/json');
header('Content-Type: text/html; charset=utf-8');
$dbGdl->query("SET NAMES 'utf8'");

/* Looks for the to-update products */
$productos = $dbPs->query('SELECT id_product, reference FROM ps_product WHERE active = 1');

/* For each product found... */
while ($producto = $productos->fetch_assoc()) {
	
	/* Results of the query on PrestaShop */
	$id = $producto['id_product'];
	$ref = $producto['reference'];

	/* Looks for the RecID of the product in Lúmika GDL */
	$rec = $dbGdl->query('SELECT RecID FROM productos WHERE Codigo = "'. $ref .'"')->fetch_assoc()['RecID'];

	/* Looks for quantity in both warehouses */
	$dbGdl->query("SET NAMES 'utf8'");
	$cantCol = $dbGdl->query('SELECT SUM(CASE tipo WHEN 0 THEN (cantidad*Equivalencia) WHEN 1 THEN -(cantidad*Equivalencia) ELSE 0 END) as cant FROM productosstockmovimientos WHERE tipo <> 2 AND tipo <> 3 AND idproducto = "' . $rec . '" AND iddeposito = "' . $col . '"')->fetch_assoc()['cant'];
	$dbGdl->query("SET NAMES 'utf8'");
	$cantGdl = $dbGdl->query('SELECT SUM(CASE tipo WHEN 0 THEN (cantidad*Equivalencia) WHEN 1 THEN -(cantidad*Equivalencia) ELSE 0 END) as cant FROM productosstockmovimientos WHERE tipo <> 2 AND tipo <> 3 AND idproducto = "' . $rec . '" AND iddeposito = "' . $gdl . '"')->fetch_assoc()['cant'];

	/* In case there are nulls */
	$cantCol = ($cantCol === null)? 0 : $cantCol;
	$cantGdl = ($cantGdl === null)? 0 : $cantGdl;
	
	/* Updated */
	$total = $cantCol +  $cantGdl;
	$dbPs->query("UPDATE ps_stock_available SET quantity={$total} WHERE id_product={$id}");
	
	/* Shows values */
	//echo "Id: " . $id . "\t:Col: " . $cantCol . "\tGdl: " . $cantGdl . "\tTotal: " . $total . "\tRef: " . $ref . '<br>';

	$atualizados[] = array('id' => $id, 'col' => $cantCol, 'gdl' => $cantGdl, 'total' => $total, 'ref' => $ref);
}

echo json_encode($atualizados);
die();

?>