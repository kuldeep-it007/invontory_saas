<?php
namespace Rosetta\Services\Notifications;

use Rosetta\Channels\Messages\SMSMessage;
interface SMSServiceInterface
{
	public function send(SMSMessage $message);
}