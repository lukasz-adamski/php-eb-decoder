<?php

/**
 * @author Adams <lukasz.adamski@eterprime.eu>
 * @license MIT
 */
 
if (! function_exists('decode_pseudo_encoding')) {
    /**
     * Function read contents from given file and emulate php
     * with some modifications to get encoded source as output.
     *
     * @param $filename
     * @return string|false
     * @throws \Exception When failed to start PHP emulation process.
     */
    function decode_pseudo_encoding($filename)
    {
        $code = @file_get_contents($filename);
        
        if (! is_string($code))
            return false;
        
        $identifier = rand(1000000, 9999999);
        $magic = md5('adams:' . $identifier);
        
        $preamble = "
        <?php
        if (!defined('__preamble_{$identifier}')) {
            define('__preamble_{$identifier}', 1);
            
            function __run__(\$code)
            {
                global \$_sourceCode;
                \$_sourceCode .= \$code;
            }
            
            function __shutdown__()
            {
                global \$_sourceCode;
                
                \$pre = '';
                
                foreach (\$GLOBALS as \$key => \$value)
                {
                    if (in_array(strtolower(\$key), [
                            'globals', '_server',
                            'argv', 'argc', '_sourcecode'
                        ]))
                        continue;
                    
                    \$pre .= '\$' . \$key . ' = ' . var_export(\$value, true) . ';' . PHP_EOL;
                }
                
                echo \$pre 
                    . PHP_EOL . '// {$magic}' 
                    . PHP_EOL . \$_sourceCode;
            }
            
            register_shutdown_function('__shutdown__');
        }
        ?>
        ";
        
        $descriptors = [
            ['pipe', 'r'],
            ['pipe', 'w']
        ];
        
        while (strpos($code, 'eval'))
        {
            $code = str_replace('eval', '__run__', $code);
            $code = $preamble . $code;
            
            $emulator = proc_open(PHP_BINARY, $descriptors, $pipes, dirname($filename));
            
            if (! is_resource($emulator))
                throw new \Exception('Failed to emulate PHP CLI');
            
            fwrite($pipes[0], $code);
            fclose($pipes[0]);
            
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            
            $code = '<?php ' . $output;
            
            if (0 !== proc_close($emulator))
                break;
        }
        
        $code = explode($magic, $code, 2)[1];
        return trim($code);
    }
}

if (PHP_SAPI == 'cli') {
    if ($argc != 2)
        die('Usage: php ' . $argv[0] . ' <filename>' . PHP_EOL);
    
    list($_, $filename) = $argv;
    
    print decode_pseudo_encoding($filename);
}
