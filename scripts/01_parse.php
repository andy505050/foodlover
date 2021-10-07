<?php
$basePath = dirname(__DIR__);
$config = require $basePath . '/config.php';
$geocodingPath = $basePath . '/raw/geocoding';
if (!file_exists($geocodingPath)) {
    mkdir($geocodingPath, 0777, true);
}
$dataPath = $basePath . '/data/point';
$pairs = [
    '　' => '',
];
$pool = [];
foreach (glob($basePath . '/raw/data/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if (!empty($json)) {
        foreach ($json as $point) {
            if (is_string($point['category'])) {
                $point['category'] = [$point['category']];
            }
            $pos = strpos($point['market_address'], '號');
            if (false !== $pos) {
                $point['market_address'] = strtr($point['market_address'], $pairs);
                $address = substr($point['market_address'], 0, $pos) . '號';
                $address = str_replace($point['city'], '', $address);
                $address = trim(str_replace($point['area'], '', $address));
                $address = $point['city'] . $point['area'] . $address;
                $geocodingFile = $geocodingPath . '/' . $address . '.json';
                // if (!file_exists($geocodingFile)) {
                //     $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                //         'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                //         'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                //         'oAddress' => $address, //所要查詢的門牌位置
                //         'oSRS' => 'EPSG:4326', //回傳的坐標系統
                //         'oFuzzyType' => '2', //模糊比對的代碼
                //         'oResultDataType' => 'JSON', //回傳的資料格式
                //         'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                //         'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                //         'oIsLockCounty' => 'true', //是否鎖定縣市
                //         'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                //         'oIsLockVillage' => 'false', //是否鎖定村里
                //         'oIsLockRoadSection' => 'false', //是否鎖定路段
                //         'oIsLockLane' => 'false', //是否鎖定巷
                //         'oIsLockAlley' => 'false', //是否鎖定弄
                //         'oIsLockArea' => 'false', //是否鎖定地區
                //         'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                //         'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                //         'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                //         'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
                //     ]);
                //     $content = file_get_contents($apiUrl);
                //     $pos = strpos($content, '{');
                //     $posEnd = strrpos($content, '}') + 1;
                //     $resultline = substr($content, $pos, $posEnd - $pos);
                //     if (strlen($resultline) > 10) {
                //         file_put_contents($geocodingFile, substr($content, $pos, $posEnd - $pos));
                //     } else {
                //         echo $content . "\n";
                //     }
                // }
                if (file_exists($geocodingFile)) {
                    $json = json_decode(file_get_contents($geocodingFile), true);
                    if (!empty($json['AddressList'][0]['X'])) {
                        if (!isset($pool[$point['city']])) {
                            $pool[$point['city']] = [];
                        }
                        if(is_array($point['market_name'])) {
                            $point['market_name'] = implode('/', $point['market_name']);
                        }
                        if (!isset($pool[$point['city']][$address])) {
                            $pool[$point['city']][$address] = [
                                'name' => $point['market_name'],
                                'address' => $point['market_address'],
                                'x' => floatval($json['AddressList'][0]['X']),
                                'y' => floatval($json['AddressList'][0]['Y']),
                                'shops' => [],
                            ];
                            //echo "{$address}/{$json['AddressList'][0]['X']}/{$json['AddressList'][0]['Y']}\n";
                        }
                        if (is_array($point['shop'])) {
                            $point['shop'] = implode('', $point['shop']);
                        }
                        if (is_array($point['pay_list'])) {
                            $point['pay_list'] = implode(' / ', $point['pay_list']);
                        }
                        $pool[$point['city']][$address]['shops'][] = [
                            'shop' => $point['shop'],
                            'pay_list' => $point['pay_list'],
                        ];
                    }
                }
            }
        }
    }
}

$fc = [];
foreach ($pool as $city => $lv1) {
    $fc[$city] = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];
    $filePath = $dataPath . '/' . $city;
    if (!file_exists($filePath)) {
        mkdir($filePath, 0777, true);
    }
    foreach ($lv1 as $address => $data) {
        $dataFile = $filePath . '/' . $address . '.json';
        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $fc[$city]['features'][] = [
            'type' => 'Feature',
            'properties' => [
                'k' => $address,
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    $data['x'],
                    $data['y'],
                ],
            ],
        ];
    }
    file_put_contents($basePath . '/data/' . $city . '.json', json_encode($fc[$city], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}