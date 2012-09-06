<?php

/*
================================================================================

MathScript Interpreter

by Kyle Temkin <ktemkin@binghamton.edu>
Copyright (C) 2011 Binghamton University <http://www.binghamton.edu>

based on: 

EvalMath - PHP Class to safely evaluate math expressions
Copyright (C) 2005 Miles Kaufmann <http://www.twmagic.com/>

with some modifications by skodak

================================================================================
*/

//Core constructs for MathScript
define('MATHSCRIPT_IDENTIFIER', '[A-Za-z][A-Za-z0-9_\[\]\.\:]*');

//define('MATHSCRIPT_STRING_ASSIGNMENT', '/^\s*('.MATHSCRIPT_IDENTIFIER.')\s*=\s*"([^"]+)"$/');
define('MATHSCRIPT_VAR_ASSIGNMENT', '/^\s*('.MATHSCRIPT_IDENTIFIER.')\s*=\s*([^=].+)$/');
define('MATHSCRIPT_VAR_OPERATION_ASSIGNMENT', '/^\s*('.MATHSCRIPT_IDENTIFIER.')\s*(\+|\-|\*|\/|\||&|%)=\s*([^=].+)$/');
define('MATHSCRIPT_FUNCTION_DEF', '/^\s*('.MATHSCRIPT_IDENTIFIER.')\s*\(\s*('.MATHSCRIPT_IDENTIFIER.'(?:\s*,\s*'.MATHSCRIPT_IDENTIFIER.')*)\s*\)\s*=\s*(.+)$/');
define('MATHSCRIPT_LEGAL_CHARS', '/[^\w\s+*^\/()\.,-"]/');
define('MATHSCRIPT_FUNCTION_CLOSE', '/^('.MATHSCRIPT_IDENTIFIER.')\($/');

//TODO: switch " for '
define('MATHSCRIPT_QUOTED_STRING', '/"((?:[^"]|\\\\.)*)"/');

//array indicies (unchanged for backwards compatibility)
define('MATHSCRIPT_FUNCTION_NAME',  'fnn');
define('MATHSCRIPT_ARGUMENT_COUNT', 'argcount');
define('MATHSCRIPT_FUNCTION_TOKEN', 'fn'); 
define('MATHSCRIPT_ARGUMENTS', 'args');
define('MATHSCRIPT_FUNCTION_BODY', 'func');

//"pass by name" for literals
define('MATHSCRIPT_TYPE_LITERAL', null);
define('MATHSCRIPT_TYPE_RESULT', null);

//DEBUG
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

/*
class EvalMath extends MathScript
{ }
*/

class MathScript
{

