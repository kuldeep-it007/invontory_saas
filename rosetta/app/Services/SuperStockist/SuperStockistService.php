<?php

namespace Rosetta\Services\SuperStockist;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Rosetta\Exceptions\FileImport\FileNotFoundException;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Services\NAV\STAServiceInterface;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;

class SuperStockistService implements SuperStockistServiceInterface
{
	protected $customerService, $staService;
	public function __construct(CustomerServiceInterface $customerService, STAServiceInterface $staService)
	{
		$this->customerService = $customerService;
		$this->staService = $staService;
	}
	public function getSuperStockists(Company $company, array $params)
	{
		if (array_has($params, 'page')) {
			$superStockists = $company->super_stockists()->when($params, function ($query) use ($params) {
				if (array_has($params, 'super_stockist_name')) {
					$query->where('name', 'like', '%' . $params['super_stockist_name'] . '%');
				}
				if (array_has($params, 'super_stockist_id')) {
					$query->where('nav_id', '=', $params['super_stockist_id']);
				}
			})->orderBy('created_at', 'desc')->paginate($params['per_page']);
		} else {
			$superStockists = $company->super_stockists;
		}
		return $superStockists;
	}
	public function sync(User $user, Company $company, $nav_id)
	{
		$navSuperStockist = $this->customerService->get($company, $nav_id);
		$superStockist = new SuperStockist;
		$superStockist->company()->associate($company);
		$superStockist->nav_id = $navSuperStockist->getNo();
		$superStockist->name = $navSuperStockist->getName();
		$superStockist->city_code = $navSuperStockist->getCity();
		$superStockist->state_code = $navSuperStockist->getState_Code();
		$superStockist->post_code = $navSuperStockist->getPost_Code();
		$superStockist->author()->associate($user);
		$superStockist->save();
		$superStockist->setCustomerCard($navSuperStockist);
		return $superStockist;
	}
	public function create(User $user, Company $company, array $params)
	{
		$navSuperStockist = $this->customerService->createSuperStockist($company, $params);
		$superStockist = new SuperStockist;
		$superStockist->name = $params['name'];
		$superStockist->post_code = $params['zipcode'];
		$superStockist->state_code = $params['state'];
		$superStockist->city_code = $params['city'];
		if (array_has($params, 'address'))
			$superStockist->address = $params['address'];
		if (array_has($params, 'address2'))
			$superStockist->address_2 = $params['address2'];
		$superStockist->nav_id = $navSuperStockist->getNo();
		$superStockist->company()->associate($company);
		$superStockist->author()->associate($user);
		$superStockist->save();
		$superStockist->setCustomerCard($navSuperStockist);

		$this->staService->createAddress($company, $superStockist->nav_id, $params); //#842

		return $superStockist;
	}
	public function find(int $id)
	{
		$superStockist = SuperStockist::find($id);
		if (!$superStockist) {
			throw new ModelNotFoundException;
		}
		return $superStockist;
	}
	public function getShippingAddresses(int $companyId, int $superStockistId)
	{
		$company = Company::find($companyId);
		$superStockist = $company->super_stockists()->find($superStockistId);
		return $this->staService->getAllAddresses($company, $superStockist->nav_id);
	}
	public function createShippingAddresses(int $companyId, int $superStockistId, array $params)
	{
		$company = Company::find($companyId);
		$superStockist = $company->super_stockists()->find($superStockistId);
		return $this->staService->createAddress($company, $superStockist->nav_id, $params);
	}
	public function updateShippingAddress(int $companyID, int $superStockistId, $addressCode, array $params)
	{
		$company = Company::find($companyID);
		$superStockist = $company->super_stockists()->find($superStockistId);
		return $this->staService->updateAddress($company, $superStockist->nav_id, $addressCode, $params);
	}
	public function deleteAddress(int $companyID, int $superStockistId, $addressCode)
	{
		$company = Company::find($companyID);
		$superStockist = $company->super_stockists()->find($superStockistId);
		return $this->staService->deleteAddress($company, $superStockist->nav_id, $addressCode);
	}
	public function getInventory(int $superStockistId, array $params)
	{
		$superStockist = $this->find($superStockistId);
		$category = null;
		$collection = null;
		$colour = null;
		$query = $superStockist->stocks()->where('quantity', '<>', 0);
		if (array_has(@$params['params'], 'filters') && count($params['params']['filters'])) {
			$filters = $params['params']['filters'];
			if (array_has($filters, 'category')) {
				$query->whereHas('product', function ($q) use ($filters) {
					$q->where('category_code', '=', $filters['category']);
					if (array_has($filters, 'collection')) {
						$q->where('collection_code', '=', $filters['collection']);
					}
					if (array_has($filters, 'colour')) {
						$q->where('colour_code', '=', $filters['colour']);
					}
				});
			}
			if (array_has($filters, 'quantity')) {
				preg_match('/([=<>])(\d+)/', urldecode($filters['quantity']), $matches);
				$operator = $matches[1];
				$quantity = $matches[2];

				$query->where('quantity', $operator, $quantity);
			}
		}
		return $query->paginate(@$params['params']['per_page'], ['*'], '', @$params['params']['page']);
	}
	public function adjustInventory(int $superStockistId, int $inventoryID, string $operation, float $quantity)
	{
		$superStockist = $this->find($superStockistId);
		$inventory = $superStockist->stocks()->find($inventoryID);
		if ($operation == '=') {
			$inventory->quantity = $quantity;
		}
		if ($operation == '-') {
			$inventory->quantity -= $quantity;
		}
		if ($operation == '+') {
			$inventory->quantity += $quantity;
		}
		$inventory->save();
		return $inventory;
	}
	public function addStock($companyId, $superStockistId, array $params)
	{
		$superStockist = SuperStockist::find($superStockistId);
		$query = Product::where('category_code', '=', $params['category_code'])
			->where('collection_code', '=', $params['collection_code']);

		if (array_has($params, 'colour_code')) {
			$query->where('colour_code', '=', $params['colour_code']);
		}
		$product = $query->first();
		if ($product) {
			$superStockistStock = $superStockist->stocks
				->where('product_id', '=', $product->id)
				->where('batch_no', '=', $params['batch_no'])
				->first();

			if (!$superStockistStock) {
				$stock = new Stock;
				$stock->product_id = $product->id;
				$stock->batch_no = $params['batch_no'];
				$stock->quantity = $params['stock'];
				$superStockist->stocks()->save($stock);
				return $stock;
			} else {
				$superStockistStock->quantity += $params['stock'];
				$superStockistStock->save();
				return $superStockistStock;
			}
		}
	}
	public function deleteStock($superStockistId, $stockId)
	{
		$superStockist = SuperStockist::find($superStockistId);
		$superStockistStock = $superStockist->stocks->where('id', '=', $stockId)->first();
		if ($superStockistStock)
			$superStockistStock->delete();
	}
	public function importStock($superStockist, UploadedFile $file)
	{
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
		$spreadsheet = IOFactory::load($file->getRealPath());
		if (!file_exists($file)) {
			throw new FileNotFoundException;
		}
		$worksheet = $spreadsheet->getActiveSheet();
		$maxRow = $worksheet->getHighestRow();
		$superStockistStock = $superStockist->stocks();
		if ($superStockistStock)
			$superStockistStock->delete();
		for ($i = 2; $i <= $maxRow; $i++) {
			$nav_id = $worksheet->getCell('A' . $i)->getValue();
			if(empty($nav_id)){
				continue;
			}
			$quantity = $worksheet->getCell('E' . $i)->getCalculatedValue();
			$batch_no = $worksheet->getCell('F' . $i)->getValue();
			$product = Product::where('nav_id', '=', $nav_id)->first();
			if (!$product) {
				$validated['invalid'][] = [
					'row' => $i,
					'nav_id' => $nav_id,
					'category' => $worksheet->getCell('B' . $i)->getValue(),
					'collection' => $worksheet->getCell('C' . $i)->getValue(),
					'colour' => $worksheet->getCell('D' . $i)->getValue(),
					'batch_no' => $batch_no,
					'quantity' => $quantity,
					'reason' => 'Invalid Product'
				];
				continue;
			}
			/*find existing stock if any */
			$stock = $superStockist->stocks()->where('product_id', '=', $product->id)
				->where('batch_no', '=', $batch_no)->first();

			if (!$stock) {
				if ($quantity <= 0 || $quantity == '' || $quantity == null) {
					$validated['invalid'][] = [
						'row' => $i,
						'nav_id' => $nav_id,
						'category' => $worksheet->getCell('B' . $i)->getValue(),
						'collection' => $worksheet->getCell('C' . $i)->getValue(),
						'colour' => $worksheet->getCell('D' . $i)->getValue(),
						'batch_no' => $batch_no,
						'quantity' => $quantity,
						'reason' => 'Invalid Quantity'
					];
					continue;
				} else {
					$stock = new Stock;
					$stock->product_id = $product->id;
					$stock->batch_no = $batch_no;
					if ($quantity == null)
						$stock->quantity = 0;
					else
						$stock->quantity = $quantity;
					$superStockist->stocks()->save($stock);
				}
			} else {
				$validated['invalid'][] = [
					'row' => $i,
					'nav_id' => $nav_id,
					'category' => $worksheet->getCell('B' . $i)->getValue(),
					'collection' => $worksheet->getCell('C' . $i)->getValue(),
					'colour' => $worksheet->getCell('D' . $i)->getValue(),
					'batch_no' => $batch_no,
					'quantity' => $quantity,
					'reason' => 'Stock for this batch already present'
				];
				continue;
				//$stock->quantity += $quantity;
				//$stock->save();
			}
		}
		return $validated;
	}
	public function exportStock(int $superStockistId, array $params)
	{
		$superStockist = $this->find($superStockistId);
		$category = null;
		$collection = null;
		$colour = null;
		$query = $superStockist->stocks()->where('quantity', '<>', 0);
		if (array_has($params['params'], 'filters') && count($params['params']['filters'])) {
			$filters = $params['params']['filters'];
			if (array_has($filters, 'category')) {
				$query->whereHas('product', function ($q) use ($filters) {
					$q->where('category_code', '=', $filters['category']);
					if (array_has($filters, 'collection')) {
						$q->where('collection_code', '=', $filters['collection']);
					}
					if (array_has($filters, 'colour')) {
						$q->where('colour_code', '=', $filters['colour']);
					}
				});
			}
			if (array_has($filters, 'quantity')) {
				preg_match('/([=<>])(\d+)/', urldecode($filters['quantity']), $matches);
				$operator = $matches[1];
				$quantity = $matches[2];

				$query->where('quantity', $operator, $quantity);
			}
		}
		$inventory = $query->get();

		if ($inventory) {
			$spreadsheet = new Spreadsheet();
			$activeSheet = $spreadsheet->getActiveSheet()->setTitle("My Stock");;
			$activeSheet->setCellValue('A1', 'NAV ID')->getStyle('B1')->getFont()->setBold(true);
			$activeSheet->setCellValue('B1', 'Category')->getStyle('C1')->getFont()->setBold(true);
			$activeSheet->setCellValue('C1', 'Collection')->getStyle('D1')->getFont()->setBold(true);
			$activeSheet->setCellValue('D1', 'Colour')->getStyle('E1')->getFont()->setBold(true);
			$activeSheet->setCellValue('E1', 'Batch No')->getStyle('F1')->getFont()->setBold(true);
			$activeSheet->setCellValue('F1', 'Quantity')->getStyle('G1')->getFont()->setBold(true);
			$row = 2;
			foreach ($inventory as $stock) {
				$activeSheet->setCellValue('A' . $row, $stock->product->nav_id);
				$activeSheet->setCellValue('B' . $row, $stock->product->category->code);
				$activeSheet->setCellValue('C' . $row, $stock->product->collection->code);
				if ($stock->product->colour)
					$activeSheet->setCellValue('D' . $row, $stock->product->colour->code);
				$activeSheet->setCellValue('E' . $row, $stock->batch_no);
				$activeSheet->setCellValue('F' . $row, $stock->quantity);
				$row++;
			}
			for ($col = 'A'; $col !== 'F'; $col++) {
				$activeSheet->getColumnDimension($col)->setAutoSize(true);
			}
			$writer = new Xlsx($spreadsheet);
			$file = storage_path() . '/app/SuperStockistStock_' . $superStockistId . '.xlsx';
			$writer->save($file);
			return $file;
		}
	}
	public function getSSInventory(int $superStockistId, array $params)
	{
		$superStockist = SuperStockist::find($superStockistId);
		if (array_has($params, 'dealer')) {
			$dealer = Dealer::find($params['dealer']);
			$uniqueProducts = $this->getIndividualSSInventory($superStockist, $dealer, $params);
		}

		if (array_has($params, 'cust_type') && ($params['cust_type'] == "DISTRIBUTO")) {
			$distributor = Distributor::find($params['distributor']);
			$uniqueProducts = $this->getIndividualSSInventory($superStockist, $distributor, $params);
		}

		/* Get Co SS stock */
		if ($superStockist->coSuperStockists->isNotEmpty()) {
			foreach ($superStockist->coSuperStockists as $coSuperStockist) {
				$u = $this->getIndividualSSInventory($coSuperStockist, $dealer, $params);
				foreach ($u as $key  => $stock) {
					if (array_has($uniqueProducts, $key)) {
						$uniqueProducts[$key]->quantity += $stock->quantity;
						$uniqueProducts[$key]->setBlocked($uniqueProducts[$key]->getBlocked() + $stock->getBlocked());
					} else {
						$uniqueProducts[$key] = $stock;
					}
				}
			}
		}
		//dd(collect(array_values($uniqueProducts))->where('quantity','>',1));
		return collect(array_values($uniqueProducts))->where('quantity', '>', 1);
	}
	protected function getIndividualSSInventory($superStockist, $customer, $params)
	{
		$category = null;
		$collection = null;
		$colour = null;
		$query = $superStockist->stocks();
		$superStockistId = $superStockist->id;
		if (array_has($params, 'category')) {
			$query->whereHas('product', function ($q) use ($params) {
				$q->where('category_code', '=', urldecode($params['category']));
				if (array_has($params, 'collection')) {
					$q->where('collection_code', '=', urldecode($params['collection']));
				}
				if (array_has($params, 'colour')) {
					$q->where('colour_code', '=', urldecode($params['colour']));
				}
			});
		}
		$inventories = $query->get();
		$uniqueProducts = [];

		foreach ($inventories as $inventory) {
			if (array_has($uniqueProducts, $inventory->product_id)) {
				$tempInventory = $uniqueProducts[$inventory->product_id];
				$inventory->quantity += $tempInventory->quantity;
			}
			$uniqueProducts[$inventory->product_id] = $inventory;
		}
		foreach ($uniqueProducts as $uniqueProduct) {
			$blocked_total = BlockedItem::active()->where('super_stockist_id', '=', $superStockistId)
				->where('product_id', '=', $uniqueProduct->product_id)
				->where('stock_point_id', '=', NULL)
				->sum('order_quantity');

			//$uniqueProduct->quantity -= $blocked_total;
			$uniqueProduct->setBlocked($blocked_total);
			$cart_total = $customer->cartItems->where('super_stockist_id', '=', $superStockistId)
				->where('product_id', '=', $uniqueProduct->product_id)
				->where('stock_point_id', '=', NULL)
				->sum('order_quantity');
			$uniqueProduct->quantity -= $cart_total;	//should be commented				
		}
		return $uniqueProducts;
	}
	public function getSSInventoryDetails($superStockist, $productID, array $params)
	{
		if (array_has($params, 'dealer')) {
			$dealer = Dealer::find($params['dealer']);
			$inventories = $this->getIndividualSSInventoryDetails($superStockist, $dealer, $productID, $params);
		}

		if (array_has($params, 'cust_type') && ($params['cust_type'] == "DISTRIBUTO")) {
			$distributor = Distributor::find($params['distributor']);
			$inventories = $this->getIndividualSSInventoryDetails($superStockist, $distributor, $productID, $params);
		}

		if (array_has($params, 'cust_type') && ($params['cust_type'] == "SUPER_STOCKIST" && $params['superStockist'] > 0)) {
			$SuperStockist = SuperStockist::find($params['superStockist']);
			$inventories = $this->getIndividualSSInventoryDetails($superStockist, $SuperStockist, $productID, $params);
		}

		if ($superStockist->coSuperStockists->isNotEmpty()) {
			foreach ($superStockist->coSuperStockists as $coSuperStockist) {
				$coSuperStockistInventory = $this->getIndividualSSInventoryDetails($coSuperStockist, $dealer, $productID, $params);
				$inventories = $inventories->merge($coSuperStockistInventory);
			}
		}
		//	collect($inventories)->where('quantity','>',1);
		return $inventories->where('quantity', '>', 1);
	}
	protected function getIndividualSSInventoryDetails($superStockist, $dealer, $productID, array $params)
	{
		$superStockistId = $superStockist->id;
		$inventories = $superStockist->stocks->where('product_id', '=', $productID)
			->where('quantity', '>', 1);

		foreach ($inventories as $inventory) {
			$blocked_total = BlockedItem::active()->where('super_stockist_id', '=', $superStockistId)
				->where('product_id', '=', $inventory->product_id)
				->where('batch_no', '=', $inventory->batch_no)
				->sum('order_quantity');
			$inventory->quantity -= $blocked_total;

			$cart_total = $dealer->cartItems->where('super_stockist_id', '=', $superStockistId)
				->where('product_id', '=', $inventory->product_id)
				->where('batch_no', '=', $inventory->batch_no)
				->sum('order_quantity');
			$inventory->quantity -= $cart_total;
		}
		return $inventories;
	}
	public function getMyBlockedStock(SuperStockist $superStockist, array $params)
	{
		$query = BlockedItem::active()->where('super_stockist_id', '=', $superStockist->id)->where('order_quantity', '>', 0);
		if (array_has($params, 'page')) {
			return $query->paginate($params['per_page'], ['*'], '', $params['page']);
		}
		return $query->get();
	}
	public function unBlockMyStock($itemId, $superStockistId)
	{
		BlockedItem::where('id', '=', $itemId)->where('super_stockist_id', $superStockistId)->delete();
	}

