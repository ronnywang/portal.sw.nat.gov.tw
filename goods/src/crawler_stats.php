<?php

class Crawler
{
    protected $_good_ids = null;
    protected $_id_used = array();
    /**
     * getGoodIds 取得各種 ID 的 parent 關係
     * 
     * @access public
     * @return void
     */
    public function getGoodIds()
    {
        if ($this->_good_ids) {
            return $this->_good_ids;
        }
        $this->_good_ids = array();
        $fp = fopen(__DIR__ . '/../good_code.csv', 'r');
        while ($rows = fgetcsv($fp)) {
            $this->addGoodID($rows[0]);
        }

        return $this->_good_ids;
    }

    protected function addGoodID($id)
    {
        $lens = array(2 => 0,4 => 2,6 => 4,8 => 6,10 => 8);
        $len = strlen($id);

        $this->_id_used[$id] = true;

        if (!array_key_exists($len, $lens)) {
            return;
        }

        $prefix = $lens[$len] ? substr($id, 0, $lens[$len]) : 'root';
        if (!array_key_exists($prefix, $this->_good_ids)) {
            $this->_good_ids[$prefix] = array();
        }

        $this->_good_ids[$prefix][] = $id;
        if ($prefix != 'root' and !$this->_id_used[$prefix]) {
            $this->addGoodID($prefix);
        }
    }

    protected $_output = null;

    public function outputCSV($rows)
    {
        if (!$this->_output) {
            $this->_output = fopen('php://output', 'w');
        }

        fputcsv($this->_output, $rows);
    }

    public function crawlIds($ids, $year, $month)
    {
        $url = 'https://portal.sw.nat.gov.tw/APGA/GA03_LIST';
        error_log("crawling {$year}/{$month}" . implode(',', $ids));
        $params = array(
            'minYear' => '92',
            'maxYear' => '102',
            'maxMonth' => '8',
            'minMonth' => '1',
            'maxYearByYear' => '101',
            'searchInfo.TypePort' => '1',
            'searchInfo.TypeTime' => '0',
            'searchInfo.StartYear' => $year,
            'searchInfo.StartMonth' => $month,
            'searchInfo.EndMonth' => $month,
            'searchInfo.goodsType' => strlen($ids[0]) < 10 ? strlen($ids[0]) : 11,
            'earchInfo.goodsCodeGroup' => implode(',', $ids),
            'searchInfo.CountryName' => '請點選國家地區',
            'searchInfo.Type' => 'rbMoney1',
            'searchInfo.GroupType' => 'rbByGood',
            'Search' => '開始查詢',
        );
        $params['searchInfo.goodsCodeGroup'] = implode(',', $ids);
        $curl = curl_init($url);
        $fp = tmpfile();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Chrome');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_exec($curl);
        curl_close($curl);

        $doc = new DOMDocument;
        @$doc->loadHTMLFile(stream_get_meta_data($fp)['uri']);
        $table_dom = $doc->getElementById('dataList');
        $tr_doms = $table_dom->getElementsByTagName('tr');

        for ($i = 1; $i < $tr_doms->length; $i ++) {
            $rows = array();
            $td_doms = $tr_doms->item($i)->getElementsByTagName('td');

            if (strlen($ids[0]) == 10) {
                $rows[] = trim($td_doms->item(0)->nodeValue); // 貨品分類
                //$rows[] = $td_doms->item(1)->nodeValue; // 中文貨名
                //$rows[] = $td_doms->item(2)->nodeValue; // 英文貨名
                $rows[] = trim($td_doms->item(3)->nodeValue); // 國家
                $rows[] = str_replace(',', '', trim($td_doms->item(4)->nodeValue)); // 數量
                $rows[] = trim($td_doms->item(5)->nodeValue); // 數量單位
                $rows[] = str_replace(',', '', trim($td_doms->item(6)->nodeValue)); // 重量
                $rows[] = trim($td_doms->item(7)->nodeValue); // 重量單位
                $rows[] = str_replace(',', '', trim($td_doms->item(8)->nodeValue)); // 價值
            } else {
                $rows[] = trim($td_doms->item(0)->nodeValue); // 貨品分類
                //$rows[] = $td_doms->item(1)->nodeValue; // 中文貨名
                //$rows[] = $td_doms->item(2)->nodeValue; // 英文貨名
                $rows[] = trim($td_doms->item(3)->nodeValue); // 國家
                $rows[] = str_replace(',', '', trim($td_doms->item(4)->nodeValue)); // 重量
                $rows[] = trim($td_doms->item(5)->nodeValue); // 重量單位
                $rows[] = str_replace(',', '', trim($td_doms->item(6)->nodeValue)); // 價值
            }

            $this->outputCSV($rows);
        }
    }

    public function main($argv)
    {
        if ($argv[1]) {
            $years = array_slice($argv, 1);
        } else {
            $years = range(102, 92);
        }
        foreach ($years as $year) {
            for ($month = 1; $month <= 12; $month ++) {
                if ($year == 102 and $month > 8) {
                    continue;
                }
                $this->crawlMonth($year, $month);
            }
        }
    }

    public function crawlFromFile($from, $to, $year, $month)
    {
        if (file_exists(__DIR__. "/../{$year}-{$month}-{$to}.csv")){
            return;
        }
        $fp = fopen(__DIR__ . "/../{$year}-{$month}-{$from}.csv", 'r');
        $ids = array();
        while ($rows = fgetcsv($fp)) {
            $ids[$rows[0]] = true;
        }
        $good_ids = $this->getGoodIds();

        $this->_output = fopen(__DIR__ . "/../{$year}-{$month}-{$to}.csv", 'w');
        if ($to == '11code') {
            $this->outputCSV(array('貨品分類', '國家', '數量', '數量單位', '重量', '重量單位', '價值'));
        } else {
            $this->outputCSV(array('貨品分類', '國家', '重量', '重量單位', '價值'));
        }

        $query_ids = array();
        foreach ($ids as $id => $xxx) {
            if (!$good_ids[$id]) {
                continue;
            }
            $query_ids = array_merge($query_ids, $good_ids[$id]);

            if (count($query_ids) > 80) {
                $this->crawlIds($query_ids, $year, $month);
                $query_ids = array();
            }
        }
        if (count($query_ids)) {
            $this->crawlIds($query_ids, $year, $month);
            $query_ids = array();
        }
        fclose($this->_output);
    }

    public function crawlMonth($year, $month)
    {
        if (!file_exists(__DIR__ . "/../{$year}-{$month}-2code.csv")){
            // 兩碼
            $this->_output = fopen(__DIR__ . "/../{$year}-{$month}-2code.csv", 'w');
            $this->outputCSV(array('貨品分類', '國家', '重量', '重量單位', '價值'));
            $good_ids = $this->getGoodIds();
            if (!$ids = $good_ids['root']) {
                return;
            }
            $this->crawlIds($ids, $year, $month);
            fclose($this->_output);
        }

        $this->crawlFromFile('2code', '4code', $year, $month);
        $this->crawlFromFile('4code', '6code', $year, $month);
        $this->crawlFromFile('6code', '8code', $year, $month);
        $this->crawlFromFile('8code', '11code', $year, $month);

    }
}

$c = new Crawler;
$c->main($_SERVER['argv']);