    static $operators =  array
        (
            //basic arithmetic
            '+' => array ('right-associative' => false, 'precedence' => 1, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'add')),
            '-' => array ('right-associative' => false, 'precedence' => 1, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'subtract')),
            '*' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'multiply')),
            '/' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'divide')),
            '^' => array ('right-associative' => true,  'precedence' => 3, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'power')),
            '_' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 1,  'by_reference' => false, 'handler' => array('mathscript_operators', 'inverse')),
            '%' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'modulus')),
            '++' => array ('right-associative' => false, 'precedence' => 5, 'arity' => 1, 'by_reference' => true,  'handler' => array('mathscript_operators', 'increment')),
            '--' => array ('right-associative' => false, 'precedence' => 5, 'arity' => 1, 'by_reference' => true,  'handler' => array('mathscript_operators', 'decrement')),
           
            //boolean and bitwise logic
            '&' => array ('right-associative' => false, 'precedence' => 4, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'bitwise_and')),
            '|' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'bitwise_or')),
            '!' => array ('right-associative' => true , 'precedence' => 2, 'arity' => 1,  'by_reference' => false, 'handler' => array('mathscript_operators', '_not')),
            '~' => array ('right-associative' => true , 'precedence' => 4, 'arity' => 1,  'by_reference' => false, 'handler' => array('mathscript_operators', 'bitwise_not')),
            '^^' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', 'bitwise_xor')),
            '&&' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', '_and')),
            '||' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', '_or')),

            //comparison operators
            '>' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'greater_than')),
            '<' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2,  'by_reference' => false, 'handler' => array('mathscript_operators', 'less_than')),
            '==' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', 'equals')),
            '!=' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', 'not_equals')),
            '>=' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', 'greater_equal')),
            '<=' => array ('right-associative' => false, 'precedence' => 0, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', 'less_equal')),

            //string concatination
            ':' => array ('right-associative' => false, 'precedence' => 2, 'arity' => 2, 'by_reference' => false, 'handler' => array('mathscript_operators', 'concat')),
        );


    /**
     * The extension manager for this mathscript instance; 
     */
    private $extensions;


    /**
     * If true, errors will not be passed to the PHP error handler. 
     * @var bool
     */
    public $suppress_errors = false;

    /**
     * If true, errors will be printed directly to the PHP output, no matter the value of surpress errors.
     */
    public $debug_show_errors = false;

    /**
     * Contains a string description of the most recently encountered error, or null if no error has been encountered.
     */
    public $last_error = null;


    /**
     * User variables defined at runtime.
     *
     * @var array
     */
    public $vars = array(); // variables (and constants)

    /**
     * If the current statement is being used as an assignment, this is the variable that will be assigned to.
     */
    public $assignment_target = null;

    /**
     * User functions defined at runtime.
     * 
     * @var array
     */
    private $user_functions = array(); // user-defined functions


    /**
     * The last return value of an executed function.
     */
    private $last_return_value = null;

    /**
     * Returns true iff a script is currently executing. If set to false, any executing script will halt. 
     */
    private $executing = false;

    /**
     * Creates a new MathScript mathscript evaluator.
     * 
     * @param string $modules   A array of extension modules to load. "spreadsheet" and "basicmath" are built in; others must be included.
     */
    public function __construct($modules = array('spreadsheet', 'basicmath')) 
    {
        //create a new extension manager
        $this->extensions = new mathscript_extension_manager($this, $modules);     
    }
     

    /**
     * Convenience shortcut method for evaluate.
     * 
     * @deprecated Use the long-hand "evaluate" instead, as it's more readable. 
     *
     * @param mixed $expr   The expression to be evaluated.
     * @return              The result of the single command executed.
     */
    function e($expr) 
    {
        //call the core evaluation method
        return $this->evaluate($expr);
    }

    /**
     * Safely evalutes a single expression of user code.
     * Used internally by the mathscript engine, as well as to determine the result of a variable, or expression.
     * 
     * @param mixed $expr   The expression to be evaluated.
     * @return              The result of the single command executed.
     */
    function evaluate($expr, $local_context = false, $function_context = false) 
    {
        //clear the record of the last error
        $this->last_error = null;

        //clear the current assignment record
        $this->assignment_target = null;

        //store the initial execution context
        $initial_vars = $this->vars;

        //assume an initial return value of false
        $retval = false;

        //and strip off any leading/trailing whitespace
        $expr = trim($expr);

        //remove any semicolons from the end of the expression
        if (substr($expr, -1, 1) == ';') 
            $expr = substr($expr, 0, strlen($expr)-1); 

        //perform common preprocessing tasks on the expression
        //(this resolves TCL-style variables, and allows TCL-style function calls)
        $expr = $this->preprocess($expr);

        //if the given expression is a variable assignment:
        if (preg_match(MATHSCRIPT_VAR_ASSIGNMENT, $expr, $matches)) 
        {
            //set the assignment target
            $this->assignment_target = $matches[1];

            //parse the data to be assigned
            $to_assign = $this->evaluate_infix($matches[2]);

            //if an error occurred during processing of the RHS (data to be assigned), return false
            if ($to_assign === false) 
                return false; 

            //otherwise, set the relevant variable
            $this->vars[$this->assignment_target] = $to_assign;

            //and return the value that resulted from the assignment
            $retval = $to_assign; // and return the resulting value
        }

        elseif(preg_match(MATHSCRIPT_VAR_OPERATION_ASSIGNMENT, $expr, $matches))
        {
            //get the initial value of the left-hand-side of the expression
            $lhs = $this->dereference($matches[1]);

            //if it does not exist, fail
            if($lhs === null)
                return false;

            //compute the RHS of the expression, which will be added to the LHS
            $rhs = $this->evaluate_infix($matches[3]);

            //if we couldn't compute the RHS, fail
            if($rhs === false)
                return false;

            //call the correct opreator for this block
            $handler = self::$operators[$matches[2]]['handler'];
            $result = call_user_func($handler, $this, $lhs, $rhs);

            //and store the result
            $this->vars[$matches[1]] = $result;

            //and return the value that resulted from the assignment
            $retval = $result;
        }

        //handle function definitions 
        elseif (preg_match(MATHSCRIPT_FUNCTION_DEF, $expr, $matches))
        {

            //extract the function name
            $function_name = $matches[1];

            //ensure we're not able to override a built-in function (user defined functions can be overridden)
            if ($this->extensions->function_exists($function_name))
                return $this->trigger('cannot redefine built-in function "'.$matches[1]().'"');

            //parse the argument list for the function
            $args = explode(",", preg_replace("/\s+/", "", $matches[2])); // get the arguments

            //convert the infix expression for the function to postfix
            $stack = $this->infix_to_postfix($matches[3]);

            //if we failed to parse the infix expression, return false
            if ($stack === false) 
                    return false;

            //finally, store the function definition
            $this->user_functions[$function_name] = array(MATHSCRIPT_ARGUMENTS => $args, MATHSCRIPT_FUNCTION_BODY=> $stack);

            //and return, indicating success
            $retval = true;
        
        } 
        //if the line wasn't any of our special cases above, attempt to evaluate it directly
        else
        {
            //if the code was empty (or an straight zero), return 0 (this is typical of comments)
            if(empty($expr))
                return 0;

            //evaluate the given function, and get the return value
            $retval = $this->evaluate_infix($expr); 

        }

        //if we're inside of a function context, don't allow changes to the global scope
        if($function_context)
        {
            $this->vars = $initial_vars;
        }
        //if this evaluation is occurring in a local context
        elseif($local_context)
        {
            //delete any variables that were not present in the initial list of variables
            //this establishes a "local context"
            foreach($this->vars as $name => $value)
                if(!array_key_exists($name, $initial_vars))
                    unset($this->vars[$name]);
        }

        //return the previously-set return value
        return $retval;

    }

  

    //~ktemkin
    public function evaluate_script($math_script, $local_context = false, $function_context = false)
    {
        //and start a new array, which will store any errors which occur during script execution
        $errors = array();

        //split the script into "lines" by semicolon
        $script_lines = self::split_into_statements($math_script); //explode(';', $math_script);

        //indicate that we are currently executing
        $this->executing = true;

        //retrieve an initial copy of the variables
        $initial_vars = $this->vars;

        //for each line in the script
        foreach($script_lines as $line)
        {
            //if our execution flag has been set to false, stop executing
            if(!$this->executing)
                break;

            //remove all leading/trailing whitespace
            $line = trim($line);

            //if the line has no text, skip it 
            if(empty($line))
                continue;
            
            //strip newlines, tabs and leading/trailing spces, as they're meaningless
            #$line = trim(str_replace(array("\n", "\t"), ' ', $line));
            
            //evaluate the given line
            $this->evaluate($line);
            
            //if an error occurred, add it to our errors array
            if(!empty($this->last_error))
                $errors[] = preg_replace('|on line <b>[0-9]+</b>|', '', $this->last_error).' (<em>'.$line.'</em>)';
        }

        //if we're inside of a function context, don't allow changes to the global scope
        if($function_context)
        {
            //reset the variables to their initial state
            $this->vars = $initial_vars;

            //and return the most recent return value
            return $this->last_return_value;
        }
        //if this evaluation is occurring in a local context
        elseif($local_context)
        {
            //delete any variables that were not present in the initial list of variables
            //this establishes a "local context"
            foreach($this->vars as $name => $value)
                if(!array_key_exists($name, $initial_vars))
                    unset($this->vars[$name]);
        }

        //return the list of errors which occured; on success, it will be empty
        return $errors;
    }

    /**
     * Pre-processes a given expression before execution,
     * performing TCL-like variable substitution. TCL-style function calls
     * are converted to standard MathScript functions. 
     */
    private function preprocess($expr, $allow_tcl_calls = true)
    {
        $prefix = '';
        $suffix = '';

        //determine if the given statement is a TCL-like function call
        $tcl_call = preg_match('#^(\$?'.MATHSCRIPT_IDENTIFIER.')\s+(.+)*$#ss', $expr, $matches) && $allow_tcl_calls;

        //if the expression is a TCL-style function call, process it
        if($tcl_call)
        {
            //extract the function's name
            $function_name = $matches[1];

            //if the function name starts with a dollar sign, it's a TCL-style variable
            if(substr($function_name, 0, 1) == '$')
            {
                //the TCL-style variable name is equivalent to the function's "name" without the leading $
                $var_name = substr($function_name, 1);

                //if the variable exists, set the function name to its value
                if(array_key_exists($var_name, $this->vars))
                    $function_name = $this->vars[$var_name];
            }

            //if this represents a valid function, handle it
            if($this->extensions->function_exists($function_name))
            {
                //extract the function name, and set the pre/suffix to parenthesis
                $prefix = $function_name.'(';
                $suffix = ')';

                //convert the expression to a standard MathScript function
                $expr =  trim($matches[2]);
            }
            //otherwise, it's not a real TCL call
            else
            {
                $tcl_call = false;
            }

        }

        //initialize our flags: we'll start off outside of a quote/curly string, and _not_ at the start of a block of whitespace
        $quote = false;
        $curly_depth = 0;
        $paren_depth = 0;
        $last_was_space = false;

        //start a "buffer" for the new expression
        $new_expr = '';

        //for each charcter in the input string
        $expr_length = strlen($expr);
        for($i = 0; $i < $expr_length; ++$i)
        {
            //extract a single character from the string
            $char = substr($expr, $i, 1);

            //if we have the escape character
            if($char == '\\')
            {
                //push it, and the next character, directly onto the string 
                $new_expr .= substr($expr, i, 2);

                //and skip the next character
                ++$i;
                continue;
            }

            //if we've hit a quote, toggle quotes
            if($char == '"')
                $quote = !$quote;

            //if we've hit an open-curly, increase the curly-depth
            if($char == '{')
                ++$curly_depth;

            //if we've hit a close-curly, decrease the curly-depth
            if($char == '}')
                --$curly_depth;

            //if we've hit an open-paren, increase the paren-depth
            if($char == '(')
                ++$paren_depth;

            //if we've hit a close-paren, increase the paren-depth
            if($char == ')')
                --$paren_depth;

            //if we are not inside of a " or { string, preprocess
            if($curly_depth == 0)
            {
                //get the rest of the expression, starting at $i
                $rest_of_expr = substr($expr, $i);

                //if we've run into a TCL-style variable, in ${identifier} form, require the entire contents of {} to match
                if(preg_match('#^\$\{('.MATHSCRIPT_IDENTIFIER.')\}#', $rest_of_expr, $matches))
                {
                    //if we have a variable with that exact name, 
                    if(array_key_exists($matches[1], $this->vars))
                    {
                        //append the _value_ of the new variable
                        $new_expr .= $this->vars[$matches[1]];

                        //and continue past the end of the varaible
                        $i += strlen($matches[1]) + 2;
                        continue;
                    }
                    // if the varaible does't exist, throw an error
                    else
                    {
                        $this->trigger('Tried to $-reference an undefined variable "'.$matches[1].'".');
                        return '';
                    }
                }

                //if we've run into a TCL-style variable, in $identifier form
                elseif(preg_match('#^\$('.MATHSCRIPT_IDENTIFIER.')#', $rest_of_expr, $matches))
                {
                    //get the longest matching varaible
                    $var_name = $this->longest_matching_varaible($matches[1]);

                    //if the variable is defined, replace it with its value
                    if($var_name !== null)
                    {
                        //add the variable's _value_ to the string
                        $new_expr .= $this->vars[$var_name];

                        //and continue past the end of the variable
                        $i += strlen($var_name);
                        continue;
                    }
                }
                //if we're parsing a tcl-style call, and we've a space
                elseif(self::is_preprocessor_whitespace($char) && $tcl_call && !$quote && $paren_depth == 0) 
                {
                    //if this is the first space, add a comma before it
                    if(!$last_was_space)
                        $new_expr .= ',';

                    //and set that the last character was a space
                    $last_was_space = true;
                }
                //otherwise, indicate that we haven't hit whitespace (that we care about)
                else
                {
                    $last_was_space = false;
                }
                
            }

            //push the character directly to the string
            $new_expr .= $char;
        }

        //return the new expression, surrounded by the prefix and suffix
        return $prefix.$new_expr.$suffix;
    }

    /**
     * Finds the longest variable which matches the start of $full_name.
     * Primarily used for TCL-style $-substitution. 
     * 
     * @param string $full_name     The "full name" of a $-prefixed indentifier, for variable matching.
     * @return string               The name of the longest variable which matches.
     */
    private function longest_matching_varaible($full_name)
    {
        //start off assuming we haven't matched any variable
        $current_var = null;

        //store the length of the matched variable; this allows us to quickly determine the longer of the two variables
        $current_length = 0;

        //for each of the known variables
        foreach($this->vars as $var => $value)
        {
            //get the length of the variable name
            $length = strlen($var);

            //if the new variable is longer than our current one, and our matches the start of our our "full name"
            if($length > $current_length && substr($full_name, 0, $length) == $var)
            {
                //set it to our current variable
                $current_var = $var;

                //and store its length for quick comparison
                $current_length = $length;
            }
        }

        //return the current variable (or null, if we didn't find anything)
        return $current_var;

    }

    /**
     * Returns true iff the given character is considered whitespace by the preprocessor.
     */
    static function is_preprocessor_whitespace($char)
    {
        return in_array($char, array(' ', "\n", "\t", "\r", PHP_EOL));
    }

    /**
     * Terminate any currently running execution. 
     */
    public function _break()
    {
        //set the internal execution flag to false, stopping execution
        $this->executing = false;
    }

    public function _return($value)
    {
        //store the given return value
        $this->last_return_value = $value;

        //and set the internal execution flag to false, stopping execution
        $this->executing = false;
    }


	/**
     * @author Logan Baron, from code contributed to PHP.net
     */
	private static function find_next_newline($haystack, $offset=0)
	{
		//start a new array of matches
		$chr = array();
	
		//for each character which can be considered a newline
		$newlines = array(PHP_EOL, "\n", "\r");

		//for each possible newline
		foreach($newlines as $needle)
		{
			//try to find the given newline in the string
			$res = strpos($haystack, $needle, $offset);
				
			//if we found it, store its location
			if ($res !== false) 
				$chr[$needle] = $res;
		}

		//if we haven't found any newlines, return false
		if(empty($chr)) 
			return false;
	
		//otherwise, return the first newline encountered
		return min($chr);
}	 

	//TODO: do this more efficiently?
    static function split_into_statements($math_script)
    {
        //start an empty array of lines
        $statements = array( 0 => '');

        //and start at the 0th line
        $statement = 0;

        //and start off outside of "/{ quotes
        $quotes = false;
        $depth = 0;

        //keep track of where the slashes are; we'll use double slashes as comments
        $last_was_slash = false;

        //for each character in the script
        for($i = 0; $i < strlen($math_script); ++$i)
        {
            //get the current character
            $char = substr($math_script, $i, 1);

            //if we have the escape character, keep the next character, and don't let it affect the flags
            if($char == '\\')
            {
                //copy the next character directly to the script
                $statements[$statement] .= substr($math_script, $i + 1, 1);

                //and continue from the character after that
                ++$i;
                continue;
            }

            //if the character is a quote, toggle quote mo      return $statements;e
            if($char == '"')
                $quotes = !$quotes;

            //if the character is an open-curly, increase the {-depth
            elseif($char == '{')
                ++$depth;

            //and if it's a close-curly, decrease the {-depth
            elseif($char == '}')
                --$depth;

            //if the last character was a slash, and this character is a slash
            if($depth == 0 && !$quotes && $last_was_slash && $char == '/')
            {
                //remove the last slash from the string
                $statements[$statement] = substr($statements[$statement], 0, -1);

                //skip until past the next newline
				$i = self::find_next_newline($math_script, $i);

                //if the string contains no newlines, then the comment spans the rest of the script; break
                if($i === false)
                    break;

                //and continue past the end of the comment
                $last_was_slash = false;
                continue;
            }

            //if we have a semicolon, move on to the next statement
            elseif($depth == 0 && !$quotes && $char == ';')
            {

                //move to the next statement
                ++$statement;

                //and initialize the new statement to an empty string
                $statements[$statement] = '';
            }

		    //if we've found any whitespace character, replace it with a single space
            elseif($depth == 0 && !$quotes && self::is_preprocessor_whitespace($char))
		         $statements[$statement] .= ' ';

            //otherwise, add the charcter to the current statement
            else
                $statements[$statement] .= $char;

            //if the character is a slash, set last_was_slash
            $last_was_slash = ($char == '/');
        }

        return $statements;
    }


    /**
     * Gets or sets the list of global variables. 
     * 
     * @param array $newvalue   The new array of variables to be used.
     * @return array            The current array of global variables.
     */
    public function vars(array $newvalue = null) 
    {
        //if a new value was specified, use it to set the internal variable list
        if(is_array($newvalue))
            $this->vars = $newvalue;

        //and return the internal variable list
        return $this->vars;
    }

    /**
     *  Gets a variable, or triggers an error if the variable does not exist.
     */
    public function & dereference($name)
    {
        //if the variable exists, return it
        if(isset($this->vars[$name]))
            return $this->vars[$name]; 

        //otherwise, trigger an error and retrun null
        $this->trigger_undefined_variable($name);
        return null;
    }

    /**
     * Gets or sets the internal list of function definitions.
     * 
     * @param mixed $newvalue 
     * @access public
     * @return void
     */
    public function funcs_raw($newvalue = null)
    {
        //if a new value was specified, use it to set the internal function list
        if(is_array($newvalue))
            $this->user_functions = $newvalue;

        //and return the internal function list
        return $this->user_functions;
    }
    
    /**
     * Returns a mathematical representation of the list of user-defined functions.
     * 
     * @access public
     * @return void
     */
    public function funcs()
    {   
        //start a new array, which which will store the function descriptions
        $output = array();

        //add each function to the array
        foreach ($this->user_functions as $fnn=>$dat)
            $output[] = $fnn . '(' . implode(',', $dat[MATHSCRIPT_ARGUMENTS]) . ')';

        //and return the completed array of functions
        return $output;
    }

    //===================== HERE BE INTERNAL METHODS ====================\\

    /**
     * Converts a given infix expression to a postfix one, using the Dijkstra's 
     *
     * @deprecated  Use infix() instead.
     * @param mixed $expr 
     * @return void
     */
    public function nfx($expr)
    {
        return $this->infix_to_postfix($expr);
    }

    /**
     * Converts a given infix expression into an internal postfix representation.
     *
     * FIXME: refactor this long function into multiple smaller ones?
     * 
     * @param mixed $expr 
     * @access public
     * @return void
     */
    protected function infix_to_postfix($expr) 
    {
        //create a new stack which we'll use for our shunting yard algorithm
        $stack = new mathscript_stack();

        //create a new, empty postfix expression, which will be populated herein
        $output = array(); 

        //Start off expecting an operand, rather than an opration.
        //We'll need to know when we're expecting an operation for:
        //1) syntax-checking purposes
        //2) using unary operators, like '-'
        $expecting_op = false; 

        //trim all whitespace from the beginning and ends of the expression
        $expr = trim($expr);

        //if there's any non-printable characters in the expression, throw an exception
        //if (preg_match(MATHSCRIPT_ILLEGAL_CHARS, $expr, $matches)) 
        //    return $this->trigger("illegal character '{$matches[0]}'");

        //if our expression is terminated by an operator, throw an exception
        //if(array_key_exists(substr($expr, -1, 1), self::$operators))
        //    return $this->trigger('no operand for '.substr($expr, -1));

        //loop until we break
        $expr_len = strlen($expr);

        //for each character in the string, by their index
        for($index = 0; $index < $expr_len; ) 
        {

            //get the first character of at the current position in the string as our operand
            $op = substr($expr, $index, 1);

            //get a version of of the operand that's two characters long, as well
            $double_op = substr($expr, $index, 2);
    
            //if the operand represents a two-character operator (like "==")
            //TODO: generalize to longer operators?
            if(strlen($double_op) == 2 && array_key_exists($double_op, self::$operators))
            {
                //use it as our operator
                $op = $double_op;

                //and move forward in the string by one
                ++$index;
            }

            
            // find out if we're currently at the beginning of a number/variable/function/parenthesis/operand
            $ex = preg_match('/^('.MATHSCRIPT_IDENTIFIER.'\(?|\d+(?:\.\d*)?|\.\d+|\()/', substr($expr, $index), $match);

            //if we have a minus, and we're not expecting it as a binary operator, treat it as a unary negation
            if ($op == '-' && !$expecting_op)
            { 
                //convert the ambiguous '-' into the unambiguous internal negation operator
                $stack->push('_'); 
                $index++;
            }
            
            //prohibit the internal negation operator from being used directly
            elseif ($op == '_') 
            {
                return $this->trigger("'_' cannot be used as an operator"); // but not in the input expression
            }

            //if we have a right associative unary operator, push it directly to the stack
            elseif(array_key_exists($op, self::$operators) && self::$operators[$op]['arity'] == '1' && self::$operators[$op]['right-associative'] && !$expecting_op)
            {
                $stack->push($op);
                $index++;
            }

            //if we were expecting an operator, and recieved an expresison
            elseif ($ex && $expecting_op)
            {
                return $this->trigger('expected operand, got '.$expr[$index].'!', $expr);
            } 

            //if we have an operator (and are expecting one), push it onto the stack
            elseif (array_key_exists($op, self::$operators) && $expecting_op) 
            { 
                
                //while there are still items on the stack (and a ton of other conditions below) [REFACTOR ME TO A FUNCTION?]
                while($stack->count > 0)
                {
                    //get the last item pushed onto the stack
                    $stack_top = $stack->last();

                    //if the "top" item on the stack isn't an operator
                    if(!array_key_exists($stack_top, self::$operators))
                        break;

                    //if the current operator has greater precedence than the previous operator, break
                    if(self::$operators[$op]['precedence'] > self::$operators[$stack_top]['precedence'])
                        break;

                    //if the current operator is right-associative and has equal precedence to the previous operator, break
                    if(self::$operators[$stack_top]['right-associative'] && (self::$operators[$op]['precedence'] == self::$operators[$stack_top]['precedence']))
                        break;

                    //push the item off the stack, and directly to the postfix output
                    $output[] = $stack->pop(); 
                }

                //now, push the new operator onto the stack
                $stack->push($op); // finally put OUR operator onto the stack

                //move forward in the expression
                $index++;

                //since we recieved the operator we're expecting, we'll now expect an operand
                $expecting_op = false;

            } 
            
            //if we the next character is a parenthesis, "clear" the stack until we hit the matched open-paren
            elseif ($op == ')' and $expecting_op) 
            { 

                //while the operator on top of the stack _isn't_ the matched parenthesis
                while (($new_op = $stack->pop()) != '(') 
                {
                    //if we ran out of operands on the stack, parens must be unbalanced; trigger an error
                    if (is_null($new_op)) 
                        return $this->trigger("unexpected ')'");

                    //otherwise, push the non-paren oeprator directly to the output
                    else 
                        $output[] = $new_op;
                }


                //if we just finished parsing a function call
                if (preg_match(MATHSCRIPT_FUNCTION_CLOSE, $stack->last(2), $matches)) 
                { 
                    //extract the name of the function, 
                    $function_name = $matches[1]; 

                    //determine the argument count
                    $arg_count = $stack->pop();

                    //Get the function token, which we generated when we first encountered the function name.
                    $function_token = $stack->pop();

                    //and push the function data to the stack
                    $output[] = array(MATHSCRIPT_FUNCTION_TOKEN => $function_token, MATHSCRIPT_FUNCTION_NAME => $function_name, MATHSCRIPT_ARGUMENT_COUNT => $arg_count); 

                    //check the arguments for the  the case where this is one of our language functions, which are defined below
                    if($this->extensions->function_exists($function_name)) 
                    {
                        //if the function isn't variadic, and we recieved an unexpected amount of arguments, throw an error
                        if (!$this->extensions->function_accepts_arg_count($function_name, $arg_count))
                            return $this->trigger('no definition of '.$function_name.' takes '.$arg_count.' arguments.');
                        
                    } 

                    //checks the arguments for for the case that this is a built-in function
                    elseif (array_key_exists($function_name, $this->user_functions)) 
                    {
                        //if an incorrect amount of arguments, raise an error
                        if ($arg_count != count($this->user_functions[$function_name][MATHSCRIPT_ARGUMENTS]))
                            return $this->trigger("wrong number of arguments for $function_name: ($arg_count given, " . count($this->user_functions[$function_name][MATHSCRIPT_ARGUMENTS]) . " expected)");

                    } 

                    //if a non-function was interpreted as a function, something's gone wrong
                    else 
                    { 
                        return $this->trigger("internal error: non-function was interpreted as function");
                    }
                }

                //increase our position in the string
                $index++;

            } 
            //if the current operand is a comma, and we've just finished parsing an non-operator, 
            //then we've likely just finished parsing a function argument
            elseif ($op == ',' and $expecting_op) 
            { 

                //remove all elements from the stack, until we hit a open-paren
                while (($new_op = $stack->pop()) != '(') 
                {
                    //if we don't find an open-paren, then this wasn't a valid function
                    if (is_null($new_op))
                        return $this->trigger("unexpected ','"); // oops, never had a (

                    //otherwise push all non-paren operators from the stack onto the output, effectively parsing the operand expression
                    else 
                        $output[] = $new_op; // pop the argument expression stuff and push onto the output
                }

                //if the given line isn't finished like a function call, trigger an error
                if (!preg_match(MATHSCRIPT_FUNCTION_CLOSE, $stack->last(2), $matches))
                    return $this->trigger("unexpected ','");

                //We now need to increase the argument count of the call, which is already on top the operator stack.
                //We'll just pop it off, increment it, and push it back on.
                $stack->push($stack->pop()+1); 

                //Finally, we push the open-parenthesis back onto the stack. This allows us to perform the same parsing operation
                //for each of the arguments, independent of state or place. (Not the way I'd have done this if I were writing the code
                //from scratch, but I'm not going to modify it now. ~ktemkin)
                $stack->push('(');

                //move forward in the string 
                $index++;

                //and indicate that we are now expecting a non-operator
                $expecting_op = false;
            } 

            //if we have an open-paren, and we're not expecting an operator, it's a grouping parenthesis, and not the start of a function
            elseif ($op == '(' && !$expecting_op) 
            {
                //all we have to do is push it onto the stack
                $stack->push('('); 

                //move forward into the string
                $index++;
            }

            //if we have an normal expression, and we're expecting a non-operator, handle it:
            elseif ($ex and !$expecting_op) 
            { 
                $val = $match[1];

                //if we're opening a function
                if (preg_match(MATHSCRIPT_FUNCTION_CLOSE, $val, $matches)) 
                {
                    //if the function is a known function, of any type
                    if (array_key_exists($matches[1], $this->user_functions) || $this->extensions->function_exists($matches[1]))
                    { 
                        //push the function token onto the stack
                        $stack->push($val);

                        //followed by a default argument count
                        $stack->push(1);

                        //start grouping as though we were inside of a parenthesis; this allows us to evaluate sub-expressions as function arguments
                        $stack->push('(');

                        //and indicate that we're expecting a sub-expresion, and not an operator, as we previously thought
                        $expecting_op = false;
                    }
                    //otherwise, trigger an error, as we're calling a non-function
                    else 
                    {
                        $this->trigger($matches[1] . 'undefined function '.$matches[1]);
                        /*
                        $val = $matches[1];
                        $output[] = $val;
                         */
                    }
                }
                //otherwise, we have a regular number or variable, and not a subexpression 
                else 
                { 
                    //push it directly to the output
                    $output[] = $val;

                    //we've recieved a generic expression, so we should now recieve some kind of operator
                    $expecting_op = true;
                }

                //move past the end of the expression
                $index += strlen($val);


            }

            //if we have a close-paren, and we're not expecting an operator, then we _must_ be closing a nullary function,
            //(or have an a malformed expression)
            elseif ($op == ')') 
            {
                //if we did not just open a parenthesis, or we haven't just started a function, trigger an error
                if ($stack->last() != '(' || $stack->last(2) != 1) 
                    return $this->trigger("unexpected ')'");

                //if we did just close a nullary function
                if (preg_match(MATHSCRIPT_FUNCTION_CLOSE, $stack->last(3), $matches)) 
                { 
                    //remove the open-paren and argument count from the stack
                    $stack->pop();
                    $stack->pop();

                    //and pull the function token and name off of the stack
                    $function_token = $stack->pop();
                    $function_name = $matches[1]; 

                    //if the function doesn't have a nullary form, thrigger an error
                    if(!$this->extensions->function_accepts_arg_count($function_name, 0))
                        return $this->trigger('no form of '.$function_name.' accepts zero arguments');

                    //push the function call directly onto the stack
                    $output[] = array(MATHSCRIPT_FUNCTION_TOKEN =>$function_token, MATHSCRIPT_FUNCTION_NAME =>$function_name, 'argcount'=> 0 );

                    //and move forward with the parsing
                    $index++;

                }
                //otherwise, we have a close-paren which shouldn't exist; trigger an error 
                else 
                {
                    return $this->trigger("unexpected ')'");
                }
            }

            //if we've hit an open-paren, this is a nestable string; parse it
            elseif($op == '{' && !$expecting_op)
            {
                //start off with an empty string
                $string = '';

                //and start off at the outermost level of nesting
                $level = 0;

                //move past the initial open-bracket
                ++$index;

                //while we haven't hit a close-curly in the outermost level 
                while(($char = substr($expr, $index, 1)) != '}' || $level > 0)
                {

                    //if we've run past the end of the expression, a { must be unterminated somewhere
                    if($index >= strlen($expr))
                        return $this->trigger('Unterminated {');

                    //if we've hit an open-curly, increase the nesting level
                    if($char == '{')
                        ++$level;

                    //and if we've hit an close-curly, decrease the nesting level
                    if($char == '}')
                        --$level;

                    //if we've hit an escape character, 
                    if($char == '\\')
                        ++$index;

                    //and add the character to the string
                    $string .= substr($expr, $index, 1);

                    //move on to the next character
                    ++$index;

                }

                //add the newly-created string to the output
                $output[] = array('string' => $string);

                //we should now expect an operator on the string
                $expecting_op = true;

                //and move on to the next token
                ++$index;
            }


            //if we've hit a open-quote, this is a string; parse it
            elseif($op == '"' && !$expecting_op)
            {
                //start from the next character
                ++$index;

                //build a string
                $string = '';

                //while we haven't hit a closing quote
                while(($char = substr($expr, $index, 1)) != '"')
                {
                    //if we've run past the end of our string, there must be an unbalanced quote
                    if($index >= strlen($expr))
                        return $this->trigger('unterminated "');

                    //if we've hit an escape character
                    if($char == '\\')
                    {
                        ++$index;
                        $char = substr($expr, $index, 1);
                    }

                    //add the character to the string
                    $string .= $char;

                    //move forward in the string
                    ++$index;
                }

                //add the newly-created string to the output
                $output[] = array('string' => $string);

                //we should now expect an operator on the string
                $expecting_op = true;

                //and move on to the next token
                ++$index;
            }

            //handle the case in which we recieved an operator, but wasn't expecting one 
            elseif (array_key_exists($op, self::$operators) and !$expecting_op) 
            {
                return $this->trigger("unexpected operator '$op'");
            }

            //if none of our parser cases apply, then something's gone wrong
            else 
            { 
                return $this->trigger("an unexpected error occured in $expr; ensure that this expression is well-formed");
            }
            
           

            //skip whitespace in blocks; this speeds up execution
            while (self::is_preprocessor_whitespace(substr($expr, $index, 1)))
                $index++;

        }

        while (!is_null($op = $stack->pop())) 
        {
            //if an open-paren is still on the stack, we must have had unbalanced parenthesis
            if ($op == '(') 
                return $this->trigger("expecting ')'"); // if there are (s on the stack, ()s were unbalanced

            //push the operators directly onto the output
            $output[] = $op;
        }

        //and return the output
        return $output;
    }

    /**
     * Convenience method which converts a given infix string to postfix, and evaluates it, in a single step. 
     * 
     * @param string $infix     A string containg the infix data to be processed.
     * @param array $vars       An array of any loal variables to be used.
     * @return mixed            The result of the evaulation.
     */
    function evaluate_infix($infix, array $vars = array())
    {
        return $this->evaluate_postfix($this->infix_to_postfix($infix, $vars)); 
    }

    /**
     * Convenience shortcut for evaluate_postfix.
     * 
     * @deprecated
     * @param mixed $tokens 
     * @param array $vars 
     * @access public
     * @return void
     */
    function pfx($tokens, array $vars = array()) 
    {
        return $this->evaluate_postfix($tokens, $vars);
    }



    /**
     * Evaluate a given "postfix" token stack, and returns a numeric result.
     * 
     * @param array $tokens 
     * @param array $vars 
     * @access public
     * @return void
     */
    function evaluate_postfix($tokens, array $vars = array()) 
    {

        //if we didn't recieve any tokens 
        if ($tokens == false) 
            return false;

        //create a new stack, which we'll use to evaluate the "postfix" expression
        $stack = new mathscript_stack();

        //for each token in the postfix string
        foreach ($tokens as $token)
        {
    
            //if the token is an array, it represents a function; evaluate it
            if (is_array($token) && !array_key_exists('string', $token)) 
            { 
                //get the function name, and the amount of arguments specified
                $function_name = $token[MATHSCRIPT_FUNCTION_NAME];
                $arg_count = $token[MATHSCRIPT_ARGUMENT_COUNT];

                //handle constructed functions, which are written in PHP for use by the scripted language
                if ($this->extensions->function_exists($function_name))
                {
                    //start a new array, which will hold the function arguments
                    $args = array();

                    //for($i = $arg_count - 1; $i >= 0 ; --$i)
                    for($i = 0; $i < $arg_count; ++$i)
                    {
                        //pop the top element off the stack
                        list($reference, $top) = $stack->pop();

                        //if this function expects pass-by-refernce, the pass it a reference to the object
                        if($this->extensions->function_argument_by_reference($function_name, $i))
                        {
                            //Note the lack of a check for existence. This allows us to _create_ variables by reference.
                                    
                            //if our reference was supplied as a literal, use it as a pointer
                            if(is_null($reference))
                                $args[$i] =& $top;
                            else
                                $args[$i] =& $reference;
                        }
                        //add the new element to the arguments array
                        else
                        {
                            //if we're trying to operate on an undefined variable, raise an error
                            if(is_null($top))
                                return $this->trigger_undefined_variable($reference);

                            //otherwise, pass in the variable
                            $args[$i] = $top;
                        }

                    }

                    //Call the constructed function with the given arguments, and retrieve the results.
                    $result = $this->extensions->call($function_name, array_reverse($args));

                    //if the result of the given function was false, something went wrong internally
                    //TODO: replace with exception?
                    if($result === false)
                        return $this->trigger('internal error in function '.$function_name);

                    //push the result onto the stack
                    $stack->push(array(MATHSCRIPT_TYPE_RESULT, $result));
               
                }
                //otherwise if this is a user (runtime) defined function, handle it 
                elseif (array_key_exists($function_name, $this->user_functions)) 
                {
                    //extract the expected amount of function arguments
                    $expected_args = count($this->user_functions[$function_name][MATHSCRIPT_ARGUMENTS]);

                    //start a new associative array, which will map the argument _name_ to the given argument
                    $args = array();
                    
                    //for each argument provided
                    for ($i = $expected_args - 1; $i >= 0; $i--) 
                    {
                        //pull a single argument from the top of the stack
                        list($reference, $top) = $stack->pop();

                        if(is_null($top))
                            return $this->trigger_undefined_variable($reference);

                        //get the name for the current argument
                        $arg_name = $this->user_functions[$function_name][MATHSCRIPT_ARGUMENTS][$i];

                        //and add the name/value pair to our array
                        $args[$arg_name] = $top;
                    }

                    //take a snapshot of the current system state
                    $initial_vars = $this->vars;

                    //merge in the arguments as local variables
                    $this->vars = array_merge($this->vars, $args);

                    //evaluate the given function via recursion
                    $result = $this->evaluate_postfix($this->user_functions[$function_name][MATHSCRIPT_FUNCTION_BODY]);

                    //and discard any modifications to the variable space
                    $this->vars = $initial_vars;

                    //and push the result onto the stack
                    $stack->push(array(MATHSCRIPT_TYPE_RESULT, $result));
                }
            }

            //handle binary operators
            elseif(!is_array($token) && array_key_exists($token, self::$operators) && self::$operators[$token]['arity'] == 2)
            {
                //pop two operands off the stack
                list($ref2, $op2) = $stack->pop();
                list($ref1, $op1) = $stack->pop();

                //if we didn't get an operand, throw an error
                if(is_null($op1))
                    return $this->trigger_undefined_variable($ref1);
                if(is_null($op2))
                    return $this->trigger_undefined_variable($ref2);

                //get the handler for the given operator
                $handler = self::$operators[$token]['handler'];

                //call the given handler to perform the operation
                $result = call_user_func($handler, $this, $op1, $op2);

                //and push the result onto the stack
                $stack->push(array(MATHSCRIPT_TYPE_RESULT, $result));
            }
            //handle unary operators
            elseif(!is_array($token) && array_key_exists($token, self::$operators) && self::$operators[$token]['arity'] == 1)
            {
                //pop the operand off the stack
                list($ref, $op) = $stack->pop();

                //get the handler for the given operator
                $handler = self::$operators[$token]['handler'];

                //if the operator is designed for pass-by-reference
                if(self::$operators[$token]['by_reference'])
                {
                    //get a reference to the operand
                    $arg =& $ref;
                }
                else
                {
                    //if we didn't get an operand, throw an error
                    if(is_null($op))
                        return $this->trigger_undefined_variable($ref);

                    //set the argument equal to the operand
                    $arg = $op;
                }

                //call the given handler to perform the operation
                $result = call_user_func($handler, $this, $arg);

                //and push the result onto the stack
                $stack->push(array(MATHSCRIPT_TYPE_RESULT, $result));
            }
 
            //handle variables and literals
            else 
            {
                //if the token is an array with a "string" key, it's a string- push the core string to the stack, directly
                if(is_array($token) && array_key_exists('string', $token))
                    $stack->push(array(MATHSCRIPT_TYPE_LITERAL, $token['string']));

                //if we've recieved a numeric token, push it directly onto the stack
                elseif (is_numeric($token)) 
                    $stack->push(array(MATHSCRIPT_TYPE_LITERAL, $token));

                //if we've recieved a local variable, push its value onto the stack
                elseif (array_key_exists($token, $vars)) 
                    $stack->push(array($token, $vars[$token]));

                //if we've recieved a global variable, push its value onto the stack
                elseif (array_key_exists($token, $this->vars)) 
                    $stack->push(array($token, $this->vars[$token]));

                //otherwise, we have a variable of unknown value;
                //we'll push it onto the array as having the value null
                else 
                    $stack->push(array($token, null));
                    //return $this->trigger("undefined variable '$token'");
            }
        }

        // when we're out of tokens, the stack should have a single element: the final result
        if ($stack->count != 1) 
            return $this->trigger('internal error: '.$stack->count.' values were left on the stack after execution.');

        //return the final result
        list($reference, $result) = $stack->pop();

        return $result;
    }

    function trigger_undefined_variable($varname, $line='')
    {
        return $this->trigger('tried to reference the value of '.$varname. ' before it was assigned a value', $line);
    }

    // trigger an error, but nicely, if need be
    function trigger($msg, $line = '') 
    {
        ///XXX: TODO: exception?
        //store the last error message
        $this->last_error = $msg;

        //print error messages directly to the PHP output if debug show errors is on
        if($this->debug_show_errors)
            echo '<b>ERROR:</b>&nbsp; '.$msg.' ('.$line.')</br>';
 
        //if surpress_errors is off, throw the warning
        if (!$this->suppress_errors) 
            trigger_error($msg, E_USER_WARNING);

       //return false, indicating failure
        return false;
    }
}

