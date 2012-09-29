<?php

/**
 * Array function extension for MathScript.
 * 
 * @package 
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class mathscript_array
{

    /**
     *  Determines if a given name is a member of an array group.
     */
    public static function in_array_group($self, $array_prefix, $target_name)
    {
        $length = strlen($array_prefix) + 1;
        
        //if this string doesn't start with the array prefix and '[', fail
        if(substr($target_name, 0, $length) !== $array_prefix .'[')
            return 0;

        //if this string doesn't start with ']', fail
        if(substr($target_name, -1) !== ']')
            return 0;

        //if both of the above are true, return true
        return 1;
    }

    public static function array_key_name($self, $target)
    {
        $last_opening = strrpos($target, '[');
        
        //if we couldn't find the last opening, indicate an error
        if($last_opening === false)
            return false;

        //return the array's name
        return substr($target, $last_opening + 1, -1);
    }


    public static function _pack($self, $name)
    {
        //TODO: allow recursive packing of multi-dimensional

        //create an empty packed array
        $packed = array();

        //iterate over each of the variables
        foreach($self->vars as $var => $value)
        {
            //copy the value into the packed array
            if(self::in_array_group($self, $name, $var))
                $packed[self::array_key_name($self, $var)] = $value;
        }

        //return the new packed array
        return $packed;
    }

    public static function _unpack($self, $name, $array)
    {
        if(!preg_match('#'.MATHSCRIPT_IDENTIFIER.'#', $name))
            return false;

        //TODO: allow recursive unpacking of multi-dimensional arrays
        foreach($array as $var => $value)
        {
            //if the resultant variable would be a valid value, import it into our scope
            if(preg_match('#'.MATHSCRIPT_IDENTIFIER.'#', $name.'['.$var.']'))
                $self->vars[$name.'['.$var.']'] = $value;
        }

        return true;

    }

    /**
     * Returns a count of the elements in a psuedo-array.
     */
    public function array_count($self, $array_name)
    {
        return count(self::_pack($self, $array_name));
    }



}
