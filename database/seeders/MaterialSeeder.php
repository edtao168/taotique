<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
	{
		$materials = [
			['bb_code' => '00', 'c_code' => '0', 'name' => '白水晶'],
			['bb_code' => '01', 'c_code' => '0', 'name' => '粉晶'],
			['bb_code' => '02', 'c_code' => '0', 'name' => '茶晶'],
			['bb_code' => '03', 'c_code' => '0', 'name' => '紫水晶'],
			['bb_code' => '04', 'c_code' => '0', 'name' => '紫黃晶'],
			['bb_code' => '05', 'c_code' => '0', 'name' => '黃水晶'],
			['bb_code' => '06', 'c_code' => '0', 'name' => '雲母'],
			['bb_code' => '07', 'c_code' => '0', 'name' => '黑曜石'],
			['bb_code' => '07', 'c_code' => '5', 'name' => '金曜石'],
			['bb_code' => '08', 'c_code' => '0', 'name' => '孔雀石'],
			['bb_code' => '09', 'c_code' => '0', 'name' => '瑪瑙'],
			['bb_code' => '10', 'c_code' => '0', 'name' => '紫蘇輝石'],
			['bb_code' => '11', 'c_code' => '0', 'name' => '紅膠花'],
			['bb_code' => '11', 'c_code' => '5', 'name' => '黃膠花'],
			['bb_code' => '12', 'c_code' => '0', 'name' => '虎眼石'],
			['bb_code' => '12', 'c_code' => '5', 'name' => '紅虎眼'],
			['bb_code' => '12', 'c_code' => '6', 'name' => '藍虎眼'],
			['bb_code' => '13', 'c_code' => '0', 'name' => '乳白晶'],
			['bb_code' => '14', 'c_code' => '0', 'name' => '石榴石'],
			['bb_code' => '14', 'c_code' => '5', 'name' => '紫紅'],
			['bb_code' => '14', 'c_code' => '6', 'name' => '橙紅'],
			['bb_code' => '15', 'c_code' => '0', 'name' => '藍針'],
			['bb_code' => '16', 'c_code' => '0', 'name' => '幽靈'],
			['bb_code' => '16', 'c_code' => '5', 'name' => '白幽靈'],
			['bb_code' => '17', 'c_code' => '0', 'name' => '福祿壽'],
			['bb_code' => '18', 'c_code' => '0', 'name' => '碧璽'],
			['bb_code' => '19', 'c_code' => '0', 'name' => '草莓晶'],
			['bb_code' => '20', 'c_code' => '0', 'name' => '髪晶'],
			['bb_code' => '20', 'c_code' => '5', 'name' => '黑髮晶'],
			['bb_code' => '21', 'c_code' => '0', 'name' => '紅紋石'],
			['bb_code' => '22', 'c_code' => '0', 'name' => '月光石'],
			['bb_code' => '22', 'c_code' => '5', 'name' => '橙月光'],
			['bb_code' => '23', 'c_code' => '0', 'name' => '藍沙石'],
			['bb_code' => '23', 'c_code' => '5', 'name' => '金沙石'],
			['bb_code' => '24', 'c_code' => '0', 'name' => '螢石'],
			['bb_code' => '25', 'c_code' => '0', 'name' => '東陵玉'],
			['bb_code' => '26', 'c_code' => '0', 'name' => '多寶'],
			['bb_code' => '27', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '28', 'c_code' => '0', 'name' => '堇青石'],
			['bb_code' => '29', 'c_code' => '0', 'name' => '海藍寶'],
			['bb_code' => '30', 'c_code' => '0', 'name' => '玉髓'],
			['bb_code' => '31', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '32', 'c_code' => '0', 'name' => '黑碧璽'],
			['bb_code' => '33', 'c_code' => '0', 'name' => '翡翠'],
			['bb_code' => '34', 'c_code' => '0', 'name' => '硨磲'],
			['bb_code' => '35', 'c_code' => '0', 'name' => '珊瑚'],
			['bb_code' => '36', 'c_code' => '0', 'name' => '玉'],
			['bb_code' => '37', 'c_code' => '0', 'name' => '葡萄石'],
			['bb_code' => '38', 'c_code' => '0', 'name' => '青金石'],
			['bb_code' => '39', 'c_code' => '0', 'name' => '捷克隕石'],
			['bb_code' => '40', 'c_code' => '0', 'name' => '托帕石'],
			['bb_code' => '41', 'c_code' => '0', 'name' => '鋰輝石'],
			['bb_code' => '41', 'c_code' => '5', 'name' => '紫鋰輝'],
			['bb_code' => '42', 'c_code' => '0', 'name' => '橄欖石'],
			['bb_code' => '43', 'c_code' => '0', 'name' => '綠柱石'],
			['bb_code' => '43', 'c_code' => '5', 'name' => '摩根石'],
			['bb_code' => '43', 'c_code' => '6', 'name' => '祖母綠'],
			['bb_code' => '44', 'c_code' => '0', 'name' => '方解石'],
			['bb_code' => '45', 'c_code' => '0', 'name' => '天河石'],
			['bb_code' => '45', 'c_code' => '5', 'name' => '拉長石'],
			['bb_code' => '45', 'c_code' => '6', 'name' => '斜長石'],
			['bb_code' => '46', 'c_code' => '0', 'name' => '松石'],
			['bb_code' => '47', 'c_code' => '0', 'name' => '剛玉'],
			['bb_code' => '47', 'c_code' => '5', 'name' => '紅寶石'],
			['bb_code' => '47', 'c_code' => '6', 'name' => '藍寶石'],
			['bb_code' => '48', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '49', 'c_code' => '0', 'name' => '木化石'],
			['bb_code' => '50', 'c_code' => '0', 'name' => '歐泊'],
			['bb_code' => '51', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '52', 'c_code' => '0', 'name' => '坦桑石'],
			['bb_code' => '53', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '54', 'c_code' => '0', 'name' => '透輝石'],
			['bb_code' => '55', 'c_code' => '0', 'name' => '藍晶石'],
			['bb_code' => '55', 'c_code' => '5', 'name' => '藍綠石'],
			['bb_code' => '56', 'c_code' => '0', 'name' => '琥珀'],
			['bb_code' => '56', 'c_code' => '5', 'name' => '蜜蠟'],
			['bb_code' => '57', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '58', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '59', 'c_code' => '0', 'name' => ' '],
			['bb_code' => '60', 'c_code' => '0', 'name' => '砭石'],
			['bb_code' => '95', 'c_code' => '0', 'name' => '包裝'],
			['bb_code' => '96', 'c_code' => '0', 'name' => '琉璃'],
			['bb_code' => '97', 'c_code' => '0', 'name' => '金飾'],
			['bb_code' => '98', 'c_code' => '0', 'name' => '銀飾'],
			['bb_code' => '99', 'c_code' => '0', 'name' => '配件'],
			['bb_code' => '99', 'c_code' => '9', 'name' => '其它']
		];

		foreach ($materials as $item) {
			\App\Models\MaterialDefinition::updateOrCreate(
				['bb_code' => $item['bb_code'], 'c_code' => $item['c_code']],
				$item
			);
		}
	}
}
