<?php
/**
 * Created by PhpStorm.
 * User: marina
 * Date: 19/11/2018
 * Time: 14:37
 */

/**
 * Handles one file being validated
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mhacker_tc_file {
    protected $tc;
    protected $path;
    protected $errors = null;
    protected $tokens = null;
    protected $tokenscount = 0;
    protected $classes = null;
    protected $functions = null;
    protected $filephpdocs = null;
    protected $allphpdocs = null;
    protected $variables = null;
    protected $defines = null;
    protected $constants = null;

    /**
     * tool_mhacker_tc_file constructor.
     *
     * @param tool_mhacker_test_coverage $tc
     * @param string $path
     */
    public function __construct(tool_mhacker_test_coverage $tc, string $path) {
        $this->tc = $tc;
        $this->path = $path;
    }

    /**
     * Cleares all cached stuff to free memory
     */
    protected function clear_memory() {
        $this->tokens = null;
        $this->tokenscount = 0;
        $this->classes = null;
        $this->functions = null;
        $this->filephpdocs = null;
        $this->allphpdocs = null;
        $this->variables = null;
        $this->defines = null;
        $this->constants = null;
    }

    public function get_full_path() {
        return $this->tc->get_full_path() . $this->path;
    }

    public function remove_check_points($list = null) {
        if ($this->tc->is_file_ignored($this->path)) {
            // TODO should not even be added to the tree.
            return;
        }
        self::remove_check_point_from_path($this->get_full_path(), $list, $list ? null : $this->tc->todo_comment());
    }

    /**
     * Removes the checkpoint(s) calls and, optionally, to-do comments
     *
     * @param string $fullpath
     * @param array $list
     * @param string $todocomment
     */
    public static function remove_check_point_from_path($fullpath, $list, $todocomment = null) {
        if (!file_exists($fullpath) || !is_writable($fullpath)) {
            // Don't even bother.
            return;
        }
        $contents = file_get_contents($fullpath);
        if ($list) {
            $cpregex = '('. join('|', $list) . ')';
        } else {
            $cpregex = '[\d]+';
        }
        $contents = preg_replace('@\\n *\\\\tool_mhacker_test_coverage::cp\\(' . $cpregex .
            ', \\[.*?\\]\\);@', '', $contents);
        if ($todocomment !== null) {
            $contents = preg_replace('/\\n *' . preg_quote($todocomment, '/') . '\\n/', "\n", $contents);
        }
        file_put_contents($fullpath, $contents);
    }

    public function replace_check_points_with_todos() : array {
        $contents = file_get_contents($this->get_full_path());
        $replaced = [];
        if (preg_match_all('@\\n *\\\\tool_mhacker_test_coverage::cp\\(([\d]+), \\[(.*?)\\]\\);\n@', $contents, $matches)) {
            $replaced = [];
            foreach ($matches[0] as $idx => $fullmatch) {
                $cp = $matches[1][$idx];
                $prereq = preg_split('/, /', $matches[2][$idx], -1, PREG_SPLIT_NO_EMPTY);
                if (!array_intersect($replaced, $prereq)) {
                    $replacewith = "\n" . $this->tc->todo_comment() . "\n";
                    $replaced[] = $cp;
                } else {
                    $replacewith = "\n";
                }
                $contents = str_replace($fullmatch, $replacewith, $contents);
            }
            file_put_contents($this->get_full_path(), $contents);
        }
        return $replaced ? ["There are " . count($replaced) . " TODOs in file <b>{$this->path}</b>"] : [];
    }

    protected $checkpoints = [];
    public function add_check_points() {
        if ($this->tc->is_file_ignored($this->path)) {
            // TODO should not even be added to the tree.
            return;
        }
        $this->checkpoints = [];

        $tokens = &$this->get_tokens();
        $scanfile = false;
        if ($aftertoken = $this->find_config_php_inclusion()) {
            //echo $this->path . ' includes config.php at '.$aftertoken.", last token = {$tokens[$aftertoken][1]}<br>";
            $scanfile = true;
        } else if ($aftertoken = $this->find_defined_moodle_internal()) {
            //echo $this->path . ' defines moodle_internal at '.$aftertoken.", last token = {$tokens[$aftertoken][1]}<br>";
        } else {
            \core\notification::add('Skipping file '.$this->path.' - could not find require(config.php) or defined(MOODLE_INTERNAL).');
            return;
        }
        //\core\notification::add('!!Adding checkpoints to the file '.$this->path, \core\output\notification::NOTIFY_INFO);
        $filecp = $this->new_checkpoint($aftertoken, "");
        foreach ($this->get_functions() as $function) {
            $this->add_check_points_to_function($function, $filecp);
        }

        if ($scanfile) {
            $this->add_check_points_to_block($aftertoken, $this->tokenscount - 1, $filecp);
        }

        $s = '';
        for ($tid = 0; $tid < $this->tokenscount; $tid++) {
            $s .= $tokens[$tid][1];
            if (array_key_exists($tid, $this->checkpoints)) {
                foreach ($this->checkpoints[$tid] as $ins) {
                    if (substr($tokens[$tid][1], -1) === "\n") {
                        $s .= "\\tool_mhacker_test_coverage::cp($ins);\n";
                    } else {
                        $s .= "\n\\tool_mhacker_test_coverage::cp($ins);";
                    }
                }
            }
        }
        file_put_contents($this->get_full_path(), $s);
    }

    protected function add_check_points_to_function(stdClass $function, $prereq) {
        if (!$function->tagpair) {
            // Abstract function.
            return;
        }
        if ($this->tc->is_function_ignored($this->path, $function)) {
            // No need to check.
            return;
        }
        $functioncp = $this->new_checkpoint($function->tagpair[0], $prereq);
        $this->add_check_points_to_block($function->tagpair[0] + 1, $function->tagpair[1] - 1,
            "{$prereq}, $functioncp");
    }

    protected function new_checkpoint($aftertoken, $prereq) {
        $cp = $this->tc->get_next_cp();
        $tokens = &$this->get_tokens();
        //if ($tokens[$aftertoken + 1][0] != T_WHITESPACE || strpos($tokens[$aftertoken + 1][1], "\n") === false) {
        if (!$this->is_whitespace_token($aftertoken + 1) || !$this->is_multiline_token($aftertoken + 1)) {
            // There is no newline after this token. Hopefully a comment.
            //print_object($tokens[$aftertoken+1]);
            //$nonspace = $this->next_nonspace_token($aftertoken);
            //print_object($tokens[$aftertoken + 2]);

            if ($tokens[$aftertoken + 2][0] == T_COMMENT &&
                    $this->is_multiline_token($aftertoken + 2)) {
                //\core\notification::add("Skipping inline comment in file {$this->path} ", \core\output\notification::NOTIFY_WARNING);
                $aftertoken = $aftertoken + 2;
            } else {
                \core\notification::add("Error in file {$this->path} ".print_r($tokens[$aftertoken + 3], true));
            }

        }

        $this->checkpoints[$aftertoken][] = "$cp, [{$prereq}]";
        return $cp;
    }

    protected function add_check_points_to_block($tid1, $tid2, $prereq) {
        $tokens = &$this->get_tokens();
        for ($tid = $tid1; $tid <= $tid2; $tid++) {
            if ($tokens[$tid][1] === '{') {
                if ($tokens[$tid-1][0] == T_OBJECT_OPERATOR) {
                    continue;
                } else if ($this->is_switch($tid)) {
                    $tids = $this->find_cases_in_switch($tid);
                    foreach ($tids as $tidx) {
                        $this->new_checkpoint($tidx, $prereq);
                    }
                } else {
                    $this->new_checkpoint($tid, $prereq);
                }
            }
        }
    }

    /**
     * Checks if token '{' is the beginning of a switch.
     * @param int $tid
     */
    protected function find_cases_in_switch($tid1) {
        $tokens = &$this->get_tokens();
        if ($tokens[$tid1][1] !== '{') {
            return false;
        }
        if (!$tagpair = $this->find_tag_pair($tid1, '{', '}')) {
            return false;
        }
        $tid2 = $tagpair[1];
        $waitforcolon = false;
        $rv = [];
        for ($tid = $tid1 + 1; $tid < $tid2; $tid++) {
            if ($waitforcolon && $tokens[$tid][1] === ':') {
                $rv[] = $tid;
                $waitforcolon = false;
            } else if ($tokens[$tid][0] == T_CASE || $tokens[$tid][0] == T_DEFAULT) {
                $waitforcolon = true;
            } else if ($tokens[$tid][1] === '{') {
                if (!$tagpair = $this->find_tag_pair($tid, '{', '}')) {
                    return $rv;
                }
                $tid = $tagpair[1];
            } else if ($tokens[$tid][1] === '(') {
                if (!$tagpair = $this->find_tag_pair($tid, '(', ')')) {
                    return $rv;
                }
                $tid = $tagpair[1];
            }
        }
        return $rv ?: false;
    }

    protected function is_switch($tid) {
        return $this->find_cases_in_switch($tid) !== false;
    }

    protected function find_config_php_inclusion() {
        $tokens = &$this->get_tokens();
        for ($tid = 0; $tid < $this->tokenscount; $tid++) {
            if (in_array($tokens[$tid][0], array(T_REQUIRE, T_REQUIRE_ONCE))) {
                //echo $this->get_filepath()." : ".print_r($tokens[$tid], true).print_r($tokens[$tid+2], true)."<br>";
                $r = new stdClass();
                $r->tid = $tid;
                $r->boundaries = $b = $this->find_object_boundaries($r);
                $tt = array_slice($tokens, $b[0] + 2, $b[1] - $b[0] - 3);
                $in = '';
                foreach ($tt as $t) {
                    if ($t[0] == T_CONSTANT_ENCAPSED_STRING) {
                        if (preg_match('/config\.php[\'\"]$/', $t[1])) {
                            return $b[1];
                        }
                    }
                }
            }
        }
        return false;
    }

    protected function find_namespace_definition() {
        $tokens = &$this->get_tokens();
        for ($tid = 0; $tid < $this->tokenscount; $tid++) {
            if ($tokens[$tid][0] == T_NAMESPACE) {
                $r = (object)['tid' => $tid];
                $b = $this->find_object_boundaries($r);
                return $b[1];
            }
        }
        return false;
    }

    /**
     * Returns a file contents converted to array of tokens.
     *
     * Each token is an array with two elements: code of token and text
     * For simple 1-character tokens the code is -1
     *
     * @return array
     */
    public function &get_tokens() {
        if ($this->tokens === null) {
            $source = file_get_contents($this->get_full_path());
            $this->tokens = token_get_all($source);
            $this->tokenscount = count($this->tokens);
            $inquotes = -1;
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if (is_string($this->tokens[$tid])) {
                    // Simple 1-character token.
                    $this->tokens[$tid] = array(-1, $this->tokens[$tid]);
                }
                // And now, for the purpose of this project we don't need strings with variables inside to be parsed
                // so when we find string in double quotes that is split into several tokens and combine all content in one token.
                if ($this->tokens[$tid][0] == -1 && $this->tokens[$tid][1] == '"') {
                    if ($inquotes == -1) {
                        $inquotes = $tid;
                        $this->tokens[$tid][0] = T_STRING;
                    } else {
                        $this->tokens[$inquotes][1] .= $this->tokens[$tid][1];
                        $this->tokens[$tid] = array(T_WHITESPACE, '');
                        $inquotes = -1;
                    }
                } else if ($inquotes > -1) {
                    $this->tokens[$inquotes][1] .= $this->tokens[$tid][1];
                    $this->tokens[$tid] = array(T_WHITESPACE, '');
                }
            }
        }
        return $this->tokens;
    }

    /**
     * Returns all classes found in file
     *
     * Returns array of objects where each element represents a class:
     * $class->name : name of the class
     * $class->tagpair : array of two elements: id of token { for the class and id of token } (false if not found)
     * $class->phpdocs : phpdocs for this class (instance of local_moodlecheck_phpdocs or false if not found)
     * $class->boundaries : array with ids of first and last token for this class
     *
     * @return array
     */
    public function &get_classes() {
        if ($this->classes === null) {
            $this->classes = array();
            $tokens = &$this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if (($this->tokens[$tid][0] == T_CLASS) && ($this->previous_nonspace_token($tid) !== "::")) {
                    $class = new stdClass();
                    $class->tid = $tid;
                    $class->name = $this->next_nonspace_token($tid);
                    $class->phpdocs = $this->find_preceeding_phpdoc($tid);
                    $class->tagpair = $this->find_tag_pair($tid, '{', '}');
                    $class->boundaries = $this->find_object_boundaries($class);
                    $this->classes[] = $class;
                }
            }
        }
        return $this->classes;
    }

    /**
     * Returns all functions (including class methods) found in file
     *
     * Returns array of objects where each element represents a function:
     * $function->tid : token id of the token 'function'
     * $function->name : name of the function
     * $function->phpdocs : phpdocs for this function (instance of local_moodlecheck_phpdocs or false if not found)
     * $function->class : containing class object (false if this is not a class method)
     * $function->fullname : name of the function with class name (if applicable)
     * $function->accessmodifiers : tokens like static, public, protected, abstract, etc.
     * $function->tagpair : array of two elements: id of token { for the function and id of token } (false if not found)
     * $function->argumentstoken : array of tokens found inside function arguments
     * $function->arguments : array of function arguments where each element is array(typename, variablename)
     * $function->boundaries : array with ids of first and last token for this function
     *
     * @return array
     */
    public function &get_functions() {
        if ($this->functions === null) {
            $this->functions = array();
            $tokens = &$this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if ($this->tokens[$tid][0] == T_FUNCTION) {
                    $function = new stdClass();
                    $function->tid = $tid;
                    $function->fullname = $function->name = $this->next_nonspace_token($tid, false, array('&'));

                    // Skip anonymous functions.
                    if ($function->name == '(') {
                        continue;
                    }
                    $function->phpdocs = $this->find_preceeding_phpdoc($tid);
                    $function->class = $this->is_inside_class($tid);
                    if ($function->class !== false) {
                        $function->fullname = $function->class->name . '::' . $function->name;
                    }
                    $function->accessmodifiers = $this->find_access_modifiers($tid);
                    if (!in_array(T_ABSTRACT, $function->accessmodifiers)) {
                        $function->tagpair = $this->find_tag_pair($tid, '{', '}');
                    } else {
                        $function->tagpair = false;
                    }
                    $argumentspair = $this->find_tag_pair($tid, '(', ')', array('{', ';'));
                    if ($argumentspair !== false && $argumentspair[1] - $argumentspair[0] > 1) {
                        $function->argumentstokens = $this->break_tokens_by(
                            array_slice($tokens, $argumentspair[0] + 1, $argumentspair[1] - $argumentspair[0] - 1) );
                    } else {
                        $function->argumentstokens = array();
                    }
                    $function->arguments = array();
                    foreach ($function->argumentstokens as $argtokens) {
                        $type = null;
                        $variable = null;
                        for ($j = 0; $j < count($argtokens); $j++) {
                            if ($argtokens[$j][0] == T_VARIABLE) {
                                $variable = $argtokens[$j][1];
                                break;
                            } else if ($argtokens[$j][0] != T_WHITESPACE && $argtokens[$j][1] != '&') {
                                $type = $argtokens[$j][1];
                            }
                        }
                        $function->arguments[] = array($type, $variable);
                    }
                    $function->boundaries = $this->find_object_boundaries($function);
                    $this->functions[] = $function;
                }
            }
        }
        return $this->functions;
    }

    /**
     * Returns all class properties (variables) found in file
     *
     * Returns array of objects where each element represents a variable:
     * $variable->tid : token id of the token with variable name
     * $variable->name : name of the variable (starts with $)
     * $variable->phpdocs : phpdocs for this variable (instance of local_moodlecheck_phpdocs or false if not found)
     * $variable->class : containing class object
     * $variable->fullname : name of the variable with class name (i.e. classname::$varname)
     * $variable->accessmodifiers : tokens like static, public, protected, abstract, etc.
     * $variable->boundaries : array with ids of first and last token for this variable
     *
     * @return array
     */
    public function &get_variables() {
        if ($this->variables === null) {
            $this->variables = array();
            $this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if ($this->tokens[$tid][0] == T_VARIABLE && ($class = $this->is_inside_class($tid)) &&
                    !$this->is_inside_function($tid)) {
                    $variable = new stdClass;
                    $variable->tid = $tid;
                    $variable->name = $this->tokens[$tid][1];
                    $variable->class = $class;
                    $variable->fullname = $class->name . '::' . $variable->name;
                    $variable->accessmodifiers = $this->find_access_modifiers($tid);
                    $variable->phpdocs = $this->find_preceeding_phpdoc($tid);
                    $variable->boundaries = $this->find_object_boundaries($variable);
                    $this->variables[] = $variable;
                }
            }
        }
        return $this->variables;
    }

    /**
     * Returns all constants found in file
     *
     * Returns array of objects where each element represents a constant:
     * $variable->tid : token id of the token with variable name
     * $variable->name : name of the variable (starts with $)
     * $variable->phpdocs : phpdocs for this variable (instance of local_moodlecheck_phpdocs or false if not found)
     * $variable->class : containing class object
     * $variable->fullname : name of the variable with class name (i.e. classname::$varname)
     * $variable->boundaries : array with ids of first and last token for this constant
     *
     * @return array
     */
    public function &get_constants() {
        if ($this->constants === null) {
            $this->constants = array();
            $this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if ($this->tokens[$tid][0] == T_CONST && !$this->is_inside_function($tid)) {
                    $variable = new stdClass;
                    $variable->tid = $tid;
                    $variable->fullname = $variable->name = $this->next_nonspace_token($tid, false);
                    $variable->class = $this->is_inside_class($tid);
                    if ($variable->class !== false) {
                        $variable->fullname = $variable->class->name . '::' . $variable->name;
                    }
                    $variable->phpdocs = $this->find_preceeding_phpdoc($tid);
                    $variable->boundaries = $this->find_object_boundaries($variable);
                    $this->constants[] = $variable;
                }
            }
        }
        return $this->constants;
    }

    /**
     * Returns all 'define' statements found in file
     *
     * Returns array of objects where each element represents a define statement:
     * $variable->tid : token id of the token with variable name
     * $variable->name : name of the variable (starts with $)
     * $variable->phpdocs : phpdocs for this variable (instance of local_moodlecheck_phpdocs or false if not found)
     * $variable->class : containing class object
     * $variable->fullname : name of the variable with class name (i.e. classname::$varname)
     * $variable->boundaries : array with ids of first and last token for this constant
     *
     * @return array|false
     */
    public function find_defined_moodle_internal() {
        $this->get_tokens();
        for ($tid = 0; $tid < $this->tokenscount; $tid++) {
            if ($this->tokens[$tid][1] == 'defined') {
                $next1id = $this->next_nonspace_token($tid, true);
                $next1 = $this->next_nonspace_token($tid, false);
                $next2 = $this->next_nonspace_token($next1id, false);
                $variable = new stdClass;
                $variable->tid = $tid;
                if ($next1 == '(' && preg_match("/^(['\"])(.*)\\1$/", $next2, $matches)) {
                    $variable->fullname = $variable->name = $matches[2];
                }
                $variable->phpdocs = $this->find_preceeding_phpdoc($tid);
                $variable->boundaries = $this->find_object_boundaries($variable);
                if ($matches[2] == 'MOODLE_INTERNAL') {
                    return $variable->boundaries[1];
                }
            }
        }
        return false;
    }

    /**
     * Finds and returns object boundaries
     *
     * $obj is an object representing function, class or variable. This function
     * returns token ids for the very first token applicable to this object
     * to the very last
     *
     * @param stdClass $obj
     * @return array
     */
    public function find_object_boundaries($obj) {
        $boundaries = array($obj->tid, $obj->tid);
        $tokens = &$this->get_tokens();
        if (!empty($obj->tagpair)) {
            $boundaries[1] = $obj->tagpair[1];
        } else {
            // Find the next ; char.
            for ($i = $boundaries[1]; $i < $this->tokenscount; $i++) {
                if ($tokens[$i][1] == ';') {
                    $boundaries[1] = $i;
                    break;
                }
            }
        }
        if (isset($obj->phpdocs) && $obj->phpdocs instanceof local_moodlecheck_phpdocs) {
            $boundaries[0] = $obj->phpdocs->get_original_token_id();
        } else {
            // Walk back until we meet one of the characters that means that we are outside of the object.
            for ($i = $boundaries[0] - 1; $i >= 0; $i--) {
                $token = $tokens[$i];
                if (in_array($token[0], array(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG))) {
                    break;
                } else if (in_array($token[1], array('{', '}', '(', ';', ',', '['))) {
                    break;
                }
            }
            // Walk forward to the next meaningful token skipping all spaces and comments.
            for ($i = $i + 1; $i < $boundaries[0]; $i++) {
                if (!in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) {
                    break;
                }
            }
            $boundaries[0] = $i;
        }
        return $boundaries;
    }

    /**
     * Checks if the token with id $tid in inside some class
     *
     * @param int $tid
     * @return stdClass|false containing class or false if this is not a member
     */
    public function is_inside_class($tid) {
        $classes = &$this->get_classes();
        $classescnt = count($classes);
        for ($clid = 0; $clid < $classescnt; $clid++) {
            if ($classes[$clid]->boundaries[0] <= $tid && $classes[$clid]->boundaries[1] >= $tid) {
                return $classes[$clid];
            }
        }
        return false;
    }

    /**
     * Checks if the token with id $tid in inside some function or class method
     *
     * @param int $tid
     * @return stdClass|false containing function or false if this is not inside a function
     */
    public function is_inside_function($tid) {
        $functions = &$this->get_functions();
        $functionscnt = count($functions);
        for ($fid = 0; $fid < $functionscnt; $fid++) {
            if ($functions[$fid]->boundaries[0] <= $tid && $functions[$fid]->boundaries[1] >= $tid) {
                return $functions[$fid];
            }
        }
        return false;
    }

    /**
     * Checks if token with id $tid is a whitespace
     *
     * @param int $tid
     * @return boolean
     */
    public function is_whitespace_token($tid) {
        $this->get_tokens();
        return ($this->tokens[$tid][0] == T_WHITESPACE);
    }

    /**
     * Returns how many line feeds are in this token
     *
     * @param int $tid
     * @return int
     */
    public function is_multiline_token($tid) {
        $this->get_tokens();
        return substr_count($this->tokens[$tid][1], "\n");
    }

    /**
     * Returns the first token which is not whitespace following the token with id $tid
     *
     * Also returns false if no meaningful token found till the end of file
     *
     * @param int $tid
     * @param bool $returnid
     * @param array $alsoignore
     * @return int|false
     */
    public function next_nonspace_token($tid, $returnid = false, $alsoignore = array()) {
        $this->get_tokens();
        for ($i = $tid + 1; $i < $this->tokenscount; $i++) {
            if (!$this->is_whitespace_token($i) && !in_array($this->tokens[$i][1], $alsoignore)) {
                if ($returnid) {
                    return $i;
                } else {
                    return $this->tokens[$i][1];
                }
            }
        }
        return false;
    }

    /**
     * Returns the first token which is not whitespace before the token with id $tid
     *
     * Also returns false if no meaningful token found till the beggining of file
     *
     * @param int $tid
     * @param bool $returnid
     * @param array $alsoignore
     * @return int|false
     */
    public function previous_nonspace_token($tid, $returnid = false, $alsoignore = array()) {
        $this->get_tokens();
        for ($i = $tid - 1; $i > 0; $i--) {
            if (!$this->is_whitespace_token($i) && !in_array($this->tokens[$i][1], $alsoignore)) {
                if ($returnid) {
                    return $i;
                } else {
                    return $this->tokens[$i][1];
                }
            }
        }
        return false;
    }

    /**
     * Returns all modifiers (private, public, static, ...) preceeding token with id $tid
     *
     * @param int $tid
     * @return array
     */
    public function find_access_modifiers($tid) {
        $tokens = &$this->get_tokens();
        $modifiers = array();
        for ($i = $tid - 1; $i >= 0; $i--) {
            if ($this->is_whitespace_token($i)) {
                // Skip.
                continue;
            } else if (in_array($tokens[$i][0],
                array(T_ABSTRACT, T_PRIVATE, T_PUBLIC, T_PROTECTED, T_STATIC, T_VAR, T_FINAL, T_CONST))) {
                $modifiers[] = $tokens[$i][0];
            } else {
                break;
            }
        }
        return $modifiers;
    }

    /**
     * Finds phpdocs preceeding the token with id $tid
     *
     * skips words abstract, private, public, protected and non-multiline whitespaces
     *
     * @param int $tid
     * @return local_moodlecheck_phpdocs|false
     */
    public function find_preceeding_phpdoc($tid) {
        $tokens = &$this->get_tokens();
        $modifiers = $this->find_access_modifiers($tid);
        for ($i = $tid - 1; $i >= 0; $i--) {
            if ($this->is_whitespace_token($i)) {
                if ($this->is_multiline_token($i) > 1) {
                    // More that one line feed means that no phpdocs for this element exists.
                    return false;
                }
            } else if ($tokens[$i][0] == T_DOC_COMMENT) {
                return $this->get_phpdocs($i);
            } else if (in_array($tokens[$i][0], $modifiers)) {
                // Just skip.
                continue;
            } else if (in_array($tokens[$i][1], array('{', '}', ';'))) {
                // This means that no phpdocs exists.
                return false;
            } else if ($tokens[$i][0] == T_COMMENT) {
                // This probably needed to be doc_comment.
                return false;
            } else {
                // No idea what it is!
                // TODO: change to debugging
                // echo "************ Unknown preceeding token id = {$tokens[$i][0]}, text = '{$tokens[$i][1]}' **************<br>".
                return false;
            }
        }
        return false;
    }

    /**
     * Finds the next pair of matching open and close symbols (usually some sort of brackets)
     *
     * @param int $startid id of token where we start looking from
     * @param string $opensymbol opening symbol (, { or [
     * @param string $closesymbol closing symbol ), } or ] respectively
     * @param array $breakifmeet array of symbols that are not allowed not preceed the $opensymbol
     * @return array|false array of ids of two corresponding tokens or false if not found
     */
    public function find_tag_pair($startid, $opensymbol, $closesymbol, $breakifmeet = array()) {
        $openid = false;
        $counter = 0;
        // Also break if we find closesymbol before opensymbol.
        $breakifmeet[] = $closesymbol;
        for ($i = $startid; $i < $this->tokenscount; $i++) {
            if ($openid === false && in_array($this->tokens[$i][1], $breakifmeet)) {
                return false;
            } else if ($openid !== false && $this->tokens[$i][1] == $closesymbol) {
                $counter--;
                if ($counter == 0) {
                    return array($openid, $i);
                }
            } else if ($this->tokens[$i][1] == $opensymbol) {
                if ($openid === false) {
                    $openid = $i;
                }
                $counter++;
            }
        }
        return false;
    }

    /**
     * Finds the next pair of matching open and close symbols (usually some sort of brackets)
     *
     * @param array $tokens array of tokens to parse
     * @param int $startid id of token where we start looking from
     * @param string $opensymbol opening symbol (, { or [
     * @param string $closesymbol closing symbol ), } or ] respectively
     * @param array $breakifmeet array of symbols that are not allowed not preceed the $opensymbol
     * @return array|false array of ids of two corresponding tokens or false if not found
     */
    public function find_tag_pair_inlist(&$tokens, $startid, $opensymbol, $closesymbol, $breakifmeet = array()) {
        $openid = false;
        $counter = 0;
        // Also break if we find closesymbol before opensymbol.
        $breakifmeet[] = $closesymbol;
        $tokenscount = count($tokens);
        for ($i = $startid; $i < $tokenscount; $i++) {
            if ($openid === false && in_array($tokens[$i][1], $breakifmeet)) {
                return false;
            } else if ($openid !== false && $tokens[$i][1] == $closesymbol) {
                $counter--;
                if ($counter == 0) {
                    return array($openid, $i);
                }
            } else if ($tokens[$i][1] == $opensymbol) {
                if ($openid === false) {
                    $openid = $i;
                }
                $counter++;
            }
        }
        return false;
    }

    /**
     * Locates the file-level phpdocs and returns it
     *
     * @return string|false either the contents of phpdocs or false if not found
     */
    public function find_file_phpdocs() {
        $tokens = &$this->get_tokens();
        if ($this->filephpdocs === null) {
            $found = false;
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                if (in_array($tokens[$tid][0], array(T_OPEN_TAG, T_WHITESPACE, T_COMMENT))) {
                    // All allowed before the file-level phpdocs.
                    $found = false;
                } else if ($tokens[$tid][0] == T_DOC_COMMENT) {
                    $found = $tid;
                    break;
                } else {
                    // Found something else.
                    break;
                }
            }
            if ($found !== false) {
                // Now let's check that this is not phpdocs to the next function or class or define.
                $nexttokenid = $this->next_nonspace_token($tid, true);
                if ($nexttokenid !== false) { // Still tokens to look.
                    $nexttoken = $this->tokens[$nexttokenid];
                    if ($this->is_whitespace_token($tid + 1) && $this->is_multiline_token($tid + 1) > 1) {
                        // At least one empty line follows, it's all right.
                        $found = $tid;
                    } else if (in_array($nexttoken[0],
                        array(T_DOC_COMMENT, T_COMMENT, T_REQUIRE_ONCE, T_REQUIRE, T_IF, T_INCLUDE_ONCE, T_INCLUDE))) {
                        // Something non-documentable following, ok.
                        $found = $tid;
                    } else if ($nexttoken[0] == T_STRING && $nexttoken[1] == 'defined') {
                        // Something non-documentable following.
                        $found = $tid;
                    } else if (in_array($nexttoken[0], array(T_CLASS, T_ABSTRACT, T_INTERFACE, T_FUNCTION))) {
                        // This is the doc comment to the following class/function.
                        $found = false;
                    }
                    // } else {
                    // TODO: change to debugging.
                    // echo "************ Unknown token following the first phpdocs in {$this->filepath}: id = {$nexttoken[0]}, text = '{$nexttoken[1]}' **************<br>".
                    // }
                }
            }
            $this->filephpdocs = $this->get_phpdocs($found);
        }
        return $this->filephpdocs;
    }

    /**
     * Returns all parsed phpdocs block found in file
     *
     * @return array
     */
    public function &get_all_phpdocs() {
        if ($this->allphpdocs === null) {
            $this->allphpdocs = array();
            $this->get_tokens();
            for ($id = 0; $id < $this->tokenscount; $id++) {
                if (($this->tokens[$id][0] == T_DOC_COMMENT || $this->tokens[$id][0] === T_COMMENT)) {
                    $this->allphpdocs[$id] = [];//new local_moodlecheck_phpdocs($this->tokens[$id], $id);
                }
            }
        }
        return $this->allphpdocs;
    }

    /**
     * Returns one parsed phpdocs block found in file
     *
     * @param int $tid token id of phpdocs
     * @return local_moodlecheck_phpdocs
     */
    public function get_phpdocs($tid) {
        if ($tid === false) {
            return false;
        }
        $this->get_all_phpdocs();
        if (isset($this->allphpdocs[$tid])) {
            return $this->allphpdocs[$tid];
        } else {
            return false;
        }
    }

    /**
     * Given an array of tokens breaks them into chunks by $separator
     *
     * @param array $tokens
     * @param string $separator one-character separator (usually comma)
     * @return array of arrays of tokens
     */
    public function break_tokens_by($tokens, $separator = ',') {
        $rv = array();
        if (!count($tokens)) {
            return $rv;
        }
        $rv[] = array();
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][1] == $separator) {
                $rv[] = array();
            } else {
                $nextpair = false;
                if ($tokens[$i][1] == '(') {
                    $nextpair = $this->find_tag_pair_inlist($tokens, $i, '(', ')');
                } else if ($tokens[$i][1] == '[') {
                    $nextpair = $this->find_tag_pair_inlist($tokens, $i, '[', ']');
                } else if ($tokens[$i][1] == '{') {
                    $nextpair = $this->find_tag_pair_inlist($tokens, $i, '{', '}');
                }
                if ($nextpair !== false) {
                    // Skip to the end of the tag pair.
                    for ($j = $i; $j <= $nextpair[1]; $j++) {
                        $rv[count($rv) - 1][] = $tokens[$j];
                    }
                    $i = $nextpair[1];
                } else {
                    $rv[count($rv) - 1][] = $tokens[$i];
                }
            }
        }
        // Now trim whitespaces.
        for ($i = 0; $i < count($rv); $i++) {
            if (count($rv[$i]) && $rv[$i][0][0] == T_WHITESPACE) {
                array_shift($rv[$i]);
            }
            if (count($rv[$i]) && $rv[$i][count($rv[$i]) - 1][0] == T_WHITESPACE) {
                array_pop($rv[$i]);
            }
        }
        return $rv;
    }

    /**
     * Returns line number for the token with specified id
     *
     * @param int $tid id of the token
     */
    public function get_line_number($tid) {
        $tokens = &$this->get_tokens();
        if (count($tokens[$tid]) > 2) {
            return $tokens[$tid][2];
        } else if ($tid == 0) {
            return 1;
        } else {
            return $this->get_line_number($tid - 1) + count(preg_split('/\n/', $tokens[$tid - 1][1])) - 1;
        }
    }
}
