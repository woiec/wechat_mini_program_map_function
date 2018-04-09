<?php

/**
 * 腾讯地图相关函数（小程序）
 * 小程序地图API：https://developers.weixin.qq.com/miniprogram/dev/component/map.html
 * @author 方淞
 * @link www.xpzfs.com
 * @time 2018/4/8
 */

/**
 * 获取两个经纬度坐标之间的距离(米)
 * @param array $location1[longitude, latitude]
 * @param array $location2[longitude, latitude]
 * @return float
 */
function getDistance(array $location1, array $location2): float
{
    $rad_lat1 = $location1[1] * pi() / 180.0;
    $rad_lat2 = $location2[1] * pi() / 180.0;
    $a = $rad_lat1 - $rad_lat2;
    $b = $location1[0] * pi() / 180.0 - $location2[0] * pi() / 180.0;
    $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($rad_lat1) * cos($rad_lat2) * pow(sin($b / 2), 2)));
    $s = $s * 6378.137; //地球半径
    $s = round($s * 10000) / 10;
    return $s;
}

/**
 * 过滤指定距离内的坐标点(米)
 * @param array $location_data
 * @param int $distance
 * @return array
 */
function filterLocation(array $location_data, int $distance): array
{
    $location_start = $location_data[0];
    $location_end = end($location_data);
    array_shift($location_data);
    array_pop($location_data);
    $previous_distance_count = 0;
    $new_location_data = [];
    foreach ($location_data as $k => $v) {
        $previous_distance_count += $k - 1 < 0 ? 0 : $v['distance'];
        if ($previous_distance_count > $distance) {
            $v['distance'] = $previous_distance_count;
            $new_location_data[] = $v;
            $previous_distance_count = 0;
        }
    }
    array_unshift($new_location_data, $location_start);
    array_push($new_location_data, $location_end);
    return $new_location_data;
}

/**
 * 计算多个经纬度坐标的中心点
 * @param array $location_data
 * @return array[longitude, latitude]
 */
function getLocationCentre(array $location_data): array
{
    $total = count($location_data);
    $x = $y = $z = 0;
    foreach ($location_data as $k => $v) {
        $lon = $v['longitude'] * pi() / 180;
        $lat = $v['latitude'] * pi() / 180;
        $x += cos($lat) * cos($lon);
        $y += cos($lat) * sin($lon);
        $z += sin($lat);
    }
    $x /= $total;
    $y /= $total;
    $z /= $total;
    $t_lon = atan2($y, $x);
    $t_hyp = sqrt($x * $x + $y * $y);
    $t_lat = atan2($z, $t_hyp);
    $location = [
        'longitude' => $t_lon * 180 / pi(),
        'latitude' => $t_lat * 180 / pi()
    ];
    return $location;
}

/**
 * 计算多个经纬度坐标的中心点(适合小于400Km)
 * @param array $location_data
 * @return array[longitude, latitude]
 */
function getLocationCentre2(array $location_data): array
{
    $total = count($location_data);
    $lon = $lat = 0;
    foreach ($location_data as $k => $v) {
        $lon += $v['longitude'] * pi() / 180;
        $lat += $v['latitude'] * pi() / 180;
    }
    $lon /= $total;
    $lat /= $total;
    $location = [
        'longitude' => $lon * 180 / pi(),
        'latitude' => $lat * 180 / pi()
    ];
    return $location;
}

/**
 * 计算多个坐标点离大矩形最近的经纬度
 * @param array $location_data
 * @return array
 */
function getMinMaxLocation(array $location_data): array
{
    $location_arr = [
        'longitude' => [],
        'latitude' => []
    ];
    foreach ($location_data as $k => $v) {
        unset($location_data[$k]['distance']);
        $location_arr['longitude'][$k] = $v['longitude'];
        $location_arr['latitude'][$k] = $v['latitude'];
    }
    $lon_min = array_keys($location_arr['longitude'], min($location_arr['longitude']));
    $lon_max = array_keys($location_arr['longitude'], max($location_arr['longitude']));
    $lat_min = array_keys($location_arr['latitude'], min($location_arr['latitude']));
    $lat_max = array_keys($location_arr['latitude'], max($location_arr['latitude']));
    $return_location = [
        $lon_min[0] => $location_data[$lon_min[0]],
        $lon_max[0] => $location_data[$lon_max[0]],
        $lat_min[0] => $location_data[$lat_min[0]],
        $lat_max[0] => $location_data[$lat_max[0]],
    ];
    return array_values($return_location);
}

//使用==============
$json_data = json_decode(file_get_contents('./data.json'), true);
foreach ($json_data as $k => $v) {
    if ($k === 0) {
        $json_data[$k]['distance'] = 0;
    } else {
        $last_location = $json_data[$k - 1];
        $json_data[$k]['distance'] = getDistance([$last_location['longitude'], $last_location['latitude']], [$v['longitude'], $v['latitude']]);
    }
}

//过滤指定距离内的坐标点
$new_data = filterLocation($json_data, 5);
//计算多个经纬度坐标的中心点
$new_data2 = getLocationCentre($new_data);
$new_data21 = getLocationCentre2($new_data);
//计算多个坐标点离大矩形最近的经纬度
$new_data3 = getMinMaxLocation($new_data);

//输出
$tmp_distance1 = 0;
foreach ($json_data as $k => $v) {
    $tmp_distance1 += $v['distance'];
}
$tmp_distance2 = 0;
foreach ($new_data as $k => $v) {
    $tmp_distance2 += $v['distance'];
}
echo '1、整个数据中的经纬度数量：' . count($json_data) . '；距离和：' . $tmp_distance1 . '米<br>';
echo '2、过滤（小于5米）后的经纬度数量：' . count($new_data) . '；距离和：' . $tmp_distance2 . '米<br>';
echo '3、该经纬度组中的中心点为：' . implode(',', $new_data2) . '<br>&nbsp;&nbsp;&nbsp;&nbsp;（算法2：' . implode(',', $new_data21) . '）<br>';
echo '4、离大矩形最近的经纬度分别为：<br>';
foreach ($new_data3 as $k => $v) {
    echo $v['longitude'] . ',' . $v['latitude'] . '<br>';
}
