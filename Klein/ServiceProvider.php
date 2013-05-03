<?php
/**
 * Klein (klein.php) - A lightning fast router for PHP
 *
 * @author      Chris O'Hara <cohara87@gmail.com>
 * @author      Trevor Suarez (Rican7) (contributor and v2 refactorer)
 * @copyright   (c) Chris O'Hara
 * @link        https://github.com/chriso/klein.php
 * @license     MIT
 */

namespace Klein;

use \Klein\DataCollection\DataCollection;

/**
 * ServiceProvider 
 *
 * Service provider class for handling logic extending between
 * a request's data and a response's behavior
 * 
 * @package     Klein
 */
class ServiceProvider
{

    /**
     * Class properties
     */

    /**
     * The Request instance containing HTTP request data and behaviors
     *
     * @var Request
     * @access protected
     */
    protected $request;

    /**
     * The Response instance containing HTTP response data and behaviors
     *
     * @var Response
     * @access protected
     */
    protected $response;

    /**
     * The id of the current PHP session
     *
     * @var string
     * @access protected
     */
    protected $session_id;

    /**
     * The view layout
     *
     * @var string
     * @access protected
     */
    protected $layout;

    /**
     * The view to render
     *
     * @var string
     * @access protected
     */
    protected $view;

    /**
     * Shared data collection
     *
     * @var \Klein\DataCollection\DataCollection
     * @access protected
     */
    protected $shared_data;


    /**
     * Methods
     */

    /**
     * Constructor
     *
     * @param Request $request      Object containing all HTTP request data and behaviors
     * @param Response $response    Object containing all HTTP response data and behaviors
     * @access public
     */
    public function __construct(Request $request = null, Response $response = null)
    {
        // Bind our objects
        $this->bind($request, $response);

        // Instantiate our shared data collection
        $this->shared_data = new DataCollection();
    }

    /**
     * Bind object instances to this service
     *
     * @param Request $request      Object containing all HTTP request data and behaviors
     * @param Response $response    Object containing all HTTP response data and behaviors
     * @access public
     * @return ServiceProvider
     */
    public function bind(Request $request = null, Response $response = null)
    {
        // Keep references
        $this->request  = $request  ?: $this->request;
        $this->response = $response ?: $this->response;

        return $this;
    }

    /**
     * Returns the shared data collection object
     *
     * @access public
     * @return \Klein\DataCollection\DataCollection
     */
    public function sharedData()
    {
        return $this->shared_data;
    }

    /**
     * Get the current session's ID
     *
     * This will start a session if the current session id is null
     *
     * @access public
     * @return string|false
     */
    public function startSession()
    {
        if (session_id() === '') {
            if (!session_start()) {
                return false;
            }

            $this->session_id = session_id();
        }

        return $this->session_id;
    }

    /**
     * Stores a flash message of $type
     *
     * @param string $msg       The message to flash
     * @param string $type      The flash message type
     * @param array $params     Optional params to be parsed by markdown
     * @access public
     * @return void
     */
    public function flash($msg, $type = 'info', $params = null)
    {
        $this->startSession();
        if (is_array($type)) {
            $params = $type;
            $type = 'info';
        }
        if (!isset($_SESSION['__flashes'])) {
            $_SESSION['__flashes'] = array($type => array());
        } elseif (!isset($_SESSION['__flashes'][$type])) {
            $_SESSION['__flashes'][$type] = array();
        }
        $_SESSION['__flashes'][$type][] = $this->markdown($msg, $params);
    }

    /**
     * Returns and clears all flashes of optional $type
     *
     * @param string $type  The name of the flash message type
     * @access public
     * @return array
     */
    public function flashes($type = null)
    {
        $this->startSession();
        if (!isset($_SESSION['__flashes'])) {
            return array();
        }
        if (null === $type) {
            $flashes = $_SESSION['__flashes'];
            unset($_SESSION['__flashes']);
        } elseif (null !== $type) {
            $flashes = array();
            if (isset($_SESSION['__flashes'][$type])) {
                $flashes = $_SESSION['__flashes'][$type];
                unset($_SESSION['__flashes'][$type]);
            }
        }
        return $flashes;
    }

    /**
     * Render a text string as markdown
     *
     * Supports basic markdown syntax
     *
     * Also, this method takes in EITHER an array of optional arguments (as the second parameter)
     * ... OR this method will simply take a variable number of arguments (after the initial str arg)
     *
     * @param string $str   The text string to parse
     * @param array $args   Optional arguments to be parsed by markdown
     * @param mixed $...    Optional number of params...
     * @static
     * @access public
     * @return string
     */
    public static function markdown($str, $args = null)
    {
        // Create our markdown parse/conversion regex's
        $md = array(
            '/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
            '/\*\*([^\*]++)\*\*/'          => '<strong>$1</strong>',
            '/\*([^\*]++)\*/'              => '<em>$1</em>'
        );

        // Let's make our arguments more "magical"
        $args = func_get_args(); // Grab all of our passed args
        $str = array_shift($args); // Remove the initial arg from the array (and set the $str to it)
        if (isset($args[0]) && is_array($args[0])) {
            /**
             * If our "second" argument (now the first array item is an array)
             * just use the array as the arguments and forget the rest
             */
            $args = $args[0];
        }

        // Encode our args so we can insert them into an HTML string
        foreach ($args as &$arg) {
            $arg = htmlentities($arg, ENT_QUOTES);
        }

        // Actually do our markdown conversion
        return vsprintf(preg_replace(array_keys($md), $md, $str), $args);
    }

