<?php
namespace Rosetta\Services\MasterData;

use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\Cast\Object_;
use Rosetta\Models\MasterData;
use Rosetta\Models\Setting;
use Rosetta\Services\MasterData\MasterDataServiceInterface;
class MasterDataService implements MasterDataServiceInterface
{
	public function get()
	{
		return MasterData::select('type','key','value','sort')->get()->groupBy('type')->toArray();
	}
	public function getTechSpecs()
	{
		return DB::connection('cms')->table('mst_attributes')->select('id','title')->get();
	}
	public function getImageClasses()
	{
		return DB::connection('cms')->table('image_classes')->select('id','title')->get();	
	}
	public function getImageClassId($title)
	{
		return DB::connection('cms')->table('image_classes')->select('id','title')->where('title', '=', $title)->first()->id;
	}
	public function getTenants($term)
	{
		$setting = Setting::where('key','all_tenants')->first();
		if(!$setting)
		{
			return [];
		}
		$all_tenants = json_decode($setting->value);
		$tenants = [];
		foreach($all_tenants as $tenant)
		{
			$user = DB::connection($tenant->db)
					->table('users')
					->where('email',$term)
					->orWhere('phone',$term)
					->whereNull('deleted_at')
					->first();
			if($user)
			{
				$tenants[] = (Object)[
					"name" => $tenant->name,
					"url" => $tenant->url,
				];
			}
		}
		return $tenants;
	}
	public function getStock_point_tenants($term)
	{
		$setting = Setting::where('key','stock_point_tenants')->first();
		if(!$setting)
		{
			return [];
		}
		$all_tenants = json_decode($setting->value);
		$tenants = [];
		foreach($all_tenants as $tenant)
		{
			$user = DB::connection($tenant->db)
					->table('stock_points')
					->where('code',$term)
					->first();
			if($user)
			{
				$tenants[] = (Object)[
					"name" => $tenant->name,
					"url" => $tenant->url,
				];
			}
		}//#776
		return $tenants;
	}
}