/**
 * XXX
 * This stack came with the original EvalMath class; though I'm lost as to why it's used instead of $array[]/array_push and array_pop, as
 * there's no documentation. I'm going to assume the author felt it was more efficient to keep a single array of max length, instead of constantly 
 * resizing to shrink the array.
 */
class mathscript_stack 
{

    var $stack = array();
    var $count = 0;

    /**
     * Push an object onto the stack.
     */
    function push($val) 
    {
        $this->stack[$this->count] = $val;
        $this->count++;
    }

    /**
     * Pop an object from the stack, and then return it.
     */
    function pop() 
    {
        if ($this->count > 0) {
            $this->count--;
            return $this->stack[$this->count];
        }
        return null;
    }

    /**
     * Peeks at the element in position SP-$n; so n=1 would indicate the last element, and n=1 the second-to-last.
     */
    function last($n=1) 
    {
        //if we have a valid element, return it
        if ($this->count - $n >= 0) 
            return $this->stack[$this->count-$n];
        //otherwise, return null
        else
            return null;
    }
}


/**
 * Manages MathScript extensions, which add 
 * 
 * @package 
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class mathscript_extension_manager
{
    /**
     * The internal function cache; stores funciton meta-data to speed execution.
     * @var array
     */
    private $function_cache;


    private $parent;

    /**
     * Creates a new MathScript extension manager. 
     */
    public function __construct($parent, $modules = array())
    {
        //store a reference to the owning MathScript class
        $this->parent = $parent;

        //load each of the specified modules
        foreach($modules as $module)
            $this->use_module($module);
    }
    
    /**
     * Attempts to call a function loaded from an extension. 
     * 
     * @param string $function_name     The function name to call.
     * @param mixed $args               The arguments to be passed to the given function.
     */
    public function call($function_name, $args)
    {
        //get the function's information from the cache
        $function = $this->get_cached_function($function_name);

        //if we couldn't find a function, return null
        //FIXME: throw an exception
        if($function === null)
            return null;

        //insert the caller as the first argument to the given function
        array_unshift($args, $this->parent);

        //
        // Then, call it, and return the resultant value.
        //
        return call_user_func_array($function->signature, $args);

    }

    /**
     * Returns an array containing metadata on a cached function.
     */
    private function get_cached_function($function_name)
    {
        //if the function exists in our function cache, use it
        if(array_key_exists($function_name, $this->function_cache))
            return (object)$this->function_cache[$function_name];

        //also accept a funciton whose name is prefixed with '_'; this allows us to override PHP's built-ins when writing extensions
        elseif(array_key_exists('_'.$function_name, $this->function_cache))
            return (object)$this->function_cache['_'.$function_name];

        //otherwise, we find the function to call; return null
        else
            return null;
    }

    /**
     * Returns true iff the given function exists in one of the loaded extensions. 
     */
    public function function_exists($function_name)
    {
        //A given function exists if it's located in our constructed function cache.
        //
        //We allow the extension modules to optionally prefix the funciton name with '_'; this allows us
        //to override PHP's built-in functions.
        return array_key_exists($function_name, $this->function_cache) || array_key_exists('_' . $function_name, $this->function_cache);
    }

    /**
     * Returns true if the given argument _position_ should be passed by reference ("pass by name").
     * 
     * @param string $function_name    The name of the function being evaluated.
     * @param int    $position         The integer posistion of the argument.
     * @return bool                    True iff the argument should be passed by reference.
     */
    public function function_argument_by_reference($function_name, $position)
    {
        //get the function's metadata
        $function = $this->get_cached_function($function_name);

        //return true iff the given "reference position" flag is set
        return (isset($function->reference_positions[$position]) && $function->reference_positions[$position]);
    }


    /**
     * Returns true iff the given function will accept the given amount of arguments. 
     */
    public function function_accepts_arg_count($function_name, $count)
    {
        //get the given function's metadata
        $function = $this->get_cached_function($function_name);

        if($function === null)
            return null;

        //return true if the function is variadic, or accepts the given number of arguments
        return (in_array(-1, $function->argcount) || in_array($count, $function->argcount));
    }   

    /**
     * Imports a given MathScript module, adding "constructed" functions to the MathScript language.
     * 
     * @param string $modulename    The module name. This is the same as a defined class name, minus the leading "mathscript_".
     */
    private function use_module($modulename)
    {
        $this->use_by_class('mathscript_'.$modulename);
    }


    private function use_by_class($classname)
    {
        //get a reflection object, which we'll use to retrieve all methods of our course modujle
        $reflection = new ReflectionClass($classname);

        //get all methods inside of the new module object
        $methods = $reflection->getMethods();

        //for each method in module class
        foreach($methods as $method)
        {
            //if the method isn't public/static, ignore it
            if(!$method->isPublic())
                continue;

            //get the minimum and maximum amount of parameters for the method
            $min_args = $method->getNumberOfRequiredParameters();
            $max_args = $method->getNumberOfParameters();

            //and get the method's name
            $function_name = $method->getName();

            //if the function takes no arguments, assume it's variadic
            if($max_args === 0 && $min_args === 0)
            {
                $num_args = array(-1);
                $positions_by_reference = array();
            } 
            else
            {
                //otherwise, assume it may have any amount of arguments between the minimum and maximum, plus the first argument, $self
                $num_args = range($min_args - 1, $max_args - 1);

                //Parse each of the arguments for the function; this will allow us to use PHP decorations (like by-reference)
                //to more easily construct mathscript modules.
                //
                //This isn't the cleanest way to do this, but as PHP lacks decorators... this is probably the easiest on the developer.
                $params = $method->getParameters();

                //Positions which are marked as "pass by reference" are noted specially, as this has a special meaning in mathscript.
                //("Pass by name.")
                $positions_by_reference = array();

                //Find any positional argument which is passed by reference.
                //Note that we subtract one; as the first argument should always be $self.
                foreach($params as $position => $param)
                    if($param->isPassedByReference())    
                        $positions_by_reference[$position - 1] = true;

            }

            //add the function to our function cache
            $this->function_cache[$function_name] = array
                (
                    'argcount' => $num_args,
                    'signature' => array($classname, $function_name),
                    'reference_positions' => $positions_by_reference,
                    'returns_by_reference' => $method->returnsReference()
                );
        }
    }
}

