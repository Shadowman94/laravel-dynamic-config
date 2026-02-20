<?php

namespace EmadHa\DynamicConfig;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use mysql_xdevapi\Exception;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap services.
     *
     * @return void
     * @throws \Exception
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            if (!class_exists('CreateSiteConfigTable')) {
                $timestamp = date('Y_m_d_His', time());
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_site_config_table.php.stub' => database_path('migrations/' . $timestamp . '_create_site_config_table.php'),
                ], 'migrations');
            }

            $this->publishes([
                __DIR__ . '/../config/site-config.php' => config_path('emadha/site-config.php'),
            ], 'config');
        }

        $this->initConfig();
    }

    private function initConfig()
    {

        # Check if the table exists
        if (!Schema::hasTable(config('emadha.site-config.table'))) {

            # Don't crash, Log the error instead
            Log::error(sprintf(
                    get_class($this) . " is missing the the dynamic config table [`%s`]. you might need to do `php artisan vendor:publish` && `php artisan migrate`",
                    config('emadha.site-config.table'))
            );

            return false;
        }

        # Create a new collection of what's dynamic
        $DefaultConfig = collect([]);

        # Return the config entries containing ['dynamic'=>true] key
        collect(config()->all())->each(function ($value, $key) use (&$DefaultConfig) {

            #Check if value is array type.
            if(is_array($value))
            {
                # Check if the current config key has dynamic key set to it, and it's true
                if (array_key_exists(config('emadha.site-config.dynamic_key'), $value)
                    && $value[config('emadha.site-config.dynamic_key')] == true) {

                    # unset that dynamic value
                    unset($value[config('emadha.site-config.dynamic_key')]);

                    # Add that to the DynamicConfig collection
                    $DefaultConfig->put($key, $value);
                }
            }
        });

        # Keep the defaults for reference
        config([config('emadha.site-config.defaults_key') => $DefaultConfig]);

        # Flatten the config table data
        $prefixedKeys = $this->prefixKey(null, $DefaultConfig->all());

        # Insert the flattened data into database
        foreach ($prefixedKeys as $_key => $_value) {

            $_type = gettype($_value);

            if(is_bool($_value))
            {
                $_value = $_value ? "true" : "false";
            }

            # Get the row from database if it exists,
            # If not, add it using the value from the actual config file.
            DynamicConfig::firstOrCreate(['key' => $_key], ['value' => $_value, 'type' => $_type]);
        }

        # Build the Config array
        $DynamicConfig = DynamicConfig::all();

        # Check if auto deleting orphan keys is enabled
        # and delete those if they don't exists in the actual config file
        if (config('emadha.site-config.auto_delete_orphan_keys') == true) {

            $toCompare = [];

            foreach($DynamicConfig->map->only(['key', 'value', 'type']) as $config)
            {
                $key = $config['key'];
                $value = $config['value'];
                $type = $config['type'];

                if(strcmp($type, "boolean") == 0)
                {
                    if(strcmp($value, "false") == 0)
                        $value = false;
            
                    if(strcmp($value, "true") == 0)
                        $value = true;

                    $toCompare[$key] = $value;
                }
                else
                {
                    settype($value, $type);
                    $toCompare[$key] = $value;
                }                
            }

            # Check for orphan keys
            $orphanKeys = array_diff_assoc($toCompare, $prefixedKeys);

            # Delete orphan keys
            DynamicConfig::whereIn('key', array_keys($orphanKeys))->delete();
        }

        # Store these config into the config() helper, but as model objects
        # Thus making Model's method accessible from here
        # example: config('app.name')->revert().
        # Available methods are `revert`, `default` and `setTo($value)`
        $DynamicConfig->map(function ($config) use ($DefaultConfig) {

            $key = $config->key;
            $value = $config->value;
            $type = $config->type;

            if(strcmp($type, "boolean") == 0)
            {
                if(strcmp($value, "false") == 0)
                    $value = false;
        
                if(strcmp($value, "true") == 0)
                    $value = true;
            }
            else
            {
                settype($value, $type);
            }

            $config->value = $value;

            $pieces = explode(".", $key);
            $cnt = count($pieces);

            if(is_numeric($pieces[$cnt - 1]))
            {
                # Array element: set the casted value so config('parent.key') builds the array from DB
                config([$key => $value]);
            }
            else
            {
                config([$key => $config]);
            }
        });
    }

    public function prefixKey($prefix, $array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::prefixKey($prefix . $key . '.', $value));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
