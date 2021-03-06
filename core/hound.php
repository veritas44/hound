<?php
define ('HOUND_VERSION', '0.7.0');

function get_theme_directory($partial) {
    $websiteurl = getcwd();

    $template = hound_get_parameter('template');
    $templatePath = $websiteurl . '/site/templates/' . $template . '/' . $partial;

    return $templatePath;
}

function hound_count_content($type) {
    if ($type === 'page' || $type === 'menu') {
        $dir = '../site/pages/';
        $list = glob($dir . $type . '-*.txt');
    } else if ($type === 'post') {
        $dir = '../site/pages/';
        $list = glob($dir . $type . '-*.txt');
    } else if ($type === 'backup') {
        $dir = '../backup/';
        $list = glob($dir . $type . '-*.zip');
    } else if ($type === 'asset') {
        $dir = '../files/images/';
        $list = glob($dir . '*.*');
    }

    return (int) count($list);
}

/**
 * Get configuration parameter
 * 
 * @since 0.1.4
 * @author Ciprian Popescu
 * 
 * @param string $name Name of parameter from configuration file
 * @return string
 */
function hound_get_parameter($name) {
    $websiteurl = getcwd();
    $websiteurl = str_replace('/admin', '', $websiteurl);

    $parameter = hound_read_parameter($websiteurl . '/site/config.txt');

    return (string) $parameter[$name];
}

function hound_get_contents($url) {
    if (function_exists('file_get_contents')) {
        $urlGetContentsData = file_get_contents($url);
    } else if (function_exists('fopen') && function_exists('stream_get_contents')) {
        $handle = fopen($url, "r");
        $urlGetContentsData = stream_get_contents($handle);
    } else {
        $urlGetContentsData = false;
    }

    return $urlGetContentsData;
}

function hound_read_parameter($file) {
    $headers = array(
        'title' => 'Title',
        'content' => 'Content',
        'template' => 'Template',
        'menu' => 'Menu',
        'url' => 'Url',
        'slug' => 'Slug',
        'order' => 'Order',
        'link' => 'Link',
        'item' => 'Item',
        'slogan' => 'Slogan',
        'include' => '',
    );

    $content = hound_get_contents($file);

    // Add support for custom headers by hooking into the headers array
    foreach ($headers as $field => $regex) {
        if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $content, $match) && $match[1]) {
            $headers[$field] = trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $match[1]));
        } else {
            $headers[$field] = '';
        }
    }

    return $headers;
}



function hound_compare($a, $b) {
    return filemtime($b) - filemtime($a);
}

function hound_render_blog($echo = true) {
    $i = 0;
    $arrayOfPosts = array();

    $getPosts = glob('site/pages/post-*.txt');
    usort($getPosts, 'hound_compare');

    foreach($getPosts as $file) {
        $headers = array(
            'title' => 'Title',
            'content' => 'Content',
            'template' => 'Template',
            'slug' => 'Slug',
        );

        if (!function_exists('file_get_contents')) {
            $content = hound_get_contents($file);
        } else {
            $content = file_get_contents($file);
        }

        // Add support for custom headers by hooking into the headers array
        foreach ($headers as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $content, $match) && $match[1]) {
                $headers[$field] = trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $match[1]));
            } else {
                $headers[$field] = '';
            }
        }

        $postParam = $headers;

        $arrayOfPosts[$i]['slug'] = $postParam['slug'];
        $arrayOfPosts[$i]['title'] = $postParam['title'];
        $arrayOfPosts[$i]['content'] = $postParam['content'];

        $arrayOfPosts[$i]['date'] = date('F d Y H:i:s', filemtime($file));

        $i++;
    }

    // Build blog
    $blogPosts = '';
    if (!empty($arrayOfPosts) && is_array($arrayOfPosts)) {
        foreach ($arrayOfPosts as $blogPost) {
            $blogPosts .= '<div class="post">
                <h3><a href="' . $blogPost['slug'] . '">' . $blogPost['title'] . '</a></h3>
                <div class"post-meta">' . $blogPost['date'] . '</div>
                <div class="post-content">' . $blogPost['content'] . '</div>
            </div>';
        }
    }

    if ($echo === true) {
        echo $blogPosts;
    } else {
        return $blogPosts;
    }
}




