<?php

require_once 'includes/PhpSpreadsheet/vendor/autoload.php';
require_once 'includes/tFPDF/tfpdf.php';

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (isset($_POST['download'])) {

  // Gets POST values
  $report = $_POST['report'];
  $currency = $_POST['currency'];
  $extension = $_POST['extension'];
  $sqlFrom = $_POST['sqlFrom'];
  $sqlTo = $_POST['sqlTo'];
  $filenameFrom = $_POST['filenameFrom'];
  $filenameTo = $_POST['filenameTo'];
  $headerFrom = $_POST['headerFrom'];
  $headerTo = $_POST['headerTo'];

  // Indicates whether the specified time is a single date or a range
  $isRange = ($sqlFrom != $sqlTo) ? true : false;

  // Connects to PrestaShop and executes query
  $db = new mysqli('','','','');
  $db->query('SET NAMES utf8');
  $sql = getQuery($report, $isRange, $sqlFrom, $sqlTo, $currency);
  $result = $db->query($sql);

  // If there is at least one result
  if ($result->num_rows > 0) {
    
    // Sets money display format
    setlocale(LC_MONETARY, 'es_MX');

    // Gets values to write on the file
    $values = getValues($report, $result, $currency);

    // Sets filename and indicates to browser to expect to download an attachment
    $name = str_replace('_',' ',$report);
    $filename = "Reporte de {$name} [ {$filenameFrom} " . ($isRange? "- {$filenameTo} " : '') . "].{$extension}";
    header("Content-Disposition: attachment; filename={$filename}");

    // Sets title
    $name = ucfirst($name);
    $title = "{$name} del {$headerFrom} " . ($isRange? "al {$headerTo} " : '') . "en {$currency}";

    // Downloads file
    downloadFile($extension, $report, $filename, $title, $values);
    
    die();
  }
}

