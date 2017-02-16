<?php


class Crawler
{
    protected $_output = null;

    public function outputCSV($rows)
    {
        if (!$this->_output) {
            $this->_output = fopen('php://output', 'w');
        }
        fputcsv($this->_output, $rows);
    }

    public function crawl($code = '')
    {
        list($id) = explode(' ', $code);
        $lens = array(0 => 2,2 => 4,4 => 6,6 => 8,8 => 11);
        $len = strlen($id);
        if (!array_key_exists($len, $lens)) {
            return;
        }
        $target_len = $lens[$len];

        if ($id and file_exists("good-cache/{$id}") and filesize("good-cache/{$id}")) {
            $ret = file_get_contents("good-cache/{$id}");
        } else {
            error_log($id);
            $url = 'https://portal.sw.nat.gov.tw/APGA/GoodsSearch_toByCode' . $target_len;

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            if ($len) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, 'code' . $len . '=' . urlencode($code));
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, '');
            }
            curl_setopt($curl, CURLOPT_USERAGENT, 'Chrome');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $ret = curl_exec($curl);
            if ($id) {
                file_put_contents("good-cache/{$id}", $ret);
            }
        }

        $ret = json_decode($ret);
        //  [cnyChinese] => 0201101000  特殊品級屠體及半片屠體牛肉，生鮮或冷藏
        //  [cnyEnglish] => 0201101000   Special quality carcasses and half-carcasses of bovine animals, fresh or chilled
 
        foreach ($ret->{'listBy' . $target_len} as $row) {
            list($id, $cname) = preg_split('/\s+/', $row->cnyChinese, 2);
            list($id, $ename) = preg_split('/\s+/', $row->cnyEnglish, 2);

            $this->outputCSV(array($id, trim($cname), trim($ename)));

            $this->crawl($row->cnyChinese);
        }
    }

    public function main()
    {
        $this->outputCSV(array(
            '貨品號列（2~11碼）',
            '中文貨名',
            '英文貨名',
        ));
        $this->crawl('');
    }

}

$c = new Crawler;
$c->main();