    /**
     * Escapes a string
     *
     * @param string $str   The string to escape
     * @static
     * @access public
     * @return void
     */
    public static function escape($str)
    {
        return htmlentities($str, ENT_QUOTES);
    }

    /**
     * Sends a file
     *
     * @param string $path      The path of the file to send
     * @param string $filename  The file's name
     * @param string $mimetype  The MIME type of the file
     * @access public
     * @return void
     */
    public function file($path, $filename = null, $mimetype = null)
    {
        $this->response->body('');
        $this->response->noCache();

        set_time_limit(1200);

        if (null === $filename) {
            $filename = basename($path);
        }
        if (null === $mimetype) {
            $mimetype = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
        }

        $this->response->header('Content-type', $mimetype);
        $this->response->header('Content-length', filesize($path));
        $this->response->header('Content-Disposition', 'attachment; filename="'.$filename.'"');

        $this->response->send();

        readfile($path);
    }

    /**
     * Sends an object as json or jsonp by providing the padding prefix
     *
     * @param mixed $object         The data to encode as JSON
     * @param string $jsonp_prefix  The name of the JSON-P function prefix
     * @access public
     * @return void
     */
    public function json($object, $jsonp_prefix = null)
    {
        $this->response->body('');
        $this->response->noCache();

        set_time_limit(1200);

        $json = json_encode($object);

        if (null !== $jsonp_prefix) {
            // Should ideally be application/json-p once adopted
            $this->response->header('Content-Type', 'text/javascript');
            $this->response->body("$jsonp_prefix($json);");
        } else {
            $this->response->header('Content-Type', 'application/json');
            $this->response->body($json);
        }

        $this->response->send();
    }

    /**
     * Redirects the request to the current URL
     *
     * @access public
     * @return ServiceProvider
     */
    public function refresh()
    {
        $this->response->redirect(
            $this->request->uri()
        );

        return $this;
    }

    /**
     * Redirects the request back to the referrer
     *
     * @access public
     * @return ServiceProvider
     */
    public function back()
    {
        $referer = $this->request->server()->get('HTTP_REFERER');

        if (null !== $referer) {
            return $this->response->redirect($referer);
        }

        $this->refresh();

        return $this;
    }

    /**
     * Adds to or modifies the current query string
     *
     * @param string $key   The name of the query param
     * @param mixed $value  The value of the query param
     * @access public
     * @return string
     */
    public function query($key, $value = null)
    {
        $query = array();

        parse_str(
            $this->request->server()->get('QUERY_STRING'),
            $query
        );

        if (is_array($key)) {
            $query = array_merge($query, $key);
        } else {
            $query[$key] = $value;
        }

        $request_uri = $this->request->uri();

        if (strpos($request_uri, '?') !== false) {
            $request_uri = strstr($request_uri, '?', true);
        }

        return $request_uri . (!empty($query) ? '?' . http_build_query($query) : null);
    }

    /**
     * Get (or set) the view's layout
     *
     * Simply calling this method without any arguments returns the current layout.
     * Calling with an argument, however, sets the layout to what was provided by the argument.
     *
     * @param string $layout    The layout of the view
     * @access public
     * @return string|ServiceProvider
     */
    public function layout($layout = null)
    {
        if (null !== $layout) {
            $this->layout = $layout;

            return $this;
        }

        return $this->layout;
    }

    /**
     * Renders the current view
     *
     * @access public
     * @return void
     */
    public function yield()
    {
        require $this->view;
    }

    /**
     * Renders a view + optional layout
     *
     * @param string $view  The view to render
     * @param array $data   The data to render in the view
     * @access public
     * @return void
     */
    public function render($view, array $data = array())
    {
        $original_view = $this->view;

        if (!empty($data)) {
            $this->shared_data->merge($data);
        }

        $this->view = $view;

        if (null === $this->layout) {
            $this->yield();
        } else {
            require $this->layout;
        }

        if (false !== $this->response->chunked) {
            $this->response->chunk();
        }

        // restore state for parent render()
        $this->view = $original_view;
    }

    /**
     * Renders a view without a layout
     *
     * @param string $view  The view to render
     * @param array $data   The data to render in the view
     * @access public
     * @return void
     */
    public function partial($view, array $data = array())
    {
        $layout = $this->layout;
        $this->layout = null;
        $this->render($view, $data);
        $this->layout = $layout;
    }

    /**
     * Add a custom validator for our validation method
     *
     * @param string $method        The name of the validator method
     * @param callable $callback    The callback to perform on validation
     * @access public
     * @return void
     */
    public function addValidator($method, $callback)
    {
        Validator::addValidator($method, $callback);
    }

    /**
     * Start a validator chain for the specified string
     *
     * @param string $string    The string to validate
     * @param string $err       The custom exception message to throw
     * @access public
     * @return Validator
     */
    public function validate($string, $err = null)
    {
        return new Validator($string, $err);
    }

    /**
     * Start a validator chain for the specified parameter
     *
     * @param string $param     The name of the parameter to validate
     * @param string $err       The custom exception message to throw
     * @access public
     * @return Validator
     */
    public function validateParam($param, $err = null)
    {
        return $this->validate($this->request->param($param), $err);
    }
}
