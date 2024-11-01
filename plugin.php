<?php
/*
Plugin Name: Website Backup Bot
Description: Integration with Website Backup Bot
Author: WebsiteBackupBot
Version: 0.1
*/



require_once('vendor/autoload.php');
require_once('vendor/Twig.php');

class WP_WebsiteBackupBot_Plugin {

    var $errors = array();

    public $TEMPLATE_DATA = array();
    private $THEME_DIR = "templates";
    private $CACHE_PATH = "cache";

    private $VENDOR_SITE_URL = "https://app.websitebackupbot.com";
    private $VENDOR_ROUTES = array(
        "HEARTBEAT" => "rest/heartbeat",
        "REGISTER" => "rest/register",
        "UPDATE_DATA" => "rest/update_data"

    );

    public function __construct() {

        $this->twig = new WebsiteBackupBot_Twig();

        $this->twig->setTemplateDirectory(realpath(dirname(__FILE__) . "/" . $this->THEME_DIR));


        //WBB Ajax Functions
        //Add admin page to the menu
        add_action('admin_menu', array($this, 'wbb_add_admin_page'));
        add_action('wp_ajax_wbb_backup', array($this, 'wbb_backup_ajax_request'));
        add_action('wp_ajax_wbb_backup_delete', array($this, 'wbb_backup_ajax_delete'));
        add_action('wp_ajax_wbb_register', array($this, 'wbb_register_ajax_request'));
        add_action('wp_ajax_wbb_update_settings', array($this, 'wbb_update_settings'));
        add_action('wp_ajax_wbb_heartbeat', array($this, 'wbb_heartbeat_ajax_request'));
        add_action('wp_ajax_wbb_update_data', array($this, 'wbb_update_data_ajax_request'));
        add_action('wp_ajax_wbb_save_code', array($this, 'wbb_save_code_ajax_request'));
        add_action('wp_ajax_wbb_update_local', array($this, 'wbb_update_local_ajax_request'));
        add_action('wp_ajax_wbb_check_data', array($this, 'wbb_check_code_ajax_request'));

        add_action('init', array($this, 'wbb_request_backup'), -1);
        add_action('init', array($this, 'wbb_check_code'), -1);

        add_action('admin_enqueue_scripts', array($this,'add_that_script' ),-1);
        // add_action('admin_enqueue_scripts','add_my_stylesheet');

        register_activation_hook(__FILE__, array($this, 'wbb_custom_plugin_tables'));


        $this->TEMPLATE_DATA['VENDOR_SITE_URL'] = $this->VENDOR_SITE_URL;
//        function add_my_stylesheet()
//        {
//            wp_enqueue_style( 'mdb.min', plugins_url( '/css/mdb.min.css', __FILE__ ) );
//            wp_enqueue_style( 'new-style', plugins_url( '/css/new-style.css', __FILE__ ) );
//            wp_enqueue_style( 'sweetalert2', plugins_url( '/css/sweetalert2.css', __FILE__ ) );
//            wp_enqueue_style( 'sweetalert2.min', plugins_url( '/css/sweetalert2.min.css', __FILE__ ) );
//        }


    }

    public function add_that_script()
    {
        if (is_plugin_page('wbb_settings')) // this is the page you need; check http://codex.wordpress.org/Function_Reference/is_page on how to use this function; you can provide as a parameter the id, title, name or an array..
        {
            wp_enqueue_style( 'mdb.min', plugins_url( '/css/mdb.min.css', __FILE__ ) , array(), null, false);
            wp_enqueue_style( 'new-style', plugins_url( '/css/new-style.css', __FILE__ ) , array(), null, false);
            wp_enqueue_style( 'sweetalert2', plugins_url( '/css/sweetalert2.css', __FILE__ ), array(), null, false );
            wp_enqueue_style( 'sweetalert2.min', plugins_url( '/css/sweetalert2.min.css', __FILE__ ) , array(), null, false);
            wp_enqueue_script( 'script1', plugins_url( '/js/sweetalert2.all.js', __FILE__ ) , 1.1,null, false);
            wp_enqueue_script( 'script2', plugins_url( '/js/sweetalert2.all.min.js', __FILE__ ) , 1.1,null, false);
            wp_enqueue_script( 'script3', plugins_url( '/js/sweetalert2.js', __FILE__ ) , 1.1,null, false);
            wp_enqueue_script( 'script4', plugins_url( '/js/sweetalert2.min.js', __FILE__ ) , 1.1,null, false);
            wp_enqueue_script( 'script5', plugins_url( '/js/mdb.min.js', __FILE__ ) , 1.1,null, true);
            // wp_enqueue_script( 'script6', plugins_url( '/js/connect.js', __FILE__ ) , 1.1,null, true);
        }
    }
    
