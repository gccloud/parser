<?php
/**
 * CodeIgniter
 *
 * @package CodeIgniter
 * @author  EllisLab Dev Team
 * @copyright   Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @copyright   Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @license http://opensource.org/licenses/MIT  MIT License
 * @link    http://codeigniter.com
 * @since   Version 1.0.0
 */
defined('BASEPATH') OR exit('No direct script access allowed');

// ------------------------------------------------------------------------

/**
 *  Class MY_Parser
 *  CodeIgniter Parser Library extension
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Library
 * @author      Gregory Carrodano
 * @version     20151021
 */
class MY_Parser extends CI_Parser {

    /**
     * Parses pseudo-variables contained in the specified template, replacing them with the data in the second param
     * @param  string
     * @param  array
     * @param  bool
     * @return string
     */
    protected function _parse($template, $data, $return = FALSE) {
        // First check if we've got something to parse
        if ($template === '') {
            return FALSE;
        }

        // Retrieve and load all CI vars
        $data = array_merge($data, $this->CI->load->get_vars());

        // Check for loop statements
        $template = $this->_parse_loops($template, TRUE);

        $replace = array();
        foreach($data as $key => $val) {
            $replace = array_merge(
                $replace,
                is_object($val) ? $this->_parse_object($key, $val, $template) :
                (is_array($val) ? $this->_parse_pair($key, $val, $template) :
                $this->_parse_single($key, (string) $val, $template))
            );
        }

        // Variable replacement (actually viable only for every variable defined in $data, not tags defined in the parsed template)
        foreach($replace as $from => $to) {
            $template = str_ireplace($from, $to, $template);
        }

        // Unparsed tags removal
        $template = $this->_remove_unparsed($template);

        // Check for helpers calls
        $template = $this->_parse_helpers($template, $data);

        // And last, check for conditional statements
        $template = $this->_parse_switch($template, TRUE);
        $template = $this->_parse_conditionals($template, TRUE);

        unset($data);

        if ($return === FALSE) {
            $this->CI->output->append_output($template);
        }

        return $template;
    }

    /**
     * Parses conditionals pseudo-variables contained in the specified template view
     * @param  string
     * @param  array
     * @return string
     */
    protected function _parse_conditionals($template, $preprocess = FALSE) {
        // Some settings
        $currency = '&pound;';

        if($preprocess) {
            // Pre-parsing process : we'll first replace each {if}...{/if} pair by a numbered one - {if(n)}...{/if(n)} - for correct processing
            $if_pattern = $this->l_delim.'if ';
            $endif_pattern = $this->l_delim.'\/if'.$this->r_delim;

            preg_match_all('#'.$if_pattern.'|'.$endif_pattern.'#sU', $template, $preprocess, PREG_SET_ORDER);

            if( ! empty($preprocess)) {
                $count = 0;
                $last_count = array();
                foreach($preprocess as $p) {
                    if($p[0] === $if_pattern) {
                        ++$count;
                        $last_count[] = $count;
                        $template = preg_replace('#'.$if_pattern.'#', $this->l_delim.'if'.$count.' ', $template, 1);
                    }
                    else {
                        $last = array_pop($last_count);
                        $template = preg_replace('#'.$endif_pattern.'#', $this->l_delim.'/if'.$last.$this->r_delim, $template, 1);
                    }
                }
            }
        }

        // First we'll check for IF conditionals
        preg_match_all('#'.$this->l_delim.'if(\d+) (.+)'.$this->r_delim.'(.+)'.$this->l_delim.'\/if(\1)'.$this->r_delim.'#sU', $template, $conditionals, PREG_SET_ORDER);

        if( ! empty($conditionals)) {
            foreach($conditionals as $conditional) {
                // First we extract the content we want to output if the conditional is satisfied
                $output = $conditional[3];

                // And dissect the if statement to get the comparison values and operator. Also remove any currency characters.
                $statement = str_replace($currency, '', $conditional[2]);

                preg_match('#(.+\s?)(>|>=|<>|!=|==|<=|<)(.+\s?)#', $statement, $comparison);

                $a = (trim($comparison[1]) != '') ? str_replace('"', '', trim($comparison[1])) : FALSE;
                $b = (trim($comparison[3]) != '') ? str_replace('"', '', trim($comparison[3])) : FALSE;
                $operator = trim($comparison[2]);

                // Check for true/false values and convert them to booleans for better parser comparison
                if($a == 'true' or $a == 'TRUE') {
                    $a = 1;
                }
                elseif($a == "false" or $a == 'FALSE') {
                    $a = 0;
                }
                if($b == 'true' or $b == 'TRUE') {
                    $b = 1;
                }
                elseif ($b == 'false' or $b == 'FALSE') {
                    $b = 0;
                }

                // Then we check if the condition is fullfilled
                switch($operator) {
                    case '>' :
                        $output = ($a > $b) ? $output : '';
                        break;
                    case '>=' :
                        $output = ($a >= $b) ? $output : '';
                        break;
                    case '<>' :
                        $output = ($a <> $b) ? $output : '';
                        break;
                    case '!=' :
                        $output = ($a != $b) ? $output : '';
                        break;
                    case '==' :
                        $output = ($a == $b) ? $output : '';
                        break;
                    case '<=' :
                        $output = ($a <= $b) ? $output : '';
                        break;
                    case '<' :
                        $output = ($a < $b) ? $output : '';
                        break;
                }
                // Then let's check for an {else}
                $else = preg_split("#".$this->l_delim."else".$this->r_delim."#", $conditional[3]);
                // If $output is empty, it means the condition in the above switch was not met, so if an {else} does exist we'll use the second part of the statement. Otherwise the switch condition was met, so if an {else} exists, we'll use the first part of the statement
                if(count($else) > 1) {
                    $output = ($output == '') ? $else[1] : $else[0];
                }

                // At last, we can replace the template code with the output we want to display
                $template = str_replace($conditional[0], $output, $template);
            }
            $template = $this->_parse_conditionals($template);
        }

        // Return the formatted content
        return $template;
    }

