<?php

class Timer {

    /**
     * Holds all timers.
     * @var array
     */
    public static $timers = array();

    /**
     * Handles the creation of a new timer. Simply supply a name and an
     * optional description.
     *
     * @access  public static
     * @param   string  $name
     * @return  void
     */
    public static function start($name, $description = NULL)
    {
        static::$timers[$name] = array(
            'description' => $description,
            'start' => microtime(true),
            'end' => NULL,
            'time' => NULL,
            'checkpoints' => array()
        );
    }

    /**
     * Handles the stopping an existing timer. Simply supply a name and an
     * optional number of decimal places. Will return the finalized timer values.
     *
     * @access  public static
     * @param   string  $name
     * @param   int     $decimals
     * @return  array
     */
    public static function stop($name, $decimals = 5)
    {
        // early calculation of stop time
        $end = microtime(true);

        // calculate elapsed time
        static::$timers[$name] = static::get($name);
        static::$timers[$name]['end'] = $end;
        static::$timers[$name]['time'] =
            number_format(
                ($end - static::$timers[$name]['start']) * 1000,
                $decimals
            );

        return static::$timers[$name];
    }

    /**
     * A special timer endpoint which will allow for the creation of several
     * checkpoints based on a singular start timer. To use, specify a start
     * timer name, a unique checkpoint name, an optional checkpoint description
     * describing the timer purpose, and an optional number of decimal places
     * to use for calculating the number of seconds since start time.
     *
     * @access  public
     * @param   string  $name               The start timer name
     * @param   string  $checkpointName     The unique name of the checkpoint
     * @param   mixed   $description        An optional description of the checkpoint
     * @param   int     $decimals           The number of decimal places to include
     */
    public static function checkpoint(
        $name,
        $description = NULL,
        $decimals = 5
    )
    {
        // early calculation of stop time
        $end = microtime(true);

        static::$timers[$name] = static::get($name);

        $count = count(static::$timers[$name]['checkpoints']);

        static::$timers[$name]['checkpoints'][$count] = array(
            'description' => $description,
            'end' => $end,
        );

        // calculate elapsed time
        static::$timers[$name]['checkpoints'][$count]['timeFromStart'] =
            number_format(
                $end - static::$timers[$name]['start'],
                $decimals
            );

        if ($count > 0) {
            static::$timers[$name]['checkpoints'][$count]['timeFromLastCheckpoint'] =
                number_format(
                    $end - static::$timers[$name]['checkpoints'][$count - 1]['end'],
                    $decimals
                );
        }
    }

    /**
     * Helper to retrieve a timer. If none exists, we assume that the timer start
     * time is equivalent to LARAVEL_START.
     *
     * @access  public
     * @param   string  $name
     * @return  array
     */
    public static function get($name)
    {
        if (!empty(static::$timers[$name])) {
            return static::$timers[$name];
        }

        return array(
            'description' => 'Timer since LARAVEL_START.',
            'start' => defined('LARAVEL_START') ? LARAVEL_START : microtime(true),
            'end' => NULL,
            'time' => NULL,
            'checkpoints' => array()
        );
    }

    /**
     * A quick method for returning all existing timers in the event you have
     * a problem/error/exception and need to do something with your timers.
     *
     * @access  public
     * @param   bool    $toFile
     * @return  array
     */
    public static function dump($toFile = false)
    {
        // early calculation of stop time
        $end = microtime(true);

        // ensure we end all timers
        foreach (static::$timers as $name => $timer) {
            if (static::$timers[$name]['end'] == NULL) {
                static::$timers[$name]['end'] = $end;
                static::$timers[$name]['time'] =
                    number_format(
                        $end - static::$timers[$name]['start'],
                        5
                    );
            }
        }

        // if logging to file
        if ($toFile) {
            static::write();
        }

        return static::$timers;
    }

    /**
     * Attempts to pretty print the timer data to a file for later parsing.
     *
     * @access  public
     * @param   string  $dir
     * @return  void
     */
    public static function write()
    {
        $json           = static::dump();
        $json           = json_encode($json);
        $result         = '';
        $pos            = 0;
        $strLen         = strlen($json);
        $indentStr      = "\t";
        $newLine        = "\n";
        $prevChar       = '';
        $outOfQuotes    = true;

        for ($i = 0; $i <= $strLen; $i++) {
            // grab the next character in the string
            $char = substr($json, $i, 1);

            // are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

            // if this character is the end of an element,
            // output a new line and indent the next line.
            } else if(($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // add the character to the result string.
            $result .= $char;

            // if the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        File::append(path('storage') . 'logs/timer_' . date('Y-m-d_His').'.log', $result);
    }

    /**
     * Clears out all existing timers. Consider this a reset.
     *
     * @access  public
     * @return  void
     */
    public static function clear()
    {
        static::$timers = array();
    }
}
