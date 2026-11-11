<?php
namespace Rosetta\Services\CMS;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Rosetta\Models\Product\Category;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Rosetta\Services\CMS\CMSServiceInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CMSService implements CMSServiceInterface
{
	public function createProduct(String $navID, Array $params)
	{
		$rosCategory = Category::find($params['category']);
		$category = DB::connection('cms')
			->table('mst_product_categories')
			->select('id')
			->where('title',$rosCategory->description)
			->first();
		$cmsCategoryID = null;
		if(!$category)
		{
			$cmsCategoryID = $this->createCategory($rosCategory);
			$cmsProductID = $this->insertProduct($cmsCategoryID,$params);
		}
		else
		{
			$cmsCategoryID = $category->id;
			$cmsProduct = DB::connection('cms')
				->table('products')
				->where('mst_product_categories_id',$cmsCategoryID)
				->where('name',$params['collection'])
				->first();
			if(!$cmsProduct)
			{
				$cmsProductID = $this->insertProduct($cmsCategoryID,$params);
			}
			else
			{
				$cmsProductID = $cmsProduct->id;
			}
		}
		foreach($params['techspecs'] as $techspec)
		{
			if(array_get($techspec,'id'))
			{
				DB::connection('cms')
					->table('product_attributes')
					->where('id','=',$techspec['id'])
					->update(['value' => $techspec['value']]);
			}
			else
			{
				DB::connection('cms')
					->table('product_attributes')
					->insert([
						'product_id' => $cmsProductID,
						'attribute_id' => $techspec['attribute_id'],
						'value' => $techspec['value'],
						'created_datetime' => Carbon::now()->format('Y-m-d H:i:s')
					]);
			}
		}
		$cmsColourID = $this->insertColour($navID,$cmsCategoryID,$cmsProductID,$params);
		return true;
	}
	public function updateProduct(String $navID,Array $params)
	{
		$rosCategory = Category::find($params['category']);
		$category = DB::connection('cms')
			->table('mst_product_categories')
			->select('id')
			->where('title',$rosCategory->description)
			->first();
		$cmsCategoryID = null;
		if(!$category)
		{
			$cmsCategoryID = $this->createCategory($rosCategory);
			$cmsProductID = $this->insertProduct($cmsCategoryID,$params);
		}
		else
		{
			$cmsCategoryID = $category->id;
			$cmsProduct = DB::connection('cms')
				->table('products')
				->where('mst_product_categories_id',$cmsCategoryID)
				->where('name',$params['collection'])
				->first();
			if(!$cmsProduct)
			{
				$cmsProductID = $this->insertProduct($cmsCategoryID,$params);
			}
			else
			{
				$cmsProductID = $cmsProduct->id;
			}
		}
		foreach($params['techspecs'] as $techspec)
		{
			if($techspec['id'])
			{
				DB::connection('cms')
					->table('product_attributes')
					->where('id','=',$techspec['id'])
					->update(['value' => $techspec['value']]);
			}
			else
			{
				DB::connection('cms')
					->table('product_attributes')
					->insert([
						'product_id' => $cmsProductID,
						'attribute_id' => $techspec['attribute_id'],
						'value' => $techspec['value'],
						'created_datetime' => Carbon::now()->format('Y-m-d H:i:s')
					]);
			}
		}
		$this->updateColour($navID,$cmsCategoryID,$cmsProductID,$params);
	}
	protected function updateColour(String $navID,int $cmsCategoryID,int $cmsProductID, Array $params)
	{
		$product = DB::connection('cms')
			->table('products_colors')
			->select('products_colors.id','products_colors.image_class_id','products_colors.image_path')
			->where('products_colors.nav_id','=',$navID)
			->first();
		if(!$product)
		{
				$product = $this->insertColour($navID,$cmsCategoryID,$cmsProductID,$params);
		}
		$product = DB::connection('cms')
			->table('products_colors')
			->select('products_colors.id','products_colors.image_class_id','products_colors.image_path')
			->where('products_colors.nav_id','=',$navID)
			->first();
		$imageName = $product->image_path;
		$imageClass = $product->image_class_id;
		if(array_has($params,'image'))
		{
			$temp = tempnam(sys_get_temp_dir(), 'myApp_');
			file_put_contents($temp,base64_decode($params['image']));
			$mime = File::mimeType($temp);
			$extension = (explode("/", $mime))[1];
			$imageName = Uuid::uuid4().".$extension";
			File::move($temp,sys_get_temp_dir() . '/' . $imageName);
			$this->uploadImageToCMS($navID,sys_get_temp_dir() . '/' . $imageName);
			$imageClass = $params['image_class'];
		}

		

		DB::connection('cms')
			->table('products_colors')
            ->where('id', $product->id)
            ->update(
            	[
            		'products_id' => $cmsProductID,
					'image_class_id' => $imageClass,
					'image_path' => $imageName,
					'product_color_code' => str_replace($params['collection'].' ', '', $params['name']),//$params['name'],//$params['colour'],
					'description' => $params['description'],
					'search_description' => $params['search_description'],
					'colour' => $params['colour'],
            	]
            );

	}
	protected function uploadImageToCMS(String $navID,$file)
	{
		$client = new Client([
			'headers' =>[
				'SecretKey' => config('cms.image_upload_secret'),
				'user-agent'=>'PostmanRuntime/7.29.0'
			]
		]);
	
		try{
			$response = $client->post(config('cms.image_upload_url'),[
				'multipart' => [
					[
						'name'     => 'nav_id',
						'contents' => $navID
					],
					[
						'name'     => 'image',
						'filename' => basename($file),
						'contents' => Utils::tryFopen($file,'r')
					],
				]
			]);
		}
		catch(ClientException $e)
		{
			
		}
		catch(ServerException $e)
		{

		}
	}
	protected function insertColour(String $navID,int $cmsCategoryID,int $cmsProductID, Array $params)
	{
		$imageName = "";
		$imageClass = 0;
		if(array_has($params,'image'))
		{
			$temp = tempnam(sys_get_temp_dir(), 'myApp_');
			file_put_contents($temp,base64_decode($params['image']));
			$mime = File::mimeType($temp);
			$extension = (explode("/", $mime))[1];
			$imageName = Uuid::uuid4().".$extension";
			File::move($temp,sys_get_temp_dir() . '/' . $imageName);
			$this->uploadImageToCMS($navID,sys_get_temp_dir() . '/' . $imageName);
			$imageClass = $params['image_class'];
		}
		return DB::connection('cms')
			->table('products_colors')
			->insertGetId([
				'products_id' => $cmsProductID,
				'image_class_id' => $imageClass,
				'image_path' => $imageName,
				'is_rectangular_image' => '',
				'is_cover_image' => 0,
				'product_color_code' => str_replace($params['collection'].' ', '', $params['name']),//$params['color'],
				'nav_id' => $navID,
				'description' => $params['description'],
				'search_description' => $params['search_description'],
				'colour' => $params['colour'],
				'is_active' => 0,
				'display_in_configurator' => 0,
			]);
	}
	protected function insertProduct(int $cmsCategoryID,Array $params)
	{
		return DB::connection('cms')
			->table('products')
			->insertGetId([
				'mst_product_categories_id' => $cmsCategoryID,
				'name' => $params['collection'],
				'description' =>"",
				'comments' => "",
				'created_by' => 1,
				'technical_specification' =>'',
				'product_pdf' => '',
				'is_active' => 0,
				'is_most_popular' => 0,
				'is_frontend_product' => 0,
				'page_title' => '',
				'page_description' => '',
				'page_keywords' => '',
				'procedure_template' => '',
				'brochure_template' => '',
				'video_code' => '',
				'sales_unit_of_measure' => $params['sales_unit_of_measure'],
				'measure_unit' => $params['base_unit_of_measure'],
				'modified_datetime' => Carbon::now()->format('Y-m-d H:i:s'),
				'created_datetime' => Carbon::now()->format('Y-m-d H:i:s'),
			]);
	}
	public function createCategory(Category $rosCategory)
	{
		return DB::connection('cms')
			->table('mst_product_categories')
			->insertGetId([
				"title" => $rosCategory->description,
				'description' => "", 
				'category_icon' => "", 
				'slider_image' => "",
				'is_active' => 0,
				'is_frontend_category' => 0,
				'created_datetime' => Carbon::now()->format('Y-m-d H:i:s')
			]);
	}
	public function getTechSpecs($category_code,$collection_code)
	{
		return DB::connection('cms')
			->table('product_attributes')
			->join('products','products.id','=','product_attributes.product_id')
			->join('mst_product_categories','mst_product_categories.id','=','products.mst_product_categories_id')
			->join('mst_attributes','mst_attributes.id','=','product_attributes.attribute_id')
			->where('mst_product_categories.title','=',$category_code)
			->where('products.name','=',$collection_code)
			->select('product_attributes.id','mst_attributes.id as attribute_id','product_attributes.value','mst_attributes.title')
			->get();
		return [];
	}
	public function getProductImage($navId)
	{
		$product = DB::connection('cms')
			->table('products_colors')
			->join('image_classes','image_classes.id','=','products_colors.image_class_id')
			->join('products','products.id','=','products_colors.products_id')
			->select('products_colors.image_path','products.id as product_id','products.mst_product_categories_id as category_id','image_classes.id as image_class_id','image_classes.title as image_class_title')
			->where('products_colors.nav_id','=',$navId)
			->first();
		if(!$product)
			return null;
		return [
			"url" => config('cms.image_base_url'). "{$product->category_id}/{$product->product_id}/{$product->image_path}",
			"class" => [
				"id" => $product->image_class_id, 
				"title" => $product->image_class_title
			]
		];
		
	}
}