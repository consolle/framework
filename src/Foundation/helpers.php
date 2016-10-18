<?php

use Illuminate\Support\Str;

if ( ! function_exists('app'))
{
	/**
	 * Get the available container instance.
	 *
	 * @param  string  $make
	 * @return mixed
	 */
	function app($make = null)
	{
		if ( ! is_null($make))
		{
			return app()->make($make);
		}

		return Illuminate\Container\Container::getInstance();
	}
}

if ( ! function_exists('app_path'))
{
	/**
	 * Get the path to the application folder.
	 *
	 * @param  string  $path
	 * @return string
	 */
	function app_path($path = '')
	{
		return app('path').($path ? '/'.$path : $path);
	}
}

if ( ! function_exists('base_path'))
{
	/**
	 * Get the path to the base of the install.
	 *
	 * @param  string  $path
	 * @return string
	 */
	function base_path($path = '')
	{
		return app()->make('path.base').($path ? '/'.$path : $path);
	}
}

if ( ! function_exists('root_path'))
{
    /**
     * Get the path to the base with source or phar.
     *
     * @param  string  $path
     * @return string
     */
    function root_path($path = '')
    {
        return app('path.root') . ($path ? '/'.$path : $path);
    }
}

if ( ! function_exists('work_path'))
{
    /**
     * Get the path to the work with source or phar.
     *
     * @param  string  $path
     * @return string
     */
    function work_path($path = '')
    {
        return getcwd() . ($path ? '/'.$path : $path);
    }
}

if ( ! function_exists('bcrypt'))
{
	/**
	 * Hash the given value.
	 *
	 * @param  string  $value
	 * @param  array   $options
	 * @return string
	 */
	function bcrypt($value, $options = array())
	{
		return app('hash')->make($value, $options);
	}
}

if ( ! function_exists('config'))
{
	/**
	 * Get / set the specified configuration value.
	 *
	 * If an array is passed as the key, we will assume you want to set an array of values.
	 *
	 * @param  array|string  $key
	 * @param  mixed  $default
	 * @return mixed
	 */
	function config($key = null, $default = null)
	{
		if (is_null($key)) return app('config');

		if (is_array($key))
		{
			return app('config')->set($key);
		}

		return app('config')->get($key, $default);
	}
}

if ( ! function_exists('config_path'))
{
	/**
	 * Get the configuration path.
	 *
	 * @param  string  $path
	 * @return string
	 */
	function config_path($path = '')
	{
		return app()->make('path.config').($path ? '/'.$path : $path);
	}
}

if ( ! function_exists('storage_path'))
{
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        return app('path.storage').($path ? '/'.$path : $path);
    }
}

if ( ! function_exists('env'))
{
	/**
	 * Gets the value of an environment variable. Supports boolean, empty and null.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	function env($key, $default = null)
	{
		$value = getenv($key);

		if ($value === false) return value($default);

		switch (strtolower($value))
		{
			case 'true':
			case '(true)':
				return true;

			case 'false':
			case '(false)':
				return false;

			case 'null':
			case '(null)':
				return null;

			case 'empty':
			case '(empty)':
				return '';
		}
		
		if (Str::startsWith($value, '"') && Str::endsWith($value, '"'))
		{
			return substr($value, 1, -1);
		}

		return $value;
	}
}

if ( ! function_exists('event'))
{
	/**
	 * Fire an event and call the listeners.
	 *
	 * @param  string  $event
	 * @param  mixed   $payload
	 * @param  bool    $halt
	 * @return array|null
	 */
	function event($event, $payload = array(), $halt = false)
	{
		return app('events')->fire($event, $payload, $halt);
	}
}