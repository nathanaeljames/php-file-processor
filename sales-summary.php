<!DOCTYPE html>
<html>
<body>

<form action="" method="post" enctype="multipart/form-data">
  Select a CSV file to upload:
  <input type="file" name="csvUpload" id="csvUpload">
  <input type="submit" value="Submit" name="submit">
</form>

</body>

<style>
    table, th, td {
        border: 1px solid black;
        border-collapse: collapse;
    }
    td {
        color: green;
    }
</style>

</html>


<?php

class parseCSV {

    public function validateUpload($fileref) {
        try {
            // Check for multiple uploads.
            if (is_array($fileref['error'])) {
                throw new Exception('Invalid parameters.');
            }

            // Check $fileref['error'] for errors.
            switch ($fileref['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new Exception('No file sent.');
                case UPLOAD_ERR_INI_SIZE:
                    throw new Exception('Exceeded INI filesize limit.');
                default:
                    throw new Exception('Unknown errors.');
            }

            // Check filesize (e.g. 20 megabytes, arbitrary).
            if ($fileref['size'] > 20000000) {
                throw new Exception('Exceeded filesize limit.');
            }

            // Check MIME Type manually rather than use $fileref['mime'] without validation
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $ext = strtolower(end(explode('.',$fileref['name'])));
            if (false === $ext = array_search(
                $finfo->file($fileref['tmp_name']),
                array('csv' => 'text/csv',
                    'csv' => 'text/plain',
                ),
                true
            )) {
                throw new Exception('Invalid file format. Please upload a .csv file.');
            }

            // Generate unique hash-based name rather than use $fileref['name'] without validation
            $validatedFile = sprintf('./uploads/%s.%s',sha1_file($fileref['tmp_name']),$ext);
            if (!move_uploaded_file(
                $fileref['tmp_name'],
                $validatedFile
            )) {
                throw new Exception('Failed to move uploaded file.');
            }

            echo 'File uploaded successfully.';

        } catch (Exception $e) {
            echo $e->getMessage();
            exit();
        }
        return $validatedFile;
    }

    private function convertToCAD($amount) {
        // Set API Endpoint, access key, required parameters
        $endpoint = 'convert';
        $access_key = '3c6bdd969660aa78e7ac3fac62bef09b'; // Paid key
        //$access_key = 'c19e8007b08f02b00486237ff1661821'; // Free key (will produce error for testing)

        $from = 'USD';
        $to = 'CAD';

        // Initialize CURL:
        $ch = curl_init('https://api.exchangeratesapi.io/v1/'.$endpoint.'?access_key='.$access_key.'&from='.$from.'&to='.$to.'&amount='.$amount.'');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Get the JSON data:
        $json = curl_exec($ch);
        curl_close($ch);

        // Decode JSON response:
        $conversionResult = json_decode($json, true);

        // Return the conversion result or error
        if ($conversionResult['success']) {
            return $conversionResult['result'];
        } else {
            echo '<br><span style="color:red;display:table;margin:0 auto;">'.($conversionResult['error']['message']).'</span>';
            return null;
        }
    }

    public function parseArray($validatedFile) {
        // Load file, create multidimensional array with header values as keys
        if (($file = fopen($validatedFile, "r")) !== FALSE) {
            $headers = fgetcsv($file);
            $data = [];
            while (($row = fgetcsv($file)) !== false)
            {
                $item = [];
                foreach ($row as $key => $value)
                    $item[$headers[$key]] = $value ?: null;
                $data[] = $item;
            }
            fclose($file);
            if (sizeof($data) == 0) {
                echo '<br><span style="color:red;display:table;margin:0 auto;">Error: Nothing in file!</span>';
            }
        }

        // Sort array into expected order with headers in first row and footer in last
        // Header - hard-coded
        $sorted[] = array("SKU","Cost","Price","QTY","Profit Margin","Total Profit (USD)","Total Profit (CAD)");
        // Body - reorder, check for and skip null values, store extant values as currency
        setlocale(LC_MONETARY, 'en_US.UTF-8');
        foreach ($data as $row) {
            $sortedRow[] = $row['SKU'];
            if ($row['Cost']) {
                $sortedRow[] = money_format('%.2n',$row['Cost']);
                $totalCost += $row['Cost'];
                $costValues++; } else { $sortedRow[] = null; }
            if ($row['Price']) {
                $sortedRow[] = money_format('%.2n',$row['Price']);
                $totalPrice += $row['Price'];
                $priceValues++; } else { $sortedRow[] = null; }
            if ($row['QTY']) {
                $sortedRow[] = $row['QTY'];
                $totalQTY += $row['QTY']; } else { $sortedRow[] = null; }
            $profitMargin = $row['Price'] - $row['Cost'];
            if ($row['Price'] and $row['Cost']) {
                $sortedRow[] = money_format('%.2n',$profitMargin);
                $totalpm += $profitMargin;
                $pmValues++; } else { $sortedRow[] = null; }
            $profit = $profitMargin * $row['QTY'];
            if ($row['Price'] and $row['Cost'] and $row['QTY']) {
               $sortedRow[] = money_format('%.2n',$profit);
               $totalProfitUSD += $profit; } else { $sortedRow[] = null; }
            // Now some currency conversion (API doesn't accept negative values so compensate with type coercion)
            if ($row['Price'] and $row['Cost'] and $row['QTY']) {
                $profitCAD = $this->convertToCAD(abs($profit));
                if ($profit < 0) {
                    $sortedRow[] = '-'.money_format('%.2n',$profitCAD);
                    $totalProfitCAD -= $profitCAD;
                } else {
                    $sortedRow[] = money_format('%.2n',$profitCAD);
                    $totalProfitCAD += $profitCAD;
                }
            } else { $sortedRow[] = null; }
            // Push the final sorted row into the array
            $sorted[] = $sortedRow;
            $sortedRow = null;
        }
        // Footer - some basic arithmetic which omits nulls from averages
        $sorted[] = array(
            "SUMMARY",
            money_format('%.2n',($totalCost/$costValues)),
            money_format('%.2n',($totalPrice/$priceValues)),
            $totalQTY,
            money_format('%.2n',($totalpm/$pmValues)),
            money_format('%.2n',$totalProfitUSD),
            money_format('%.2n',$totalProfitCAD),
        );
        return $sorted;
    }

    public function printResults($sortedArray) {
        echo "<br><br>";
        echo "<html><body><center><table>\n\n";
            foreach ($sortedArray as $r => $v) {
                // Print header first, then body
                if ($r < 1) {
                    echo "<tr>";
                        foreach ($v as $i) {
                            echo "<th>" . htmlspecialchars($i) 
                            . "</th>";
                        }
                    echo "</tr> \n";
                } else {
                    echo "<tr>";
                        foreach ($v as $i) {
                            if ($i[0] == '-') {
                                echo "<td style='color:red'>" . htmlspecialchars($i) 
                                . "</td>";
                            } else {
                                echo "<td>" . htmlspecialchars($i) 
                                . "</td>";
                            }
                        }
                    echo "</tr> \n";
                }
            }
        echo "\n</table></center></body></html>";
    }


}

if(isset($_FILES['csvUpload'])) {
    $parser = new parseCSV();
    $validatedInput = $parser->validateUpload($_FILES['csvUpload']);
    $sortedArray = $parser->parseArray($validatedInput);
    $parser->printResults($sortedArray);
}

?>
