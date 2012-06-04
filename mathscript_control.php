<?php

class mathscript_control
{
    /**
     * Evaluates a block of mathscript stored in a variable. 
     * Since it executes MathScript, it's perfectly safe for the operating environment.
     */
    public static function _eval($self, $code)
    {
        return $self->evaluate_script($code, true);
    } 

    /**
     * Evalutes a block of code as though it were a function.
     */
    public static function _function($self, $code)
    {
        return $self->evaluate_script($code, false, true);
    }

    public static function _return($self, $retval)
    {   
        //return, breaking out of the active loop or script
        $self->_return($retval);
    }

    public static function _break($self)
    {
        //break out of the active loop, or halt the script
        $self->_break();
    }   

    /**
     * A basic function which acts as an 'if' statement.
     */
    public static function _if($self, $condition, $truecode, $falsecode = null)
    {
        //if the given condition evaluates to true, run the true script
        if($self->evaluate($condition))
            $self->evaluate_script($truecode);

        //otherwise, run the false one
        elseif($falsecode !== null)
            $self->evaluate_script($falsecode);
    } 


    /**
     * A function which acts as a while loop.
     * 
     */
    public static function _while($self, $condition, $code)
    {
        //for as long as the condition evaluates to true
        while($self->evaluate($condition))
            $self->evaluate_script($code);
    }

    /**
     * A function which acts as a for loop.
     */
    public static function _for($self, $before, $condition, $after, $code)
    {
        //unconditionally evaluate the before code
        $self->evaluate_script($before); 

        //for as long as the condition is true
        while($self->evaluate($condition))
        {
            //evaluate the code
            $self->evaluate_script($code);

            //and the after condition
            $self->evaluate($after);
        }
    }

}