    /**
     * Parses switch pseudo-variables contained in the specified template view
     * @param  string
     * @return string
     */
    protected function _parse_switch($template, $preprocess = FALSE) {
        // Some settings
        $currency = '&pound;';

        if($preprocess) {
            // Pre-parsing process : we'll first replace each {switch}...{/switch} pair by a numbered one - {switch(n)}...{/switch(n)} - for correct processing
            $switch_pattern = $this->l_delim.'switch ';
            $endswitch_pattern = $this->l_delim.'\/switch'.$this->r_delim;

            preg_match_all('#'.$switch_pattern.'|'.$endswitch_pattern.'#sU', $template, $preprocess, PREG_SET_ORDER);

            if( ! empty($preprocess)) {
                $count = 0;
                $last_count = array();
                foreach($preprocess as $p) {
                    if($p[0] === $switch_pattern) {
                        ++$count;
                        $last_count[] = $count;
                        $template = preg_replace('#'.$switch_pattern.'#', $this->l_delim.'switch'.$count.' ', $template, 1);
                    }
                    else {
                        $last = array_pop($last_count);
                        $template = preg_replace('#'.$endswitch_pattern.'#', $this->l_delim.'/switch'.$last.$this->r_delim, $template, 1);
                    }
                }
            }
        }

        // First we'll check for SWITCH conditionals
        preg_match_all('#'.$this->l_delim.'switch(\d+) (\w)'.$this->r_delim.'(.+)'.$this->l_delim.'/switch(\1)'.$this->r_delim.'#sU', $template, $conditionals, PREG_SET_ORDER);
        if( ! empty($conditionals)) {
            // And loop through the conditionals we found above
            foreach($conditionals as $conditional) {
                // First we remove the raw code from the template
                $code = $conditional[0];

                // Remove any surrounding quotes as we can ignore them. Also remove any currency characters.
                $statement = str_replace($currency, '', $conditional[2]);

                $output = '';
                // Then we'll extract the cases list we'll loop through
                $sub = $conditional[3];
                preg_match_all('#'.$this->l_delim.'case (\w)'.$this->r_delim.'(.+)'.$this->l_delim.'break'.$this->r_delim.'#sU', $sub, $cases, PREG_SET_ORDER);
                if( ! empty($cases)) {
                    foreach($cases as $case) {
                        // And check - for each one - if the statement match the given value
                        if($statement == $case[1]) {
                            $output = $case[2];
                            break;
                        }
                    }
                }
                // If no output was actually set, then we didn't found any case that match, so we'll check for a default block
                // If no default statement is found, then no output will be set at all (we'll just return an empty string)
                if($output == '') {
                    preg_match('#'.$this->l_delim.'default'.$this->r_delim.'(.+)'.$this->l_delim.'break'.$this->r_delim.'#sU', $sub, $default);
                    if( ! empty($default)) {
                        $output = $default[1];
                    }
                }

                // At last, we can replace the template code with the output we want to display
                $template = str_replace($code, $output, $template);
            }
        }

        return $template;
    }

