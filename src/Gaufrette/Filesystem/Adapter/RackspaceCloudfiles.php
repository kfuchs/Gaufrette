<?php

namespace Gaufrette\Filesystem\Adapter;

use Gaufrette\Filesystem\Adapter;
use CF_Container;

/**
 * Rackspace cloudfiles adapter
 *
 * @package Gaufrette
 * @author  Antoine Hérault <antoine.herault@gmail.com>
 */
class RackspaceCloudfiles implements Adapter
{
    protected $container;

    /**
     * Contructor
     *
     * @param  CF_Container $container A CF_Container instance
     */
    public function __construct(CF_Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function read($key)
    {
        $object = $this->container->get_object($key);

        return $object->read();
    }

    /**
     * {@inheritDoc}
     */
    public function write($key, $content)
    {
        $object = $this->tryGetObject($key);
        if (false === $object) {
            // the object does not exist, so we create it
            $object = $this->container->create_object($key);
        }

        if ($object->write($content)) {
            return $this->getStringNumBytes($content);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function exists($key)
    {
        return false === $this->tryGetObject($key);
    }

    /**
     * {@inheritDoc}
     */
    public function keys($pattern)
    {
        return $this->container->list_objects(0, null, null, $pattern);
    }

    /**
     * {@inheritDoc}
     */
    public function mtime($key)
    {
        // as far as I know, there is no such information available through the
        // API provided by rackspace
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function checksum($key)
    {
        $object = $this->container->get_object($key);

        return $object->getETag();
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
        try {
            return $this->container->delete_object($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Tries to get the object for the specified key or return false
     *
     * @param  string $key The key of the object
     *
     * @return CF_Object or FALSE if the object does not exist
     */
    protected function tryGetObject($key)
    {
        try {
            return $this->container->getObject($key);
        } catch (\NoSuchObjectException $e) {
            // the NoSuchObjectException is thrown by the CF_Object during it's
            // creation if the object doesn't exist
            return false;
        }
    }

    /**
     * Returns the number of bytes of the given string
     *
     * @param  string $string
     *
     * @return integer
     */
    protected function getStringNumBytes($string)
    {
        $d = 0;
        $strlen_var = strlen($string);
        for ($c = 0; $c < $strlen_var; ++$c) {

            $ord_var_c = ord($string{$d});

            switch (true) {
                case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
                    $d++;
                    break;
                case (($ord_var_c & 0xE0) == 0xC0):
                    $d+=2;
                    break;
                case (($ord_var_c & 0xF0) == 0xE0):
                    $d+=3;
                    break;
                case (($ord_var_c & 0xF8) == 0xF0):
                    $d+=4;
                    break;
                case (($ord_var_c & 0xFC) == 0xF8):
                    $d+=5;
                    break;
                case (($ord_var_c & 0xFE) == 0xFC):
                    $d+=6;
                    break;
                default:
                    $d++;
            }
        }

        return $d;
    }
}
