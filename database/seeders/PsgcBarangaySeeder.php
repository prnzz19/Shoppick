<?php

namespace Database\Seeders;

use App\Models\PsgcBarangay;
use Illuminate\Database\Seeder;

/**
 * PSGC reference seed. The Cavinti rows below are the 31 July 2025 list
 * published by PSA; update this seeder from a newer PSA release before adding
 * another municipality. It is idempotent and never changes address snapshots.
 */
class PsgcBarangaySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code'=>'0403407001','correspondence_code'=>'043407001','name'=>'Anglas','is_urban'=>false,'population'=>415],
            ['code'=>'0403407002','correspondence_code'=>'043407002','name'=>'Bangco','is_urban'=>false,'population'=>174],
            ['code'=>'0403407003','correspondence_code'=>'043407003','name'=>'Bukal','is_urban'=>false,'population'=>1298],
            ['code'=>'0403407004','correspondence_code'=>'043407004','name'=>'Bulajo','is_urban'=>false,'population'=>565],
            ['code'=>'0403407005','correspondence_code'=>'043407005','name'=>'Cansuso','is_urban'=>false,'population'=>1429],
            ['code'=>'0403407006','correspondence_code'=>'043407006','name'=>'Duhat','is_urban'=>false,'population'=>1980],
            ['code'=>'0403407007','correspondence_code'=>'043407007','name'=>'Inao-Awan','is_urban'=>false,'population'=>1075],
            ['code'=>'0403407008','correspondence_code'=>'043407008','name'=>'Kanluran Talaongan','is_urban'=>false,'population'=>1890],
            ['code'=>'0403407009','correspondence_code'=>'043407009','name'=>'Labayo','is_urban'=>false,'population'=>192],
            ['code'=>'0403407010','correspondence_code'=>'043407010','name'=>'Layasin','is_urban'=>false,'population'=>647],
            ['code'=>'0403407011','correspondence_code'=>'043407011','name'=>'Layug','is_urban'=>false,'population'=>619],
            ['code'=>'0403407012','correspondence_code'=>'043407012','name'=>'Mahipon','is_urban'=>false,'population'=>2597],
            ['code'=>'0403407014','correspondence_code'=>'043407014','name'=>'Paowin','is_urban'=>false,'population'=>987],
            ['code'=>'0403407015','correspondence_code'=>'043407015','name'=>'Poblacion','is_urban'=>true,'population'=>6698],
            ['code'=>'0403407016','correspondence_code'=>'043407016','name'=>'Sisilmin','is_urban'=>false,'population'=>382],
            ['code'=>'0403407017','correspondence_code'=>'043407017','name'=>'Silangan Talaongan','is_urban'=>false,'population'=>2050],
            ['code'=>'0403407018','correspondence_code'=>'043407018','name'=>'Sumucab','is_urban'=>false,'population'=>448],
            ['code'=>'0403407019','correspondence_code'=>'043407019','name'=>'Tibatib','is_urban'=>false,'population'=>212],
            ['code'=>'0403407020','correspondence_code'=>'043407020','name'=>'Udia','is_urban'=>false,'population'=>1082],
        ];

        foreach ($rows as $row) {
            PsgcBarangay::updateOrCreate(['code' => $row['code']], $row + [
                'province_code' => '04', 'province' => 'Laguna',
                'municipality_code' => '0403407000', 'municipality' => 'Cavinti',
            ]);
        }
    }
}
