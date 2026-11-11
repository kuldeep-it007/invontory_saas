<?php
namespace Rosetta\Services\Notifications\SMS\Drivers;

use Rosetta\Classes\Settings;

interface SMSDriverInterface
{
    public function send($to, $text);
    public function init(Settings $settings);
}