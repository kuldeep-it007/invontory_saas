<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\ItemCard\Picture\ItemPicture;
use Rosetta\NAV\WS\ItemCard\Picture\SetItemPicture;
use Rosetta\Services\NAV\ItemPictureServiceInterface;
use Rosetta\Traits\Nav;
class ItemPictureService implements ItemPictureServiceInterface
{
	use Nav;
	const LOCATION = 'Codeunit/ItemPicture';
	const WSDL = 'ItemPicture';
	public function setImage(Company $company,$navId, $imageData)
	{
		$pictureService = new ItemPicture(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$setItemPicture = new SetItemPicture($navId,$imageData);
		$pictureService->SetItemPicture($setItemPicture);
	}
}