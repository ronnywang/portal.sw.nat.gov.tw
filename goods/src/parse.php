<?php
// 將 http://data.gov.tw/opendata/Details?sno=313010000G-00001 的 tsv 資料轉成 csv
$curl = curl_init('https://fbfh.trade.gov.tw/rich/text/fhj/asp/downloadAllGoods.asp');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, 'Chrome');
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
$ret = curl_exec($curl);

$fp = fopen('php://temp', 'r+');
fputs($fp, $ret);
rewind($fp);

$output = fopen(__DIR__ . '/../good_code.csv', 'w');
fputcsv($output, array(
    '貨品號列（2~11碼）',
    '生效日期(YYYMMDD)',
    '截止日期(YYYMMDD)',
    '中文貨名',
    '英文貨名',
));

while ($line = fgets($fp)) {
    fputcsv($output, array_map('trim', explode("\t", $line)));
    
}
