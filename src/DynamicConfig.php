<?php

namespace EmadHa\DynamicConfig;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DynamicConfig
 *
 * @property mixed value
 * @package EmadHa\DynamicConfig
 */
class DynamicConfig extends Model
{
    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * DynamicConfig constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('emadha.site-config.table'));
    }

    /**
     * Update the current key value
     *
     * @param $value
     *
     * @return bool
     */
    public function setTo($value)
    {
        return $this->update(['value' => $value]);
    }

    /**
     * Get the default value of the specified key
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    public function default()
    {
        return config(
            config('emadha.site-config.defaults_key') . '.' . $this->key
        );
    }

    /**
     * Revert the current key to it's original value
     * from the actual config file
     *
     * @return mixed
     */
    public function revert()
    {
        return config($this->k)->setTo(
            config(config('emadha.site-config.defaults_key') . '.' . $this->key)
        );
    }

    /**
     * Get the raw value (for use in model context).
     *
     * @return mixed|string
     */
    public function __toValue()
    {
        return $this->value;
    }

    /**
     * Get config value whether it is a DynamicConfig instance (scalar from DB) or array/other.
     * Use this when the key may be either a scalar (returns model) or an array.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function value($key, $default = null)
    {
        $v = config($key, $default);
        if ($v instanceof self) {
            return $v->__toValue();
        }
        return $v;
    }

}