	public function getAllSSInventory(array $params)
	{
		$uniqueProducts = [];
		if (request()->user()->super_stockist_id > 0) {
			$superStockists = SuperStockist::where('id', '!=', request()->user()->super_stockist_id)->get();
		} else {
			//$superStockist = SuperStockist::find($superStockistId);
			$superStockists = SuperStockist::get();
		}

		if (!empty($superStockists)) {
			foreach ($superStockists as $superStockist) {
				if (array_has($params, 'dealer')) {
					$dealer = Dealer::find($params['dealer']);
					$uniqueProducts[$superStockist->id] = $this->getIndividualSSInventory($superStockist, $dealer, $params);
				}

				if (array_has($params, 'cust_type') && ($params['cust_type'] == "DISTRIBUTO")) {
					$distributor = Distributor::find($params['distributor']);
					$uniqueProducts[$superStockist->id] = $this->getIndividualSSInventory($superStockist, $distributor, $params);
				}

				// Get SS stock
				if (array_has($params, 'cust_type') && ($params['cust_type'] == "SUPER_STOCKIST")) {
					$distributor = SuperStockist::find($params['superStockist']);
					$uniqueProducts[$superStockist->id] = $this->getIndividualSSInventory($superStockist, $distributor, $params);
				}

				/* Get Co SS stock */
				if ($superStockist->coSuperStockists->isNotEmpty()) {
					foreach ($superStockist->coSuperStockists as $coSuperStockist) {
						$u = $this->getIndividualSSInventory($coSuperStockist, $dealer, $params);
						foreach ($u as $key  => $stock) {
							if (array_has($uniqueProducts, $key)) {
								$uniqueProducts[$key]->quantity += $stock->quantity;
								$uniqueProducts[$key]->setBlocked($uniqueProducts[$key]->getBlocked() + $stock->getBlocked());
							} else {
								$uniqueProducts[$key] = $stock;
							}
						}
					}
				}
			}

			if($uniqueProducts == null)
				return collect([]);
			$flattenedArray = [];
			array_walk_recursive($uniqueProducts, function ($value) use (&$flattenedArray) {
				$flattenedArray[] = $value;
			});

			//dd(collect(array_values($flattenedArray))->where('quantity','>',1));
			return collect(array_values($flattenedArray))->where('quantity', '>', 1);
		}
		return collect([]);
	}

	public function findForCompany(int $companyId, int $superStockistId)
	{
		$company = Company::find($companyId);
		$super_stockists = $company->super_stockists()->find($superStockistId);
		return $super_stockists;
	}

	public function update(User $user, Company $company, SuperStockist $superStockist, Array $params)
	{
		$navDistributor = $this->customerService->updateSuperStockist($company, $superStockist,$params);
		if(array_has($params, 'name'))
			$superStockist->name = $params['name'];
		if(array_has($params, 'display_name'))
			$superStockist->display_name = $params['display_name'];
		if(array_has($params, 'address'))
			$superStockist->address = $params['address'];
		if(array_has($params, 'address2'))
			$superStockist->address_2 = $params['address2'];
		$superStockist->save();
		$superStockist->setCustomerCard($navDistributor);
		return $superStockist;
	}	
}
