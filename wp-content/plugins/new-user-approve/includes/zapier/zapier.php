<?php

namespace NewUserApproveZapier;

class Init
{
    private static $_instance;

    /**
     * @version 1.0
     * @since 2.1
     */
    public static function get_instance()
    {
        if ( self::$_instance == null )
            self::$_instance = new self();

        return self::$_instance;
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function __construct()
    {
        $this->require();
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function require()
    {
        require_once plugin_dir_path( __FILE__ ) . '/includes/rest-api.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/user.php';
    }
}

\NewUserApproveZapier\Init::get_instance();