    /**
     * Parses loops pseudo-variables contained in the specified template view
     * @param  string
     * @return string
     */
    protected function _parse_loops($template, $preprocess = FALSE) {
        // Some settings
        $currency = '&pound;';

        if($preprocess) {
            // Pre-parsing process : we'll first replace each {switch}...{/switch} pair by a numbered one - {switch(n)}...{/switch(n)} - for correct processing
            $for_pattern = $this->l_delim.'for ';
            $endfor_pattern = $this->l_delim.'\/for'.$this->r_delim;

            preg_match_all('#'.$for_pattern.'|'.$endfor_pattern.'#sU', $template, $preprocess, PREG_SET_ORDER);

            if( ! empty($preprocess)) {
                $count = 0;
                $last_count = array();
                foreach($preprocess as $p) {
                    if($p[0] === $for_pattern) {
                        ++$count;
                        $last_count[] = $count;
                        $template = preg_replace('#'.$for_pattern.'#', $this->l_delim.'for'.$count.' ', $template, 1);
                    }
                    else {
                        $last = array_pop($last_count);
                        $template = preg_replace('#'.$endfor_pattern.'#', $this->l_delim.'/for'.$last.$this->r_delim, $template, 1);
                    }
                }
            }
        }

        // First we'll check for FOR structures
        preg_match_all('#'.$this->l_delim.'for(\d+) (\w+) from (\d+) to (\d+) step (\d+)'.$this->r_delim.'(.+?)'.$this->l_delim.'/for(\1)'.$this->r_delim.'#s', $template, $loops, PREG_SET_ORDER);
        if( ! empty($loops) ) {
            // And loop through the conditionals we found above
            foreach ($loops as $loop) {

                $output = '';
                // First we extract the content we want to output inside the loop
                $display = $loop[5];

                // And then we make the actual loop (replacing any increment call inside each line by its value)
                for($i = $loop[2]; $i <= $loop[3]; $i = $i + $loop[4]) {
                    $output .= str_replace($this->l_delim.$loop[1].$this->r_delim, $i, $display);
                }

                // At last, we can replace the template code with the output we want to display
                $template = str_replace($loop[0], $output, $template);
            }
        }

        // Return the formatted content
        return $template;
    }

    /**
     * Parses helpers pseudo-variables (thus calling corresponding helpers) contained in the specified template view
     * @param  string
     * @param  array
     * @return string
     */
    protected function _parse_helpers($template, $data) {
        // First we'll check for any declarations
        preg_match_all('#'.$this->l_delim.'(\w+)(\()(.*)(\))'.$this->r_delim.'#sU', $template, $helpers, PREG_SET_ORDER);

        // And process any call found
        if( ! empty($helpers)) {
            foreach($helpers as $helper) {
                // First we remove the raw code from the template
                $code = $helper[0];
                // Then we catch the actual Helper function to call
                $func = $helper[1];
                // And any argument passed to it
                $args = ( ! empty($helper[3])) ? $this->_parse_helper_args($helper[3]) : array();

                // Last, we have to check if it's a correctly defined Helper function
                if($func === 'empty') {
                    $return = ($args !== "");
                    $template = str_replace($code, $return, $template);
                }
                else if(function_exists($func)) {
                    // We finally try to execute it (and catch any result returned)
                    try {
                        $return = call_user_func_array($func, $args);

                        // At last, we can replace the template code with the output we want to display
                        $template = str_replace($code, $return, $template);
                    } catch (Exception $error) {
                    }
                }
            }
        }

        // Return the formatted content
        return $template;
    }

