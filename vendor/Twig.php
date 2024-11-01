<?php


/**
 */
//Include the relevant Twig classes.

class WebsiteBackupBot_Twig {

    //Twig Variables.
    private $TWIG_LOADER;
    private $TWIG_INSTANCE;
    //Instance Variables
    private $TEMPLATE_DIRS = array();
    private $CACHE_DIR;

    //Twig Cache

    function __construct() {

        //Twig_Autoloader::register();
    }

    /**
     * Set Template Directory along with the Cache Directory.
     * @param String $template_directory - Template Directory
     * @param String $cache_dir - Cache Directory
     */
    public function setTemplateDirectory($template_directory, $cache_dir = null) {
        if (is_dir($template_directory)) {

            $this->TWIG_LOADER = new \Twig\Loader\FilesystemLoader($template_directory, $template_directory);
            $this->TWIG_INSTANCE = new \Twig\Environment($this->TWIG_LOADER, array('debug' => true));

            if (is_dir($cache_dir)) {
                $this->TWIG_INSTANCE = new \Twig\Environment($this->TWIG_LOADER, array('debug' => true, 'cache' => $cache_dir,));

                $this->CACHE_DIR = $cache_dir;
            }
        }
    }

    /**
     * Get Twig Template Object from Template located in Template Directory
     * @param String $template - Template to Render
     * @param String $data - Data Array of Values to Pass to Template.
     * @return String $template_data - Twig template.
     */
    public function getContent($template, $data = null) {
        $template = $this->TWIG_INSTANCE->loadTemplate($template);
        $content = $template->render($data);
        return $content;
    }


}

?>