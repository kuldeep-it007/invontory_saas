<?php
namespace Rosetta\Traits;

use Rosetta\Models\Setting;
use Rosetta\Classes\Settings;


Trait SettingsTrait
{
    public function settings()
    {
        return $this->morphMany(Setting::class,'settings');
    }

    /**
     * Get settings for specific type
     *
     * @param String $type
     * @return \Rosetta\Classes\Settings
     */
    public function getSettingsForType(String $type)
    {
        return new Settings(
            $this->settings()
                ->where('type', '=' ,$type)
                ->get(['key','value'])
                ->map(function($rec){
                    $value = $rec->value;
                    if(strtolower($value) == 'true')
                        return [$rec->key => true];
                    if(strtolower($value) == 'false')
                        return [$rec->key => false];
                    if(preg_match('/^0/',$value))
                        return [$rec->key => $value];
                    if(is_numeric($value))
                        return [$rec->key => $value + 0];
                    
                    return [$rec->key => $value];
                })
                ->collapse()
                ->toArray()
        );
    }

    /**
     * Get settings for multiple type
     *
     * @param String $type
     * @return \Rosetta\Classes\Settings
     */
    public function getSettingsForTypes(Array $types)
    {
        return new Settings(
            $this->settings()
                ->whereIn('type', $types)
                ->get(['key','value'])
                ->map(function($rec){
                    $value = $rec->value;
                    if(strtolower($value) == 'true')
                        return [$rec->key => true];
                    if(strtolower($value) == 'false')
                        return [$rec->key => false];
                    if(preg_match('/^0/',$value))
                        return [$rec->key => $value];
                    if(is_numeric($value))
                        return [$rec->key => $value + 0];
                    
                    return [$rec->key => $value];
                })
                ->collapse()
                ->toArray()
        );
    }
}