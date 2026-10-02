<?php

class Quicklist
{
	private array $quicklistRows = [];
	private array $csvRows = [];
	private PDO $db;

	public function __construct(string $csvFile)
    {
    	// read CSV
        if (!is_readable($csvFile)) { throw new RuntimeException("CSV file not readable: $csvFile"); }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) { throw new RuntimeException("Failed to open CSV file: $csvFile"); }

        // no headers, so just pump each row to the object
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) 
        { 
        	// throw away empty lines in CSV
        	if (empty(array_filter($row)))
		    {
		        continue;
		    }
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

    // we're not allowed to upload multiple identical cards separately via CSV, so we must merge them into one row
    public function dedupeQuicklistRows()
    {
    	$dedupedArray = array();

    	echo "Num row before: " . count($this->quicklistRows) . " rows.\n";

    	foreach ($this->quicklistRows as $row)
    	{
    		// first column is some stupid thing i dunno what it is
    		$_skuId = $row[1];
    		$_qty   = (int) $row[2];

    		if (isset($dedupedArray[$_skuId]))
    		{
    			$dedupedArray[$_skuId] += $_qty;
    		}
    		else
    		{
    			$dedupedArray[$_skuId] = $_qty;
    		}
    	}
    	$this->quicklistRows = $dedupedArray;

    	echo "Num row before: " . count($this->quicklistRows) . " after.\n";
    	
    }

    public function compileCSV()
    {
    	foreach($this->quicklistRows as $skuId => $qty)
    	{    		
			$stmt = $this->db->prepare("
			    SELECT *
			    FROM catalog
			    WHERE `TCGPlayer Id` = :skuId
			");

			$stmt->execute(['skuId' => $skuId]);

			$query = $stmt->fetch(PDO::FETCH_ASSOC);

			// only required fields are ID and condition, but we're putting more in for fun
			// prices must be greater than 0, so we're defaulting to 9999 in case you forget to update the prices yourself
			$_compiledRow = array(
				$query["TCGplayer Id"],
				$query["Product Line"],
				$query["Set Name"],
				$query["Product Name"],
				"",
				$query["Number"],
				$query["Rarity"],
				$query["Condition"],
				"0","0","0","0","0",$qty,"9999","0","9999","");

			$this->csvRows[] = $_compiledRow;	
    	}
    }

    public function exportCSV()
    {
    	$filename = "taco-".time().".csv";
    	$fp = fopen($filename, 'w');
        if ($fp === false) {
            throw new RuntimeException("Failed to open file for writing: $filename");
        }

        // Write each row
        foreach ($this->csvRows as $row) { fputcsv($fp, $row, ',', '"', '\\'); }
        fclose($fp);
    }
}

$ql = new Quicklist($argv[1]);
$ql->dedupeQuicklistRows();
$ql->compileCSV();
$ql->exportCSV();
            
?>