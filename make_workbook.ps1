$ErrorActionPreference = 'Stop'
$output = Join-Path $PSScriptRoot 'NOAH-DIGITAL-STORE-RECORDS-TEMPLATE.xlsx'
$sheetHeaders = [ordered]@{
    'CUSTOMERS' = @('CustomerID','Name','Phone','WhatsApp','Email','CreatedAt')
    'ORDERS' = @('Reference','CreatedAt','CustomerPhone','CustomerWhatsApp','DeliveryPreference','Location','PaymentPreference','Instructions','ServiceType','GoodsRequest','ServiceFee','ProductsEstimate','EstimatedTotal','PaymentReference','PaymentStatus','Status','WhatsAppConfirmationLink')
    'ORDER ITEMS' = @('Reference','ProductID','ProductName','Quantity','UnitPrice','LineTotal','Currency')
    'PRODUCTS' = @('ProductID','Name','Category','PriceTZS','PreviousPriceTZS','StockStatus','Description','ImageURL','SpecificationsJSON','Active')
    'PAYMENTS' = @('Reference','UpdatedAt','Amount','Currency','Method','Status','PaymentCode','Notes')
    'ENQUIRIES' = @('Reference','CreatedAt','Name','Phone','Email','Message','Status')
}
function Escape-Xml([string]$text) {
    return [System.Security.SecurityElement]::Escape($text)
}
function Add-ZipText($archive, [string]$name, [string]$text) {
    $entry = $archive.CreateEntry($name)
    $stream = $entry.Open()
    $writer = [System.IO.StreamWriter]::new($stream, [System.Text.UTF8Encoding]::new($false))
    $writer.Write($text)
    $writer.Dispose()
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $output) { throw "Workbook already exists; move it before generating a new template: $output" }
$fileStream = [System.IO.File]::Open($output, [System.IO.FileMode]::CreateNew)
$archive = [System.IO.Compression.ZipArchive]::new($fileStream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $contentOverrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml" />'
    $workbookSheets = ''
    $workbookRels = ''
    $index = 0
    foreach ($entry in $sheetHeaders.GetEnumerator()) {
        $index++
        $sheetName = Escape-Xml $entry.Key
        $contentOverrides += "<Override PartName=`"/xl/worksheets/sheet$index.xml`" ContentType=`"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml`" />"
        $workbookSheets += "<sheet name=`"$sheetName`" sheetId=`"$index`" r:id=`"rId$index`" />"
        $workbookRels += "<Relationship Id=`"rId$index`" Type=`"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet`" Target=`"worksheets/sheet$index.xml`" />"
        $cells = ''
        $column = 0
        foreach ($header in $entry.Value) {
            $column++
            $n = $column
            $letters = ''
            while ($n -gt 0) { $remainder = ($n - 1) % 26; $letters = [char](65 + $remainder) + $letters; $n = [math]::Floor(($n - 1) / 26) }
            $value = Escape-Xml $header
            $cells += "<c r=`"${letters}1`" t=`"inlineStr`"><is><t>$value</t></is></c>"
        }
        $sheetXml = "<?xml version=`"1.0`" encoding=`"UTF-8`" standalone=`"yes`"?><worksheet xmlns=`"http://schemas.openxmlformats.org/spreadsheetml/2006/main`"><sheetViews><sheetView workbookViewId=`"0`"><pane ySplit=`"1`" topLeftCell=`"A2`" activePane=`"bottomLeft`" state=`"frozen`" /></sheetView></sheetViews><sheetData><row r=`"1`">$cells</row></sheetData><autoFilter ref=`"A1:$letters`" /></worksheet>"
        Add-ZipText $archive "xl/worksheets/sheet$index.xml" $sheetXml
    }
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml" /><Default Extension="xml" ContentType="application/xml" />' + $contentOverrides + '</Types>'
    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml" /></Relationships>'
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' + $workbookSheets + '</sheets></workbook>'
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' + $workbookRels + '</Relationships>'
    Add-ZipText $archive '[Content_Types].xml' $types
    Add-ZipText $archive '_rels/.rels' $rootRels
    Add-ZipText $archive 'xl/workbook.xml' $workbook
    Add-ZipText $archive 'xl/_rels/workbook.xml.rels' $rels
}
finally {
    $archive.Dispose()
    $fileStream.Dispose()
}
Write-Output "Created $output"
