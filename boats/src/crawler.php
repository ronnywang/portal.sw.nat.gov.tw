<?php

class Crawler
{
    public function getBoatsByDate($date)
    {
        $url = 'http://portal.sw.nat.gov.tw/APGQ/GB330!query';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'vslRegNo=&shipName=&voyagNo=&choice=E&estArDate=' . urlencode($date));
        $ret = curl_exec($curl);
        $ret = json_decode($ret);
        curl_close($curl);
        return $ret;
    }

    public function main()
    {
        $columns = array(
            'vslRegNo' => '海運通關號碼(原船隻掛號)',
            'estClearanceDate' => '預定結關日期時間',
            'vslSign' => '船舶呼號',
            'voyageFlightNo' => '航次',
            'actuArDate' => '到港日期時間',
            'clearanceDate' => '船隻結關日期時間',
            'imoNo' => '船(機)代碼(IMO NO)',
            'exielmmNote' => '出口裝船清表表頭傳輸註記',
            'transTypeCd' => '海空運別',
            'vslName' => '船名',
            'lastPorCd' => '到港前一港',
            'exCustCd' => '出口裝船關別',
            'wharfCd' => '停泊碼頭',
            'nextPorCd' => '航行次一港',
            'shipCoCd' => '船公司代碼',
            'estDpDate' => '預定開航日期時間',
            'estArDate' => '預定到港日期',
        );
        $output = fopen('php://output', 'w');
        fputcsv($output, array_values($columns));

        for ($i = 1; $i <= 31; $i ++) {
            $ret = $this->getBoatsByDate('102/07/' . sprintf("%02d", $i));
            foreach ($ret->data as $data) {
                $values = array();
                foreach ($columns as $id => $name) {
                    $values[] = $data->{$id};
                }
                fputcsv($output, $values);
            }
        }
    }
}

$c = new Crawler;
$c->main();
