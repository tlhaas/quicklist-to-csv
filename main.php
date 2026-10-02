<?php

class Quicklist
{
	private array $quicklistRows = [];
	private array $csvRows = [];
	private PDO $db;

	public function __construct(string $csvFile)
    {

    	// read CSV
        if (!is_readable($csvFile)) {
            throw new RuntimeException("CSV file not readable: $csvFile");
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("Failed to open CSV file: $csvFile");
        }

        // no headers, so just pump each row to the object
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $this->quicklistRows[] = $row;

        }

        fclose($handle);

        // put headers in the csvRows so we're ready to go
        $headers = array("TCGplayer Id","Product Line","Set Name","Product Name","Title","Number","Rarity","Condition","TCG Market Price","TCG Direct Low","TCG Low Price With Shipping","TCG Low Price","Total Quantity","Add to Quantity","TCG Marketplace Price","My Store Reserve Quantity","My Store Price","Photo URL");
        $this->csvRows[] = $headers;

        // initalize db
        $this->db = new PDO('sqlite:catalog.db');
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    }

    public function dumpRows()
    {
    	var_dump($this->quicklistRows);
    	echo "Num rows: " . count($this->quicklistRows) . "\n";
    }

    public function compileCSV()
    {

    	foreach($this->quicklistRows as $row)
    	{
    		$qlSkuId = $row[0];
    		$qlQty = $row[1];
    		
			$stmt = $this->db->prepare("
			    SELECT *
			    FROM catalog
			    WHERE `TCGPlayer Id` = :skuId
			");

			$stmt->execute(['skuId' => $qlSkuId]);

			$query = $stmt->fetch(PDO::FETCH_ASSOC);

			// only required fields are ID and condition, but we're putting more in for fun
			// prices must be greater than 0, so we're defaulting to 9999 in case you forget to update this prices yourself
			$_compiledRow = array(
				$query["TCGplayer Id"],
				$query["Product Line"],
				$query["Set Name"],
				$query["Product Name"],
				"",
				$query["Number"],
				$query["Rarity"],
				$query["Condition"],
				"0","0","0","0","0",$qlQty,"9999","0","9999","");

			$this->csvRows[] = $_compiledRow;
			
    	}
    	var_dump($this->csvRows[0]);
    }

    public function exportCSV()
    {
    	$filename = "taco-".time().".csv";
    	$fp = fopen($filename, 'w');
        if ($fp === false) {
            throw new RuntimeException("Failed to open file for writing: $filename");
        }

        // Write each card
        foreach ($this->csvRows as $row) {
            fputcsv($fp, $row, ',', '"', '\\');
        }
        fclose($fp);
    }

}


$ql = new Quicklist($argv[1]);

$ql->compileCSV();
$ql->exportCSV();
            

?>