<?php
namespace Rosetta\Services\NAV;

use Rosetta\Services\NAV\POServiceInterface;
use Rosetta\Traits\Nav;
class POService implements POServiceInterface
{
	use Nav;
	const LOCATION = 'Page/PO';
	const WSDL = 'PO';
}