    /**
     * Parses helper arguments
     * @param  string
     * @return array
     */
    protected function _parse_helper_args($args_string) {
        // First we check if any argument string was actually detected
        if( ! empty($args_string)) {
            // Now we must check if some other Helpers are nested inside
            preg_match_all('#(\w+)(\()(.*)(\))$#sU', $args_string, $helpers, PREG_SET_ORDER);
            if( ! empty($helpers)) {
                // And, again, process any call found
                foreach($helpers as $helper) {
                    // We catch the actual Helper function to call
                    $func = $helper[1];
                    // And any argument passed to it
                    // Here is the recursive point : we'll loop again each time a nested Helper is found in the sub argument string currently parsed
                    $args_string = ( ! empty($helper[3])) ? $this->_parse_helper_args($helper[3]) : array();
                    // Last, we have to check if it's a correctly defined Helper function
                    if(function_exists($func)) {
                        // We finally try to execute it (and catch any result returned)
                        try {
                            $args_string = call_user_func_array($func, $args_string);
                        } catch(Exception $error) {
                        }
                    }
                }
            }
        }
        return ( ! empty($args_string) ? ( ! is_array($args_string) ? explode(',', $args_string) : $args_string) : array());
    }

    /**
     * Parse object tag : {some_class.some_attribute}
     * @param  string
     * @param  object
     * @param  string
     * @return string
     */
    protected function _parse_object($key, $val, $template) {
        $replace = array();
        preg_match_all('#'.preg_quote($this->l_delim).$key.'.'.'(.+?)'.preg_quote($this->r_delim).'#', $template, $matches, PREG_SET_ORDER);

        foreach($matches as $match) {
            $class = $val;
            $explode = explode('.', $match[1]);
            $count = count($explode);
            if($count > 1) {
                $attr = $explode[$count - 1];
                array_pop($explode);
                foreach($explode as $e) {
                    $class = $class->$e;
                }
            }
            else {
                $attr = $match[1];
            }

            if(method_exists($class, $attr)) {
                $replace[$match[0]] = $class->$attr();
            }
            else if(property_exists($class, $attr)) {
                $replace[$match[0]] = $class->$attr;
            }
        }

        return $replace;
    }

    /**
     * Parses tag pairs : {some_array} string... {/some_array}
     *
     * @param  string
     * @param  array
     * @param  string
     * @return string
     */
    protected function _parse_pair($variable, $data, $string) {
        $replace = array();
        preg_match_all('#'.preg_quote($this->l_delim.$variable.$this->r_delim).'(.+?)'.preg_quote($this->l_delim.'/'.$variable.$this->r_delim).'#s', $string, $matches, PREG_SET_ORDER);

        foreach($matches as $match) {
            $str = '';
            foreach($data as $pos => $row) {
                $temp = array();
                foreach($row as $key => $val) {
                    if(is_object($val)) {
                        $pair = $this->_parse_object($key, $val, $match[1]);
                        if( ! empty($pair)) {
                            $temp = array_merge($temp, $pair);
                        }
                        continue;
                    }
                    else if(is_array($val)) {
                        $pair = $this->_parse_pair($key, $val, $match[1]);
                        if( ! empty($pair)) {
                            $temp = array_merge($temp, $pair);
                        }
                        continue;
                    }

                    $temp[$this->l_delim.$key.$this->r_delim] = $val;
                }

                $str .= strtr($match[1], $temp);
                // Custom lookup to parse all indexes - written as {index in [ARRAY]} - tags
                $str = preg_replace('#'.$this->l_delim.'index in '.$variable.$this->r_delim.'#', $pos, $str);
            }

            $replace[$match[0]] = $str;
        }

        return $replace;
    }

    /**
     * [_remove_unparsed description]
     * @param  string
     * @return string
     */
    protected function _remove_unparsed($template) {
        // Pair tags removal
        preg_match_all('#('.$this->l_delim.'(\w+)'.$this->r_delim.'(.+?)'.$this->l_delim.'\/(\2)'.$this->r_delim.')#sU', $template, $unparsed, PREG_SET_ORDER);
        if( ! empty($unparsed)) {
            foreach ($unparsed as $u) {
                $template = str_ireplace($u[0], "", $template);
            }
        }

        // Simple tags removal
        preg_match_all('#'.$this->l_delim.'\w+'.$this->r_delim.'#sU', $template, $unparsed, PREG_SET_ORDER);
        if( ! empty($unparsed)) {
            foreach ($unparsed as $u) {
                if( ! in_array($u[0], array('{else}', '{break}', '{default}'))) {
                    $template = str_ireplace($u[0], "", $template);
                }
            }
        }

        return $template;
    }

}


/* End of file MY_Parser.php */
/* Location: ./application/libraries/MY_Parser.php */