    // public function add_my_stylesheet() 
    // {
    //     wp_enqueue_style( 'mdb.min', plugins_url( '/css/mdb.min.css', __FILE__ ) , array(), null, false);
    //     wp_enqueue_style( 'new-style', plugins_url( '/css/new-style.css', __FILE__ ) , array(), null, false);
    //     wp_enqueue_style( 'sweetalert2', plugins_url( '/css/sweetalert2.css', __FILE__ ), array(), null, false );
    //     wp_enqueue_style( 'sweetalert2.min', plugins_url( '/css/sweetalert2.min.css', __FILE__ ) , array(), null, false);
    //     // wp_enqueue_script( 'script', get_template_directory_uri() . '/js/script.js', array ( 'jquery' ), 1.1, true);
    // }




    public function wbb_request_backup() {
        register_rest_route('api/v1', '/request-backup', array(
            'methods' => 'GET',
            'callback' => array($this, 'wbb_api_route_request_backup'),
            'args' => array(
                'id' => array(
                    'default' => 0
                ),
            ),
        ));
    }

    public function wbb_check_code() {
        register_rest_route('api/v1', '/check-code', array(
            'methods' => 'POST',
            'callback' => array($this, 'wbb_api_route_check_code'),
            'args' => array(
                'id' => array(
                    'default' => 0
                ),
            ),
        ));
    }


    /**
     *
     * Custom API Route Callback Function
     */

    public function wbb_api_route_check_code(WP_REST_Request $request) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tableName = $wpdb->prefix . "backups_requests";

        $code = $request['code'];
        $response=new \stdClass;