function downloadFile($extension, $report, $filename, $title, $values) {
  
  // Gets properties
  $properties = getProperties($report);

  if ($extension == 'xlsx') {

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $img = new Drawing();
    
    // Sets properties
    $heights = [1 => 42, 2 => 42, 3 => 5, 4 => 20];
    $moneyFormat = '_-"$"* #,##0.0000_-;\-"$"* #,##0.0000_-;_-"$"* "-"??_-;_-@_-';
    $title1 = str_repeat(' ', 27) . 'Lúmika Brillante Solución S.A. de C.V.';
    $title2 = str_repeat(' ', 34) . $title;
    $refColumns = $properties['refColumns'];
    $headers = $properties['headers'];
    $xWidths = $properties['xWidths'];
    $formats = $properties['formats'];
    $sumStart = $refColumns[0];
    $end = $refColumns[1];
    
    // Styles document
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
    for ($col = 2; $col < ord($end) - 64; $col++) $sheet->getCellByColumnAndRow($col, 4)->setValue($headers[$col-2]);
    foreach ($heights as $row => $height) $sheet->getRowDimension($row)->setRowHeight($height);
    foreach ($xWidths as $col => $width) $sheet->getColumnDimension($col)->setWidth($width);
    $sheet->getStyle("A1:{$end}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');
    $sheet->getStyle("A3:{$end}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('CEDE02');
    $sheet->getStyle("A4:{$end}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('7F7F7F');
    $sheet->getStyle("A4:{$end}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A4:{$end}4")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A2')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->getStyle("A4:{$end}4")->getFont()->getColor()->setARGB('FFFFFF');
    $sheet->getStyle("A1:A2")->getFont()->getColor()->setARGB('FFFFFF');
    $sheet->getStyle('A1')->getFont()->setSize(28);
    $sheet->getStyle('A2')->getFont()->setSize(20);
    $sheet->getColumnDimension($end)->setWidth(1);
    $sheet->getColumnDimension('A')->setWidth(1);
    $sheet->setCellValue('A1', $title1);
    $sheet->setCellValue('A2', $title2);
    $img->setPath('includes/logo.png');
    $img->setCoordinates('B1');
    $img->setWorksheet($sheet);
    $img->setOffsetY(10);
    $img->setOffsetX(5);
    $img->setHeight(90);
    
    // For each found sale...
    for ($row = 5; $sale = array_pop($values); $row++) {

      // Writes values
      for ($col = 2; $col < ord($end) - 64; $col++) $sheet->getCellByColumnAndRow($col, $row)->setValue($sale[$col-2]);

      // Styles row
      $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('7F7F7F');
      $sheet->getStyle("{$end}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('7F7F7F');
      $sheet->getRowDimension($row)->setRowHeight(16);

    }
  
    // Applies format
    $range = range('B', chr(ord($end) - 1));
    for ($i = 0; $col = array_shift($range); $i++) {
      if ($formats[$i] == 'money')
        $sheet->getStyle("{$col}5:{$col}{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
      elseif ($formats[$i] != 'text')
        $sheet->getStyle("{$col}5:{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Writes sums
    $sheet->setCellValue("{$sumStart}{$row}", 'Total General');
    $range = range(chr(ord($sumStart) + 1), chr(ord($end) - 1));
    foreach ($range as $col) $sheet->setCellValue("{$col}{$row}", "=SUM({$col}5:{$col}" . ($row - 1) . ')');

    // Styles row
    $sheet->getStyle("A{$row}:{$end}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('7F7F7F');
    $sheet->getStyle("A{$row}:{$end}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A{$row}:{$end}{$row}")->getFont()->getColor()->setARGB('FFFFFF');
    $sheet->getRowDimension($row)->setRowHeight(20);
  
    // Downloads file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

  }
  else {
    
    // Sets properties, and creates a new page to start writing
    $pdf = new tFPDF($title, 'L');
    $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
    $pdf->SetTextColor(255, 255, 255); // White
    $pdf->SetFillColor(127, 127, 127); // Grey
    $pdf->SetAutoPageBreak(true);
    $pdf->SetFont('DejaVu');
    $pdf->AddPage();

    // Sets properties
    $alignments = $properties['alignments'];
    $fontSize = $properties['fontSize'];
    $headers = $properties['headers'];
    $height = $properties['height'];
    $pWidths = $properties['pWidths'];
    $formats = $properties['formats'];
    $sums = $properties['sums'];
    $moneyFormat = '%!.4n';

    // Sets font size
    $pdf->SetFontSize($fontSize);

    // Writes table headers
    for ($i = 0; $i < sizeof($headers); $i++) $pdf->Cell($pWidths[$i], $height, $headers[$i], 1, 0, 'C', 1);

    // Sets text color to black and line-breaks
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln();

    // Writes cell values
    foreach ($values as $sale) {

      // For each value...
      for ($i = 0; $i < sizeof($sale); $i++) {

        // Calculates sums
        if ($sums[$i] >= 0)
          $sums[$i] += $sale[$i];

        // Sets value to write
        $value = ($formats[$i] == 'money' ? money_format($moneyFormat, floatval($sale[$i])) : $sale[$i]);

        // Fixes value
        if ($report == 'ventas_por_producto' || $report == 'costos_por_producto') {
          if ($i == 0)
            $value = str_replace('-', '0', $value);
          elseif ($i == 1) {
            if ($report == 'ventas_por_producto' && strlen($value) > 15)
              $value = rtrim(substr($value, 0, 15), ' ') . '..';
            elseif ($report == 'costos_por_producto' && strlen($value) > 50)
              $value = rtrim(substr($value, 0, 50), ' ') . '..';
          } 
        }

        // Stores x position before writing
        $x = $pdf->GetX();

        // Writes cell down
        $pdf->Cell($pWidths[$i], $height, $value, 1, 0, $alignments[$i]);

        // Goes back to start of row
        $pdf->SetX($x);

        // Writes money symbol if needed
        $pdf->Cell($pWidths[$i], $height, ($formats[$i] == 'money' ? '$' : ''));

      }

      // Line-breaks to next row
      $pdf->Ln();

    }

    // Sets text color to white
    $pdf->SetTextColor(255, 255, 255);

    // Writes sums
    for ($i = 0; $i < sizeof($sums); $i++) {
      // Sets value to write
      if ($sums[$i] >= 0)
        $value = $formats[$i] == 'money' ? money_format($moneyFormat, floatval($sums[$i])) : utf8_encode($sums[$i]);
      elseif ($sums[$i+1] >= 0) {
        $value = 'Total General ';
        $alignments[$i] = 'R';
      }
      else
        $value = '';
      
      // Stores x position before writing
      $x = $pdf->GetX();

      // Writes sum down
      $pdf->Cell($pWidths[$i], $height, $value, 1, 0, $alignments[$i], 1);

      // Goes back to start of row
      $pdf->SetX($x);

      // Writes money symbol if needed
      $pdf->Cell($pWidths[$i], $height, (($formats[$i] == 'money' && $sums[$i] >= 0) ? '$' : ''));

    }

    // Downloads file
    $pdf->Output($filename, 'D');

  }
}

function getQuery($report, $isRange, $sqlFrom, $sqlTo, $currency) {
  
  // Sets string used in reports to convert money
  $toUSD = $currency == 'USD' ? '/conversion_rate' : '';
  $toMXN = $currency == 'MXN' ? '*conversion_rate' : '';

  /* Builds SQL query depending on the report. Querys convert money on-the-go, assuming:
    1. Customers will always buy products in MXN
    2. Sale tables store money values in the currency they are sold
    3. Lúmika will always store their products' cost price in USD
  If any of this conditions isn't true, then the whole convertion method must be re-made. Tests must be run to confirm these conditions */

  switch ($report) {
    // SELECT *, total_discounts_tax_excl+total_price_tax_excl as neto FROM ps_orders JOIN ps_order_detail USING (id_order) JOIN ps_order_detail_tax USING (id_order_detail) JOIN ps_customer_group USING (id_customer) JOIN ps_group_lang USING (id_group) WHERE valid = 1 AND ps_group_lang.id_lang = 2 AND DATE(ps_orders.date_add) >= '2018-08-21' AND DATE(ps_orders.date_add) <= '2018-08-23'
    case 'ventas_por_producto':
      $sql = "SELECT product_reference, product_name, reference, name, product_quantity,
      product_price {$toMXN} as converted_price,
      (total_discounts_tax_excl + total_price_tax_excl) {$toUSD} as converted_neto,
      total_discounts_tax_excl {$toUSD} as converted_descuento,
      total_price_tax_excl {$toUSD} as converted_neto_desc,
      total_amount {$toUSD} as converted_impuesto,
      total_price_tax_incl {$toUSD} as converted_total
      FROM ps_orders JOIN ps_order_detail USING (id_order) JOIN ps_order_detail_tax USING (id_order_detail) JOIN ps_customer_group USING (id_customer) JOIN ps_group_lang USING (id_group) WHERE valid = 1 AND ps_group_lang.id_lang = 2 " .
      ($isRange? "AND DATE(ps_orders.date_add) >= '{$sqlFrom}' AND DATE(ps_orders.date_add) <= '{$sqlTo}'" : "AND DATE(ps_orders.date_add) = '{$sqlFrom}'");
      break;

    // SELECT *, total_discounts_tax_excl+SUM(total_price_tax_excl) as neto, SUM(total_price_tax_excl) as neto_desc, SUM(total_amount) as impuesto FROM ps_orders JOIN ps_order_detail USING (id_order) JOIN ps_order_detail_tax USING (id_order_detail) WHERE valid = 1 AND DATE(date_add) >= '2018-08-21' AND DATE(date_add) <= '2018-08-23' GROUP BY reference
    case 'ventas_por_orden':
      $sql = "SELECT reference, date_add,
      (total_discounts_tax_excl + SUM(total_price_tax_excl)) {$toUSD} as converted_neto,
      total_discounts_tax_excl {$toUSD} as converted_descuento,
      SUM(total_price_tax_excl {$toUSD}) as converted_neto_desc,
      SUM(total_amount {$toUSD}) as converted_impuesto,
      total_paid_tax_incl {$toUSD} as converted_total
      FROM ps_orders JOIN ps_order_detail USING (id_order) JOIN ps_order_detail_tax USING (id_order_detail) WHERE valid = 1 " .
      ($isRange? "AND DATE(date_add) >= '{$sqlFrom}' AND DATE(date_add) <= '{$sqlTo}'" : "AND DATE(date_add) = '{$sqlFrom}'") .
      ' GROUP BY reference';
      break;

    // SELECT *, original_wholesale_price*product_quantity as total FROM ps_orders JOIN ps_order_detail USING (id_order) WHERE valid = 1 AND DATE(date_add) >= '2018-08-21' AND DATE(date_add) <= '2018-08-23'
    case 'costos_por_producto':
      $sql = "SELECT product_reference, product_name, reference, product_quantity,
      original_wholesale_price {$toMXN} as converted_costo,
      original_wholesale_price * product_quantity {$toMXN} as converted_total
      FROM ps_orders JOIN ps_order_detail USING (id_order) WHERE valid = 1 " .
      ($isRange? "AND DATE(date_add) >= '{$sqlFrom}' AND DATE(date_add) <= '{$sqlTo}'" : "AND DATE(date_add) = '{$sqlFrom}'");
      break;

    // SELECT *, SUM(original_wholesale_price*product_quantity) as costo FROM ps_orders JOIN ps_order_detail USING (id_order) WHERE valid = 1 AND DATE(date_add) >= '2018-08-21' AND DATE(date_add) <= '2018-08-23' GROUP BY reference
    case 'costos_por_orden':
      $sql = "SELECT reference, date_add,
      SUM(original_wholesale_price * product_quantity {$toMXN}) as converted_costo
      FROM ps_orders JOIN ps_order_detail USING (id_order) WHERE valid = 1 " .
      ($isRange? "AND DATE(date_add) >= '{$sqlFrom}' AND DATE(date_add) <= '{$sqlTo}'" : "AND DATE(date_add) = '{$sqlFrom}'") .
      ' GROUP BY reference';
      break;
  }

  return $sql;
}

function getValues($report, $result, $currency) {
  // Gets values to be written on the file
  
  // Loops through all found sales
  while ($sale = $result->fetch_assoc()) {

    // Sets array according to the type of report. Money is already converted for costos reports
    switch ($report) {
      case 'ventas_por_producto':
        $codigo = $sale['product_reference'];
        $producto = $sale['product_name'];
        $orden = $sale['reference'];
        $cliente = $sale['name'];
        $precio = $sale['converted_price'];
        $cantidad = $sale['product_quantity'];
        $neto = $sale['converted_neto'];
        $descuento = $sale['converted_descuento'];
        $neto_desc = $sale['converted_neto_desc'];
        $impuesto = $sale['converted_impuesto'];
        $total = $sale['converted_total'];
        $values[] = [ $codigo, $producto, $orden, $cliente, $precio, $cantidad, $neto, $descuento, $neto_desc, $impuesto, $total ];
        break;

      case 'ventas_por_orden':
        $referencia = $sale['reference'];
        $fecha = $sale['date_add'];
        $neto = $sale['converted_neto'];
        $descuento = $sale['converted_descuento'];
        $neto_desc = $sale['converted_neto_desc'];
        $impuesto = $sale['converted_impuesto'];
        $total = $sale['converted_total'];
        $values[] = [ $referencia, $fecha, $neto, $descuento, $neto_desc, $impuesto, $total ];
        break;
    
      case 'costos_por_producto':
        $codigo = $sale['product_reference'];
        $producto = $sale['product_name'];
        $orden = $sale['reference'];
        $costo = $sale['converted_costo'];
        $cantidad = $sale['product_quantity'];
        $total = $sale['converted_total'];
        $values[] = [ $codigo, $producto, $orden, $costo, $cantidad, $total ];
        break;

      case 'costos_por_orden':
        $referencia = $sale['reference'];
        $fecha = $sale['date_add'];
        $costo = $sale['converted_costo'];
        $values[] = [ $referencia, $fecha, $costo ];
        break;
    }
  }

  return $values;
}

function getProperties($report) {

  /* Sets properties used in:
    $headers (both): to write table's headers
    $xWidths (Xlsx): to set column widths
    $refColumns (Xlsx): to indicate what column is to be used as sum title (Total General) and where the table ends
    $alignments (PDF): to align cells to either left (L), center (C), or right (R)
    $pWidths (PDF): to set cell widths
    $sums (PDF): to calculate and write sums at the end of tables
    $formats (PDF): to indicate whether column's format so it can be properly formatted, it could be text, money, number, or date
    $height (PDF): to set row's height 
    $fontSize (PDF): to set the size of the font
  */

  switch ($report) {
    case 'ventas_por_producto':
      $headers = ['Código', 'Producto', 'Orden', 'Cliente', 'Precio', 'Cantidad', 'Neto', 'Descuento', 'Neto-Desc', 'Impuesto', 'Total'];
      $xWidths = ['B' => 27, 'C' => 30, 'D' => 15, 'E' => 10, 'F' => 17, 'G' => 10, 'H' => 17, 'I' => 17, 'J' => 17, 'K' => 17, 'L' => 17];
      $formats = ['text', 'text', 'text', 'text', 'money', 'number', 'money', 'money', 'money', 'money', 'money'];
      $alignments = ['L', 'L', 'L', 'L', 'R', 'C', 'R', 'R', 'R', 'R', 'R'];
      $pWidths = [50, 27, 22, 18, 24, 15, 24, 24, 24, 24, 24];
      $sums = [-1, -1, -1, -1, -1, 0, 0, 0, 0, 0, 0];
      $refColumns = ['F', 'M'];
      $fontSize = 8;
      $height = 6;
      break;
      
      case 'ventas_por_orden':
      $headers = ['Referencia', 'Fecha', 'Neto', 'Descuento', 'Neto-Desc', 'Impuesto', 'Total'];
      $xWidths = ['B' => 20, 'C' => 25, 'D' => 20, 'E' => 20, 'F' => 20, 'G' => 20, 'H' => 20];
      $formats = ['text', 'date', 'money', 'money', 'money', 'money', 'money'];
      $alignments = ['L', 'C', 'R', 'R', 'R', 'R', 'R'];
      $pWidths = [35, 51, 38, 38, 38, 38, 38];
      $sums = [-1, -1, 0, 0, 0, 0, 0];
      $refColumns = ['C', 'I'];
      $fontSize = 11;
      $height = 8;
      break;
      
      case 'costos_por_producto':
      $headers = ['Código', 'Producto', 'Orden', 'Costo unitario', 'Cantidad', 'Total'];
      $xWidths = ['B' => 30, 'C' => 45, 'D' => 20, 'E' => 20, 'F' => 15, 'G' => 20];
      $formats = ['text', 'text', 'text', 'money', 'number', 'money'];
      $alignments = ['L', 'L', 'L', 'R', 'C', 'R'];
      $pWidths = [55, 94, 35, 33, 26, 33];
      $sums = [-1, -1, -1, -1, 0, 0];
      $refColumns = ['E', 'H'];
      $fontSize = 11;
      $height = 8;
      break;
      
      case 'costos_por_orden':
      $xWidths = ['B' => 40, 'C' => 45, 'D' => 40];
      $headers = ['Referencia', 'Fecha', 'Costo'];
      $formats = ['text', 'date', 'money'];
      $alignments = ['L', 'C', 'R'];
      $refColumns = ['C', 'E'];
      $pWidths = [90, 96, 90];
      $sums = [-1, -1, 0];
      $fontSize = 11;
      $height = 8;
      break;
  }

  return [
    'alignments' => $alignments,
    'refColumns' => $refColumns,
    'fontSize' => $fontSize,
    'headers' => $headers,
    'pWidths' => $pWidths,
    'xWidths' => $xWidths,
    'height' => $height,
    'formats' => $formats,
    'sums' => $sums
  ];
}

?>

  <!DOCTYPE html>
  <html>

  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="shortcut icon" type="image/png" href="https://images.vexels.com/media/users/3/145131/isolated/preview/d2ba09d9b4856df5b15cdc5636a45b37-sun-large-wavy-beams-icon-by-vexels.png"
    />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO"
      crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="js/script.js"></script>
    <title>Reportes</title>
  </head>

  <body class='bg-light'>

    <div class="py-5 text-center">
      <img class="d-block mx-auto mb-4" src="includes/logo.png" width="200" height="77.39">
      <h2>Generador de Reportes de PrestaShop</h2>
      <p class='lead'>Modifique los campos deseados para generar un reporte</p>
    </div>

    <div class='container'>

      <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method='post'>

        <div class='form-group'>
          <label for='report'>Tipo de reporte</label>
          <select class='form-control' id='report' name='report'>
            <option value='ventas_por_producto'>Ventas por producto</option>
            <option value="ventas_por_orden">Ventas por orden</option>
            <option value="costos_por_producto">Costos por producto</option>
            <option value="costos_por_orden">Costos por orden</option>
          </select>
        </div>

        <div class='form-group'>
          <label for='reportrange'>Fecha(s)</label>
          <input class='form-control' id='reportrange' readonly>
          <span></span>
        </div>

        <div class='form-group'>
          <label for='currency'>Moneda</label>
          <select class='form-control' id='currency' name='currency'>
            <option value="USD">USD - Dólar estadounidense</option>
            <option value="MXN">MXN - Peso mexicano</option>
          </select>
        </div>

        <div class='form-group'>
          <label for='extension'>Archivo a descargar</label>
          <select class='form-control' id='extension' name='extension'>
            <option value='xlsx'>Excel (.xlsx)</option>
            <option value='pdf'>PDF (.pdf)</option>
          </select>
        </div>

        <input type='hidden' name='sqlFrom' />
        <input type='hidden' name='sqlTo' />
        <input type='hidden' name='filenameFrom' />
        <input type='hidden' name='filenameTo' />
        <input type='hidden' name='headerFrom' />
        <input type='hidden' name='headerTo' />

        <button class='btn btn-primary btn-block' name='download' style='margin-bottom: 5em'>Descargar</button>

      </form>

      <?php
      
      // If this code is executed is because die() wasn't reached out, which means the query was empty
      if (isset($_POST['download'])) {
        echo '<script language="javascript">';
        echo 'alert("No se han encontrado registros que cumplan con los campos especificados, por lo que no se ha generado el reporte.")';
        echo '</script>';
      }
      
      ?>

    </div>
    <!-- Container -->

  </body>

  </html>