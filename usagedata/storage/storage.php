<?php

defined('USAGEDATA_INCLUDED') or die();

class UsagedataStorage
{
    /** @var string Full path to the storage file */
    protected $path = null;

    /** @var \JRegistry Storage object. In the future we will have to use a standalone version of JRegistry  */
    protected $storage = null;

    public function __construct($path)
    {
        $this->path = $path;

        $this->storage = new JRegistry();
        $this->load();
    }

    public function load()
    {
        $this->storage->loadString(file_get_contents($this->path));
    }

    public function get($path, $default = null)
    {
        return $this->storage->get($path, $default);
    }

    public function set($path, $value)
    {
        return $this->storage->set($path, $value);
    }

    public function save()
    {
        $json_format_options = null;

        if (defined('JSON_PRETTY_PRINT'))
        {
            $json_format_options = JSON_PRETTY_PRINT;
        }

        $string = json_encode($this->storage->toString(), $json_format_options);

        return file_put_contents($this->path, $string);
    }
}