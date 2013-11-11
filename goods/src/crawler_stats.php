<?php

class Crawler
{
    protected $_good_ids = null;
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
        $fp = fopen(__DIR__ . '/../good_code.csv', 'r');
        $good_ids = array();

        $lens = array(2 => 0,4 => 2,6 => 4,8 => 6,11 => 8);
        while ($rows = fgetcsv($fp)) {
            $len = strlen($rows[0]);
            if (!array_key_exists($len, $lens)) {
                continue;
            }
            $prefix = $lens[$len] ? substr($rows[0], 0, $lens[$len]) : 'root';
            if (!array_key_exists($prefix, $good_ids)) {
                $good_ids[$prefix] = array();
            }
            $good_ids[$prefix][] = $rows[0];
        }
        return $this->_good_ids = $good_ids;
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
            'searchInfo.goodsType' => strlen($ids[0]),
            'searchInfo.goodsCodeGroup' => implode(',', $ids),
            'searchInfo.CountryName' => '請點選國家地區',
            'searchInfo.Type' => 'rbMoney1',
            'searchInfo.GroupType' => 'rbByGood',
            'Search' => '開始查詢',
        );
        $params['searchInfo.goodsCodeGroup'] = implode(',', $ids);
        if (strlen($ids[0]) == 11) {
            $params['searchInfo.goodsCodeGroup'] = implode(',', array_map(function($a){ return substr($a, 0, 10); }, $ids));
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Chrome');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        $ret = curl_exec($curl);
        if (!$ret) {
            var_dump(curl_getinfo($curl));
            throw new Exception('failed');
            exit;
        }

        //$ret = file_get_contents('output.html');

        $doc = new DOMDocument;
        @$doc->loadHTML($ret);
        $table_dom = $doc->getElementById('dataList');
        $tr_doms = $table_dom->getElementsByTagName('tr');

        for ($i = 1; $i < $tr_doms->length; $i ++) {
            $rows = array();
            $td_doms = $tr_doms->item($i)->getElementsByTagName('td');

            if (strlen($ids[0]) == 11) {
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

    public function main()
    {
        for ($year = 102; $year >= 92; $year --) {
            for ($month = 1; $month <= 12; $month ++) {
                if ($year == 102 and $month == 1) {
                    continue;
                }
                if ($year == 102 and $month > 8) {
                    continue;
                }
                $this->crawlMonth($year, $month);
            }
        }
    }

    public function crawlFromFile($from, $to, $year, $month)
    {
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
        // 兩碼
        $this->_output = fopen(__DIR__ . "/../{$year}-{$month}-2code.csv", 'w');
        $this->outputCSV(array('貨品分類', '國家', '重量', '重量單位', '價值'));
        $good_ids = $this->getGoodIds();
        if (!$ids = $good_ids['root']) {
            return;
        }
        $this->crawlIds($ids, $year, $month);
        fclose($this->_output);

        $this->crawlFromFile('2code', '4code', $year, $month);
        $this->crawlFromFile('4code', '6code', $year, $month);
        $this->crawlFromFile('6code', '8code', $year, $month);
        $this->crawlFromFile('8code', '11code', $year, $month);

    }
}

$c = new Crawler;
$c->main($_SERVER['argv']);