/**
 * Package which contains the functions which evaluate the default MathScript operators.
 * Can be loaded for access to the operator functions, if desired.
 * 
 * @package 
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class mathscript_operators
{
    public static function add($self, $a, $b)
    {
        return $a + $b;
    }

    public static function subtract($self, $a, $b)
    {
        return $a - $b;
    }

    public static function multiply($self, $a, $b)
    {
        return $a * $b;
    }

    public static function divide($self, $dividend, $divisor)
    {
        return floatval($dividend) / floatval($divisor);
    }

    public static function power($self, $base, $exponent)
    {
        return pow($base, $exponent);
    }

    public static function inverse($self, $number)
    {
        return -1*$number;
    }

    public static function modulus($self, $a, $b)
    {
        //mod a and b
        $retval = $a % $b;

        //PHP's mod operator has an odd behavior where it leaves negative operators negative
        //we'll fix this, for a more standard behavior
        if($retval < 0)
            $retval += $b;

        //return the result
        return $retval;
    }

    //Boolean and bitwise logic


    public static function bitwise_and($self, $a, $b)
    {
        return $a & $b;
    }

    public static function bitwise_or($self, $a, $b)
    {
        return $a | $b;
    }

    public static function bitwise_xor($self, $a, $b)
    {
        return intval($a) ^ intval($b);
    }

    public static function _and($self, $a, $b)
    {
        return ($a && $b) ? 1 : 0;
    }

    public static function _or($self, $a, $b)
    {
        return ($a || $b) ? 1 : 0;
    }

    public static function _not($self, $a)
    {
        return $a ? 0 : 1;    
    }

    public static function bitwise_not($self, $a)
    {
        return ~ intval($a);
    }


    //Comparison Operators


    public static function equals($self, $a, $b)
    {
        return ($a == $b) ? 1 : 0;
    }

    public static function not_equals($self, $a, $b)
    {
        return ($a == $b) ? 0 : 1;
    }

    public static function greater_than($self, $a, $b)
    {
        return ($a > $b) ? 1 : 0;
    }

    public static function less_than($self, $a, $b)
    {
        return ($a < $b) ? 1 : 0;
    }

    public static function greater_equal($self, $a, $b)
    {
        return ($a >= $b) ? 1 : 0;
    }

    public static function less_equal($self, $a, $b)
    {
        return ($a <= $b) ? 1 : 0;
    }

    //String containation
    public static function concat($self, $a, $b)
    {
        return $a.$b;
    }

    //increment/decrement
    public static function increment($self, &$a)
    {
        $self->vars[$a] = $self->dereference($a) + 1;   
    }

    public static function decrement($self, &$a)
    {
        $self->vars[$a] = $self->dereference($a) - 1;
    }

    //public static function 


}

class mathscript_spreadsheet
{

    /**
     * Calculates the average of a set of input numbers.
     */
    public static function average()
    {
        //retrieve the arguments passed to the function, and extracts the reference to the execution environment
        $args = func_get_args();
        $self = array_shift($args);

        //return the average of the array
        return array_sum($args) / count($args); 
    }

    public static function sum()
    {
        //retrieve the arguments passed to the function, and extracts the reference to the execution environment
        $args = func_get_args();
        $self = array_shift($args);

        //sum all of the elements
        return array_sum($args);
    }

    public static function max()
    {
        //retrieve the arguments passed to the function, and extracts the reference to the execution environment
        $args = func_get_args();
        $self = array_shift($args);

        //return the max of the elements
        return max($args);
    }

    public static function min()
    {
        //retrieve the arguments passed to the function, and extracts the reference to the execution environment
        $args = func_get_args();
        $self = array_shift($args);

        //return the minimum of the arguments
        return min($args);
    }
}

