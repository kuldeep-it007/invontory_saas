<?php
namespace Rosetta\Services\NAV;

use Rosetta\Services\NAV\PurchaseLineServiceInterface;
use Rosetta\Traits\Nav;
class PurchaseLineService implements PurchaseLineServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Purchase_Line';
	const WSDL = 'Purchase_Line';
}