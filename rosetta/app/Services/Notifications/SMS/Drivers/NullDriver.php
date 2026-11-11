<?php
namespace Rosetta\Services\Notifications\SMS\Drivers;



class NullDriver implements SMSDriverInterface
{
    /**
     *
     * @var \Rosetta\Classes\Settings
     */
    protected $settings;
    public function init($settings)
    {
        $this->settings = $settings;
    }
    public function send($to, $text)
    {
        
    }
}