class mathscript_basicmath
{
   public static function mod($self, $dividend, $divisor)
    {
        //calculate the mod of the two-numbers
        $mod = $dividend % $divisor;

        //and compensate for PHP's nonstandard behavior when the dividend is negative
        if($mod < 0)
            $mod += $divisor;

        //return the result
        return $mod;
    }


    public static function pi($self)
    {
        return pi();
    }

    public static function power($self, $base, $exponent)
    {
        return pow($base, $exponent);
    }

    public static function pow($self, $base, $exponent)
    {
        return pow($base, $exponent);
    }

    public static function floor($self, $a)
    {
        return floor($a);
    }

    public static function ceil($self, $a)
    {
        return ceil($a);
    }

    public static function rad2deg($self, $a)
    {
        return rad2deg($a);
    }

    public static function log2($self, $a)
    {
        return log($a, 2);
    }

    public static function round($self, $val, $precision = 0)
    {
        return round($val, $precision);
    }

    public static function sqrt($self, $a)
    {
       return sqrt($a); 
    }

    public static function abs($self, $a)
    {
       return abs($a); 
    }

    public static function ln($self, $a)
    {
       return ln($a); 
    }

    public static function log($self, $a)
    {
       return log($a); 
    }

    public static function exp($self, $a)
    {
       return exp($a); 
    }

