<?php
namespace Rosetta\Services\Inventory;
interface InventoryServiceInterface
{
    public function calculateStock(Array $itemCards,$customer,$requestedQuantity, $companyId, $min_price, $max_price,Array $superStockistStock = [],Array $disrtibutorstStock = []);
}