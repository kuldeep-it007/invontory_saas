<?php
namespace Rosetta\Exceptions\Order;
use Exception;
class BlockItemDeniedException extends Exception
{
	public $cool_down_end_time;
}