<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Stdlib
 */

namespace Zend\Stdlib\Hydrator;

use Zend\Stdlib\Exception;

/**
 * @category   Zend
 * @package    Zend_Stdlib
 * @subpackage Hydrator
 */
class ClassMethods extends AbstractHydrator implements HydratorOptionsInterface
{
    /**
     * Flag defining whether array keys are underscore-separated (true) or camel case (false)
     * @var boolean
     */
    protected $underscoreSeparatedKeys = true;

    /**
     * Define if extract values will use camel case or name with underscore
     * @param boolean|array $underscoreSeparatedKeys
     */
    public function __construct($underscoreSeparatedKeys = true)
    {
        parent::__construct();
        $this->setUnderscoreSeparatedKeys($underscoreSeparatedKeys);
    }

    /**
     * @param  array|\Traversable $options
     * @return ClassMethods
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (!is_array($options)) {
            throw new Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }
        if (isset($options['underscoreSeparatedKeys'])) {
            $this->setUnderscoreSeparatedKeys($options['underscoreSeparatedKeys']);
        }

        return $this;
    }

    /**
     * @param  boolean $underscoreSeparatedKeys
     * @return ClassMethods
     */
    public function setUnderscoreSeparatedKeys($underscoreSeparatedKeys)
    {
        $this->underscoreSeparatedKeys = $underscoreSeparatedKeys;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUnderscoreSeparatedKeys()
    {
        return $this->underscoreSeparatedKeys;
    }

    /**
     * Extract values from an object with class methods
     *
     * Extracts the getter/setter of the given $object.
     *
     * @param  object $object
     * @return array
     * @throws Exception\BadMethodCallException for a non-object $object
     */
    public function extract($object)
    {
        if (!is_object($object)) {
            throw new Exception\BadMethodCallException(sprintf(
                '%s expects the provided $object to be a PHP object)', __METHOD__
            ));
        }

        $transform = function ($letters) {
            $letter = array_shift($letters);
            return '_' . strtolower($letter);
        };
        $attributes = array();
        $methods = get_class_methods($object);

        foreach ($methods as $method) {
            if (!preg_match('/^(get|has|is)[A-Z]\w*/', $method)) {
                continue;
            }

            $attribute = $method;
            if (preg_match('/^get/', $method)) {
                $attribute = substr($method, 3);
                $attribute = lcfirst($attribute);
            }

            if ($this->underscoreSeparatedKeys) {
                $attribute = preg_replace_callback('/([A-Z])/', $transform, $attribute);
            }
            $attributes[$attribute] = $this->extractValue($attribute, $object->$method());
        }

        return $attributes;
    }

    /**
     * Hydrate an object by populating getter/setter methods
     *
     * Hydrates an object by getter/setter methods of the object.
     *
     * @param  array $data
     * @param  object $object
     * @return object
     * @throws Exception\BadMethodCallException for a non-object $object
     */
    public function hydrate(array $data, $object)
    {
        if (!is_object($object)) {
            throw new Exception\BadMethodCallException(sprintf(
                '%s expects the provided $object to be a PHP object)', __METHOD__
            ));
        }

        $transform = function ($letters) {
            $letter = substr(array_shift($letters), 1, 1);
            return ucfirst($letter);
        };

        foreach ($data as $property => $value) {
            $method = 'set' . ucfirst($property);
            if ($this->underscoreSeparatedKeys) {
                $method = preg_replace_callback('/(_[a-z])/', $transform, $method);
            }
            if (method_exists($object, $method)) {
                $value = $this->hydrateValue($property, $value);

                $object->$method($value);
            }
        }
        return $object;
    }
}