        if (
            $is_in_database = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT * FROM {$wpdb->prefix}backups_requests
                    WHERE backup_code = %s",
                    $code
                )
            )
        ) {
            $body = $is_in_database;

            $response->result = $body;
            return rest_ensure_response($response);
        } else {
            $body = 'THERE IS NO CODE';
            $response->result = $body;
            return rest_ensure_response($response);
        }
    }

    public function wbb_update_settings() {
        $nonce = sanitize_text_field($_POST['nonce']);
        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Nonce value cannot be verified.');
        }
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $table_name = $wpdb->prefix . "wbb_settings";
        $backup_wp_db = sanitize_text_field($_POST['backup_wp_db']);
        $backup_files_root = sanitize_text_field($_POST['backup_files_root']);


        if (count($wpdb->get_var("SHOW TABLES LIKE '$table_name'")) == 0) {

            $sql_query_to_create_table = "CREATE TABLE $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `backup_wp_db` int(11) DEFAULT NULL,
                    `backup_files_root` int(11) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1"; /// sql query to create table

            dbDelta($sql_query_to_create_table);

            $wpdb->insert(
                $table_name,
                array(
                    "backup_wp_db" => $backup_wp_db,
                    "backup_files_root" => $backup_files_root,
                )
            );

            $response = 'done';
            // echo $response;
            _e($response);

        } else {

            $rows_affected = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_name} SET backup_wp_db = %s, backup_files_root = %s WHERE id= %d;",
                    $backup_wp_db, $backup_files_root, 1
                ) // $wpdb->prepare
            ); // $wpdb->query


            $response = 'done';

            // echo $response;
            _e($response);
        }


    }

    public function wbb_api_route_request_backup(WP_REST_Request $request) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->prefix . "backups_requests";

        // Make sure the script can handle large folders/files
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        $file_compression = ".zip";
        $date = date_i18n('Y-m-d-His');
        $response=new \stdClass;

        $exclude = array(__DIR__ . DIRECTORY_SEPARATOR . "cache", __DIR__ . DIRECTORY_SEPARATOR . "cloud");
        $nonExclude = array();

        $dbhost = DB_HOST;
        $dbuser = DB_USER;
        $dbpass = DB_PASSWORD;
        $dbname = DB_NAME;
        $tables = '*';

        if (count($wpdb->get_var("SHOW TABLES LIKE '$table_name'")) == 0) {
            $sql_query_to_create_table = "CREATE TABLE $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `path` varchar(150) DEFAULT NULL,
                    `pathDb` varchar(150) DEFAULT NULL,
                    `backup_code` varchar(150) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1"; /// sql query to create table

            dbDelta($sql_query_to_create_table);
            //Call the core function
            $NameFile = __DIR__ . '/cloud/db-backup-' . time() . '.sql';
            $this->wbb_backup_tables($dbhost, $dbuser, $dbpass, $dbname, $tables, $NameFile);
            // Start the backup!
            $this->wbb_zipData(ABSPATH, __DIR__ . "/cloud/backup" . $date . $file_compression, $exclude);
            $this->wbb_zipSQL($NameFile, __DIR__ . "/cloud/backupDB" . $date . $file_compression, $nonExclude);


            $guid = $this->wbb_GUIDv4();

            $path = __DIR__ . "/cloud/backup" . $date . $file_compression;
            $pathdb = __DIR__ . "/cloud/backupDB" . $date . $file_compression;
            $wpdb->insert(
                $table_name,
                array(
                    "path" => $path,
                    "pathDb" => $pathdb,
                    "backup_code" => $guid,
                )
            );
            unlink($NameFile);
            $response->code = $guid;
            return rest_ensure_response($response);

        } else {

            //Call the core function
            $NameFile = __DIR__ . '/cloud/db-backup-' . time() . '.sql';
            $this->wbb_backup_tables($dbhost, $dbuser, $dbpass, $dbname, $tables, $NameFile);
            $this->wbb_zipData(ABSPATH, __DIR__ . "/cloud/backup" . $date . $file_compression, $exclude);
            $this->wbb_zipSQL($NameFile, __DIR__ . "/cloud/backupDB" . $date . $file_compression, $nonExclude);

            $guid = $this->wbb_GUIDv4();

            $path = __DIR__ . "/cloud/backup" . $date . $file_compression;
            $pathdb = __DIR__ . "/cloud/backupDB" . $date . $file_compression;
            $wpdb->insert(
                $table_name,
                array(
                    "path" => $path,
                    "pathDb" => $pathdb,
                    "backup_code" => $guid,
                )
            );
            unlink($NameFile);
            $response->code = $guid;
            return rest_ensure_response($response);
        }


    }


    function wbb_GUIDv4($trim = true) {
        // Windows
        if (function_exists('com_create_guid') === true) {
            if ($trim === true)
                return trim(com_create_guid(), '{}');
            else
                return com_create_guid();
        }

        // OSX/Linux
        if (function_exists('openssl_random_pseudo_bytes') === true) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        // Fallback (PHP 4.2+)
        mt_srand((double)microtime() * 10000);
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45);                  // "-"
        $lbrace = $trim ? "" : chr(123);    // "{"
        $rbrace = $trim ? "" : chr(125);    // "}"
        $guidv4 = $lbrace .
            substr($charid, 0, 8) . $hyphen .
            substr($charid, 8, 4) . $hyphen .
            substr($charid, 12, 4) . $hyphen .
            substr($charid, 16, 4) . $hyphen .
            substr($charid, 20, 12) .
            $rbrace;
        return $guidv4;
    }

    function wbb_custom_plugin_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


        $table_name_user = $wpdb->prefix . "wbb_user";
        //  if (count($wpdb->get_var('SHOW TABLES LIKE "wp_custom_plugin"')) == 0){
        if (count($wpdb->get_var("SHOW TABLES LIKE '$table_name_user'")) == 0) {
            $sql_query_to_create_table = "CREATE TABLE $table_name_user (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` varchar(150) DEFAULT NULL,
            `code` varchar(150) DEFAULT NULL,
            `project_id` varchar(150) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1"; /// sql query to create table

            dbDelta($sql_query_to_create_table);

        }
        $table_name = $wpdb->prefix . "wbb_settings";
        if (count($wpdb->get_var("SHOW TABLES LIKE '$table_name'")) == 0) {

            $sql_query_to_create_table = "CREATE TABLE $table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `backup_wp_db` int(11) DEFAULT NULL,
                `backup_files_root` int(11) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1"; /// sql query to create table

            dbDelta($sql_query_to_create_table);

            $wpdb->insert(
                $table_name,
                array(
                    "backup_wp_db" => '1',
                    "backup_files_root" => '1',
                )
            );

            $response = 'done';
            _e($response);
            // echo $response;

        }
    }


    public function wbb_heartbeat_ajax_request() {
        $nonce = sanitize_text_field(['nonce']);
        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Nonce value cannot be verified.');
        } else {
            $response = wp_remote_post($this->VENDOR_SITE_URL . "/" . $this->VENDOR_ROUTES['HEARTBEAT'], array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'httpversion' => '1.0',
                'sslverify' => false,
                'body' => json_encode(array("code" => sanitize_text_field($_POST['code'])))
            ));
            wp_send_json(json_encode(array("response" => true, "body" => json_decode(wp_remote_retrieve_body($response)))));
        }
    }

    public function wbb_update_data_ajax_request() {
        $nonce = sanitize_text_field($_POST['nonce']);
        //code for current project?
        $code = sanitize_text_field($_POST['code']);
        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Nonce value cannot be verified.');
        } else {
            $response = wp_remote_post($this->VENDOR_SITE_URL . "/" . $this->VENDOR_ROUTES['UPDATE_DATA'], array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'httpversion' => '1.0',
                'sslverify' => false,
                'body' => json_encode(array("code" => sanitize_text_field($_POST['code'])))
            ));

            wp_send_json(json_encode(array("response" => true, "body" => json_decode(wp_remote_retrieve_body($response)))));
        }
    }

    public function wbb_save_code_ajax_request() {
        //code
        $code = sanitize_text_field($_POST['code']);
        //username-email
        $username = sanitize_text_field($_POST['username']);
        $project_id = sanitize_text_field($_POST['project_id']);

        //project info
        $database_credentials = sanitize_text_field($_POST['database_credentials']);
        $website_credentials = sanitize_text_field($_POST['website_credentials']);
        $host = sanitize_text_field($_POST['host']);
        $name = sanitize_text_field($_POST['name']);
        $status = sanitize_text_field($_POST['status']);
        $usage = sanitize_text_field($_POST['usage']);
        $user = sanitize_text_field($_POST['user']);

        $total = 1073741824;
        // array
        $array = array(
            "code" => $code,
            "username" => $username,
            "project_id" => $project_id,
            "database_credentials" => $database_credentials,
            "website_credentials" => $website_credentials,
            "host" => $host,
            "name" => $name,
            "status" => $status,
            "total" => $this->wbb_human_filesize($total),
            "progress_bar" => $this->renderProgressBar($usage, $total),
            "usage" => $this->wbb_human_filesize($usage),
            "user" => $user,

        );
        // encode array to json
        $json = json_encode($array);

        $cache_path = dirname(__FILE__) . '/' . $this->CACHE_PATH;
        //write json to file
        if (file_put_contents($cache_path . '/' . "data.json", $json)) {
            $body = "JSON file created successfully...";
        } else {
            $body = "Oops! Error creating json file...";
        }

        global $wpdb;
        $tableName = $wpdb->prefix . "wbb_user";

        $wpdb->insert(
            $tableName,
            array(
                "user_id" => $user,
                "code" => $code,
                "project_id" => $project_id,
            )
        );

        wp_send_json(json_encode(array("response" => true, "body" => $body)));
    }


    public function wbb_update_local_ajax_request() {
        // //code
        $code = sanitize_text_field($_POST['code']);
        $project_id = sanitize_text_field($_POST['project_id']);

        //username-email
        $username = sanitize_text_field($_POST['username']);

        // //project info
        $database_credentials = sanitize_text_field($_POST['database_credentials']);
        $website_credentials = sanitize_text_field($_POST['website_credentials']);
        $host = sanitize_text_field($_POST['host']);
        $name = sanitize_text_field($_POST['name']);
        $status = sanitize_text_field($_POST['status']);
        $usage = sanitize_text_field($_POST['usage']);
        $user = sanitize_text_field($_POST['user']);

        $total = 1073741824;
        // // array
        $array = array(
            "code" => $code,
            "username" => $username,
            "project_id" => $project_id,
            "database_credentials" => $database_credentials,
            "website_credentials" => $website_credentials,
            "host" => $host,
            "name" => $name,
            "status" => $status,
            "total" => $this->wbb_human_filesize($total),
            "progress_bar" => $this->renderProgressBar($usage, $total),
            "usage" => $this->wbb_human_filesize($usage),
            //user ID
            "user" => $user,
        );
        // encode array to json
        $json = json_encode($array);
        $cache_path = dirname(__FILE__) . '/' . $this->CACHE_PATH;
        //write json to file
        if (file_put_contents($cache_path . '/' . "data.json", $json)) {
            $body = "JSON file created successfully...";
        } else {
            $body = "Oops! Error creating json file...";
        }
        wp_send_json(json_encode(array("response" => true, "body" => $body)));

    }

    public function wbb_check_code_ajax_request() {
        global $wpdb;
        $tableName = $wpdb->prefix . "wbb_user";

        // if ($results = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableName WHERE id = 1" ) ))
        if ($results = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tableName WHERE id = %d", 1))) {
            $body = $results;
        } else {
            $body = 'THERE IS NO CODE';
        }
        wp_send_json(json_encode(array("response" => true, "body" => $body)));

    }

    public function wbb_register_ajax_request() {
        $nonce = sanitize_text_field($_POST['nonce']);
        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Nonce value cannot be verified.');
        } else {
            $url = get_bloginfo('wpurl');
            $obj = array(
                "code" => sanitize_text_field($_POST['code']),
                "host" => $url,
            );
            $response = wp_remote_post($this->VENDOR_SITE_URL . "/" . $this->VENDOR_ROUTES['REGISTER'], array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'httpversion' => '1.0',
                'sslverify' => false,
                'body' => json_encode($obj)
            ));

            wp_send_json(json_encode(array("response" => true, "body" => wp_remote_retrieve_body($response))));
        }
    }


    public function wbb_backup_ajax_request() {
        $nonce = sanitize_text_field($_POST['nonce']);
        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Nonce value cannot be verified.');
        }

        global $wpdb;

        $tableName = $wpdb->prefix . "wbb_settings";

        // $resultSettings = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableName WHERE id = 1" ));
        $resultSettings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tableName WHERE id = %d", 1));
        $backup_wp_db = $resultSettings->backup_wp_db;
        $backup_files_root = $resultSettings->backup_files_root;

        if ($backup_wp_db == '1') {
            $this->wbb_exportDataNow();
        } else {
            $this->wbb_exportDataWithoutDB();
        }

    }

    public function wbb_backup_ajax_delete() {
        $nonce = sanitize_text_field($_POST['nonce']);
        $filename = sanitize_text_field($_POST['filename']);
        $file = __DIR__ . "/cache/" . trim($filename);
        if (!wp_verify_nonce($nonce, 'ajax-nonce')) {
            die('Nonce value cannot be verified.');
        }
        unlink($file);
        _e('File deleted.');
    }

    private function wbb_human_filesize($bytes, $dec = 2) {
        $size = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    private function renderProgressBar($used_raw, $total_raw) {
        $usage_percentage = (($used_raw / $total_raw) * 100);


        if ($usage_percentage >= 85) {
            $status_color = "bg-danger";
        } else if ($usage_percentage >= 50) {
            $status_color = "bg-warning";
        } else {
            $status_color = "bg-info";
        }


        return ('<div class="progress-bar progress-bar-striped ' . $status_color . '" role="progressbar"
        style="width: ' . $usage_percentage . '%;"
        aria-valuenow="' . $usage_percentage . '"
        aria-valuemin="0" aria-valuemax="100">
      </div>');
    }

    public function wbb_exportDataNow() {


        // Make sure the script can handle large folders/files
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        $file_compression = ".zip";
        $date = date_i18n('Y-m-d-His');

        $exclude = array(__DIR__ . DIRECTORY_SEPARATOR . "cache", __DIR__ . DIRECTORY_SEPARATOR . "cloud");
        // $exclude = array(__DIR__.DIRECTORY_SEPARATOR."cache" );
        $dbhost = DB_HOST;
        $dbuser = DB_USER;
        $dbpass = DB_PASSWORD;
        $dbname = DB_NAME;
        $tables = '*';
        //Call the core function
        $NameFile = __DIR__ . '/temp/db-backup-' . time() . '.sql';
        $this->wbb_backup_tables($dbhost, $dbuser, $dbpass, $dbname, $tables, $NameFile);
        // Start the backup!
        $this->wbb_zipData(ABSPATH, __DIR__ . "/temp/backup" . $date . $file_compression, $exclude);

        $nonExclude = array();
        $this->wbb_zipData(__DIR__ . "/temp", __DIR__ . "/cache/backup" . $date . $file_compression, $nonExclude);
        unlink($NameFile);
        unlink(__DIR__ . "/temp/backup" . $date . $file_compression);
        // echo 'Finished.';
        _e('Finished.');
        // Here the magic happens :)

    }

    public function wbb_exportDataWithoutDB() {


        // Make sure the script can handle large folders/files
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        $file_compression = ".zip";
        $date = date_i18n('Y-m-d-His');

        $exclude = array(__DIR__ . DIRECTORY_SEPARATOR . "cache");

        $this->wbb_zipData(ABSPATH, __DIR__ . "/cache/backup" . $date . $file_compression, $exclude);
        // echo 'Finished.';
        _e('Finished.');

        // Here the magic happens :)

    }

    public function wbb_zipSQL($source, $destination, $exclude) {
        if (extension_loaded('zip')) {
            if (file_exists($source)) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
                    $source = realpath($source);
                    if (is_dir($source)) {
                        $iterator = new RecursiveDirectoryIterator($source);
                        // skip dot files while iterating
                        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($files as $file) {
                            if (in_array($file, $exclude)) {

                                continue;
                            }
                            if (is_file($file)) {
                                $p = pathinfo($file);
                                if (in_array($p['dirname'], $exclude)) {
                                    continue;
                                }
                            }
                            $file = realpath($file);
                            if (is_dir($file)) {
                                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                            } else if (is_file($file)) {
                                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                            }
                        }
                    } else if (is_file($source)) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }
                return $zip->close();
            }
        }
        return false;
    }


    public function wbb_zipData($source, $destination, $exclude) {
        if (extension_loaded('zip')) {
            if (file_exists($source)) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
                    $source = realpath($source);
                    if (is_dir($source)) {
                        $iterator = new RecursiveDirectoryIterator($source);
                        // skip dot files while iterating 
                        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($files as $file) {
                            if (in_array($file, $exclude)) {

                                continue;
                            }
                            if (is_file($file)) {
                                $p = pathinfo($file);
                                if (in_array($p['dirname'], $exclude)) {
                                    continue;
                                }
                            }
                            $file = realpath($file);
                            if (is_dir($file)) {
                                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                            } else if (is_file($file)) {
                                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                            }
                        }
                    } else if (is_file($source)) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }
                return $zip->close();
            }
        }
        return false;
    }

    public function wbb_backup_tables($host, $user, $pass, $dbname, $tables = '*', $NameFile) {
        $link = mysqli_connect($host, $user, $pass, $dbname);

        // Check connection
        if (mysqli_connect_errno()) {
            _e("Failed to connect to MySQL: " . mysqli_connect_error());
            // echo "Failed to connect to MySQL: " . mysqli_connect_error();
            exit;
        }

        mysqli_query($link, "SET NAMES 'utf8'");

        //get all of the tables
        if ($tables == '*') {
            $tables = array();
            $result = mysqli_query($link, 'SHOW TABLES');
            while ($row = mysqli_fetch_row($result)) {
                $tables[] = $row[0];
            }
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        $return = '';
        //cycle through
        foreach ($tables as $table) {
            $result = mysqli_query($link, 'SELECT * FROM ' . $table);
            $num_fields = mysqli_num_fields($result);
            $num_rows = mysqli_num_rows($result);

            $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
            $row2 = mysqli_fetch_row(mysqli_query($link, 'SHOW CREATE TABLE ' . $table));
            $return .= "\n\n" . $row2[1] . ";\n\n";
            $counter = 1;

            //Over tables
            for ($i = 0; $i < $num_fields; $i++) {   //Over rows
                while ($row = mysqli_fetch_row($result)) {
                    if ($counter == 1) {
                        $return .= 'INSERT INTO ' . $table . ' VALUES(';
                    } else {
                        $return .= '(';
                    }

                    //Over fields
                    for ($j = 0; $j < $num_fields; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $return .= '"' . $row[$j] . '"';
                        } else {
                            $return .= '""';
                        }
                        if ($j < ($num_fields - 1)) {
                            $return .= ',';
                        }
                    }

                    if ($num_rows == $counter) {
                        $return .= ");\n";
                    } else {
                        $return .= "),\n";
                    }
                    ++$counter;
                }
            }
            $return .= "\n\n\n";
        }

        //save file
        $fileName = $NameFile;
        $handle = fopen($fileName, 'w+');
        fwrite($handle, $return);
        if (fclose($handle)) {
        }
    }

    public function wbb_settings() {


        $TEMPLATE_DATA = $this->TEMPLATE_DATA;
        $TEMPLATE_DATA['PAGE'] = 'page/browser/browser.html';

        //Scan The Cache Directory
        $cache_path = dirname(__FILE__) . '/' . $this->CACHE_PATH;
        $cached_files = array_diff(scandir($cache_path), array('.', '..'));

        $dir = dirname(__FILE__);
        $free = disk_free_space($dir);
        $total = disk_total_space($dir);

        $backups = array();
        foreach ($cached_files as $file) {
            //fix to skip json file.
            if (stristr($file, ".json")||stristr($file, "index.html")) {
                continue;
            }
            //Determine Our Extension Type
            $file_parts = pathinfo($file);

            $size = 0;
            $tar_file = $file;
            $sql_file = str_replace(".tar.gz", ".sql", $file);

            $file_date = str_replace(".tar.gz", "", $file);
            $file_date = str_replace(".sql", "", $file_date);
            $file_date = str_replace("backup-", "", $file_date);

            //Set Defaults

            //Backup Object
            if (isset($backups[$file_date])) {
                $backup = $backups[$file_date];
            } else {
                $backup = array(
                    "contents" => array(),
                    "location" => 'Local',
                    "metadata" => array()
                );
            }
            $finalExtension="";

            switch ($file_parts['extension']) {
                case "gz":
                {
                    $finalExtension=$tar_file;


                    break;
                }
                case "zip":
                {
                    $finalExtension=$tar_file;

                    break;
                }

                case "sql":
                {
                    $finalExtension=$sql_file;
                    break;
                }
            }

            $backup["metadata"]["database"] = array(
                "filename" => "",
                "size" => filesize($cache_path . '/' . $finalExtension)
            );


            $backup['backup_date'] = $file_date;

            $contents_string = "";
            if (isset($backup["metadata"]["data"])) {

                $contents_string = 'Data';
            }

            if (isset($backup["metadata"]["database"])) {

                $contents_string .= '&nbsp;&nbsp;Data';
                // $contents_string .= '&nbsp;&nbsp;Database';
            }

            $backup['filename'] = $file;
            $backup['contents'] = $contents_string;

            $date_parts = explode('-', $file_date);

            $date = array(
                'year' => $date_parts[0],
                'month' => $date_parts[1],
                'day' => $date_parts[2],
                'time' => array(
                    "raw" => $date_parts[3],
                    "formatted" => date("g:i a", strtotime($date_parts[3]))
                )
            );


            $backup['backup_date'] = $date;


            $fullsize = $backup["metadata"]["data"]["size"] + $backup["metadata"]["database"]["size"];

            $backup['size'] = $this->wbb_human_filesize($fullsize);


            $backups[$file_date] = $backup;
        }


        $TEMPLATE_DATA['SYSTEM_INFO'] = array(
            "USED" => $this->wbb_human_filesize($free),
            "USED_RAW" => $free,
            "TOTAL" => $this->wbb_human_filesize($total),
            "TOTAL_RAW" => $total,
            "ROOT_PATH" => ABSPATH
        );


        $TEMPLATE_DATA['ACCOUNT_INFO'] = array(
            "USED" => $this->wbb_human_filesize($free / 2),
            "USED_RAW" => $free / 2,
            "TOTAL" => $this->wbb_human_filesize($total / 2),
            "TOTAL_RAW" => $total / 2,
            "ROOT_PATH" => ABSPATH
        );

        $backups = array_reverse($backups, true);

        $TEMPLATE_DATA['LOCAL_BACKUPS'] = $backups;

        $TEMPLATE_DATA['NONCE'] = wp_create_nonce('ajax-nonce');
        $TEMPLATE_DATA['AJAX_URL'] = admin_url('admin-ajax.php');

        global $wpdb;

        $tableName = $wpdb->prefix . "wbb_settings";

        $resultSettings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tableName WHERE id = %d", 1));

        $TEMPLATE_DATA['SETTINGS'] = $resultSettings;

        print($this->wbb_renderTemplate("wbb-ui.html", $TEMPLATE_DATA));
    }


    public function wbb_add_admin_page() {


        add_submenu_page(
            'tools.php',
            'Submenu Page',
            'Website BackupBot',
            'manage_options',
            'wbb_settings',
            array($this, 'wbb_settings')
        );
    }


    /**
     * Render the Template but do not display it.
     * @param String - $template
     */
    protected function wbb_renderTemplate($template, $TEMPLATE_DATA) {

        //Default Variables
        $INIT_TWIG_DATA = array();
        $INIT_TWIG_DATA["SITE_TITLE"] = get_bloginfo('name');
        $INIT_TWIG_DATA["SITE_URL"] = get_bloginfo('wpurl');


        $INIT_TWIG_DATA["THEME_URL"] = get_bloginfo('wpurl') . '/wp-content/plugins/' . basename(dirname(__FILE__));


        if (is_array($TEMPLATE_DATA)) {
            $TWIG_DATA = $TEMPLATE_DATA;
            $TWIG_DATA = array_merge($TWIG_DATA, $INIT_TWIG_DATA);
            //Handle Combined JS
            $TWIG_DATA['COMBINED_JS']= $this->twig->getContent("js/combined.html", $TWIG_DATA);

            return $this->twig->getContent($template, $TWIG_DATA);
        } else {
            $TWIG_DATA = $INIT_TWIG_DATA;

            //Handle Combined JS
            $TWIG_DATA['COMBINED_JS']= $this->twig->getContent("js/combined.html", $TWIG_DATA);

            return $this->twig->getContent($template, $TWIG_DATA);
        }
    }
}


$website_backup_bot_plugin = new WP_WebsiteBackupBot_Plugin();
