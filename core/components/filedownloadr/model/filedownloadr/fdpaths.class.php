<?php
/**
 * fdPaths
 *
 * @package filedownloadr
 * @subpackage classfile
 */

class fdPaths extends xPDOSimpleObject
{
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getExtendedField(string $key, $default = null)
    {
        $extended = $this->get('extended');
        $extended = !empty($extended) ? $extended : [];
        return (array_key_exists($key, $extended)) ? $extended[$key] : $default;
    }

    /**
     * @return array
     */
    public function getExtendedFields()
    {
        $extended = $this->get('extended');
        return !empty($extended) ? $extended : [];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setExtendedField(string $key, $value)
    {
        $extended = $this->get('extended');
        $extended = !empty($extended) ? $extended : [];
        if (!is_null($value) && $value != '') {
            $extended[$key] = $value;
        } else {
            unset($extended[$key]);
        }
        if (empty($extended)) {
            return $this->set('extended');
        } else {
            return $this->set('extended', $extended);
        }
    }

    /**
     * @param array $newExtended
     * @param bool $merge
     * @return bool
     */
    public function setExtendedFields(array $newExtended, $merge = true)
    {
        $extended = $this->get('extended');
        $extended = !empty($extended) ? $extended : [];
        $extended = ($merge) ? array_merge($extended, $newExtended) : $newExtended;
        return $this->set('extended', $extended);
    }

    /**
     * @param array $fields
     * @param string $prefix
     * @return array
     */
    public static function extendedFieldNames(array $fields, $prefix = '')
    {
        $result = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (isset($field['name'])) {
                    $result[] = $prefix . $field['name'];
                } elseif (isset($field['fields']) && is_array($field['fields'])) {
                    $result = array_merge($result, self::extendedFieldNames($field['fields'], $prefix));
                }
            }
        }
        return $result;
    }
}
