<?php
/**
 * Digital Logic functions for mathscript.
 * 
 * @package 
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */

class mathscript_logic
{
    public static function random_truth_table($self, $var, $var_count = 4)
    {
        if(!is_string($var))
            return false;

        //create an (empty) array of truth table rows; and compute how many rows we'll need
        $minterms = array();
        $minterm_count = 1 << $var_count;

        //for each truth table row, select either "0" or "1" as the output
        for($i = 0; $i < $minterm_count; ++$i)
            $minterms[$i] = mt_rand(0, 1);
    
        mathscript_array::_unpack($self, $var, $minterms); 

        return true;
    }

    /**
     *
     */
    public static function sum_of_minterms($self, $truth_table_pseudoarray)
    {
        //get the number of elements in the TT psuedoarray, and the number of varaibles it was based off of
        $count = mathscript_array::array_count($self, $truth_table_pseudoarray);
        $var_count = log($count, 2);

        //start an array of minterm expressions
        $minterms = array();

        //for each "minterm" in the TT
        for($i = 0; $i < $count; ++$i)
        {
            //if the output for the given TT row is '1', include its minterm
            if($self->vars[$truth_table_pseudoarray.'['.$i.']'] == 1)
                $minterms[] = self::minterm($self,  $i, $var_count);
        }

        //return the sum of the minterms 
        return implode(' + ', $minterms);

    }

    /**
     * Returns the minterm with the given binary weight using the letters a, b, c, d...
     */
    public static function minterm($self, $number, $var_count = 4)
    { 
        $string = '';

        //for each input to the expression
        for($i = $var_count - 1; $i >= 0; --$i)
        {

            //get the bit with the given place value in the relevant number;
            $bit = $number / (1 << $i) % 2;

            //and get the variable that corresponds to the relevant number
            $letter = chr(($var_count - $i - 1) + ord('a'));

            //add the letter to our binary string
            $string .= $letter;
            
            //and, if the bit should be zero, invert the letter
            if($bit == 0)
                $string .= '\'';
        }

        return $string;
    }


}
