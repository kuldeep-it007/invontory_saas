<?php
namespace Rosetta\Services\CLRate;

interface CLRateServiceInterface
{
	public function getRate($model,$product,$related_type = null,$related_id = null);
}