class hound {
    var $config;
    var $path;
    var $websiteurl;
    var $plugins;

    public function __construct($path, $websiteurl) {
        $this->path = $path;
        $this->websiteurl = $websiteurl;
    }

    public function start() {
        // Load plugins
        $this->load_plugins();

        $listofpage = array();

        // Read template
        $config = hound_read_parameter('site/config.txt');
        $titleofsite = $config['title'];

        // Retrieve a page
        $currentUrl = explode('?', $_SERVER['REQUEST_URI']);
        $curpath = str_replace($this->path, '', $currentUrl[0]);

        $listofword = explode('/', $curpath);
        $curpage = $listofword[count($listofword) - 1];

        // Load files in /pages/
        $i = 0;
        $fileindir = $this->get_files('site/pages/');
        foreach ($fileindir as $file) {
            if (preg_match("/\bpage\b/i", $file)) {
                $listofpage[] = str_replace('site/pages/', '', $file);
            }
            if (preg_match("/\bpost\b/i", $file)) {
                $listofpage[] = str_replace('site/pages/', '', $file);
            }

            if (preg_match("/\bmenu\b/i", $file)) {
                $menuparam = hound_read_parameter($file);
                $arrayofmenu[$i]['order'] = $menuparam['order'];
                $arrayofmenu[$i]['link'] = $menuparam['link'];
                $arrayofmenu[$i]['item'] = $menuparam['item'];
                $i++;
            }
        }

        // Read file content
        if (in_array('page-' . $curpage . '.txt', $listofpage)) {
            $pageparam = hound_read_parameter('site/pages/page-' . $curpage . '.txt');
        } else if (in_array('post-' . $curpage . '.txt', $listofpage)) {
            $pageparam = hound_read_parameter('site/pages/post-' . $curpage . '.txt');
        } else {
            $pageparam = hound_read_parameter('site/pages/page-index.txt');
        }

        // Build menu
        $menuitems = '';
        if (!empty($arrayofmenu) && is_array($arrayofmenu)) {
            array_multisort($arrayofmenu);
            foreach ($arrayofmenu as $itemmenu) {
                $menuitems .= '<li><a href="' . $itemmenu['link'] . '">' . $itemmenu['item'] . '</a></li>';
            }
        }

        //RENDER LAYOUT
        $layout = new Template("site/templates/".$config['template']."/".$pageparam['template']);
        $layout->set("title", $pageparam['title']);

        /*
         * Parse content hook(s)
         */
        $pageparam['content'] = hook('content', $pageparam['content']);

        $layout->set("content", $pageparam['content']);
        $layout->set("menu", $menuitems);  
        $layout->set("urlwebsite", $this->websiteurl); 
        $layout->set("site.title", $config['title']);
        $layout->set("slogan", $config['slogan']);

        $layout->set("slug", $pageparam['slug']);
        $layout->set("excerpt", substr(strip_tags(trim($pageparam['content'])), 0, 300));

        echo $layout->output();
    }

    function get_files($directory, $ext = ''){

        $arrayItems = array();
        if ($files = scandir($directory)) {
            foreach ($files as $file) {
                if (in_array(substr($file, -1), array('~', '#'))) {
                    continue;
                }
                if (preg_match("/^(^\.)/", $file) === 0) {
                    if (is_dir($directory . "/" . $file)) {
                        $arrayItems = array_merge($arrayItems, $this->get_files($directory . "/" . $file, $ext));
                    } else {
                        $file = $directory . "/" . $file;
                        if (!$ext || strstr($file, $ext)) {
                            $arrayItems[] = preg_replace("/\/\//si", "/", $file);
                        }
                    }
                }
            }
        }

        return $arrayItems;
    }

     /**
     * Load any plugins
     */
    public function load_plugins()
    {
        $this->plugins = array();
        $plugins = $this->get_files("plugins", '.php');
        if (!empty($plugins)) {
            foreach ($plugins as $plugin) {
                include_once($plugin);
                $pluginName = preg_replace("/\\.[^.\\s]{3}$/", '', basename($plugin));
                if (class_exists($pluginName)) {
                    $obj = new $pluginName;
                    $this->plugins[] = $obj;
                }
            }
        }
    }
}