    //
    // Trig functions:
    //

    public static function sin($self, $a)  
    {   
        return sin($a);
    }

    public static function sinh($self, $a)
    {
        return sinh($a);
    }

    public static function arcsin($self, $a)
    {   
        return asin($a);
    }

    public static function asin($self, $a)
    {
        return asin($a);
    }

    public static function arcsinh($self, $a)
    {
        return asinh($a);
    }

    public static function asinh($self, $a)
    {
        return asinh($a);
    }

    public static function cos($self, $a)
    {
        return cos($a);
    }

    public static function cosh($self, $a)
    {
        return cosh($a);
    }

    public static function acos($self, $a)
    {
        return acos($a);
    }

    public static function arccos($self, $a)
    {
        return acos($a);
    }

    public static function arccosh($self, $a)
    {
        return acosh($a);
    }

    public static function acosh($self, $a)
    {
        return acosh($a);
    }

    public static function tan($self, $a)
    {
        return tan($a);
    }

    public static function tanh($self, $a)
    {
        return tanh($a);
    }

    public static function arctan($self, $a)
    {
        return atan($a);
    }

    public static function atan($self, $a)
    {
        return atan($a);
    }

    public static function arctanh($self, $a)
    {
        return atanh($a);
    }

    public static function atanh($self, $a)
    {
        return atanh($a);
    }
}
