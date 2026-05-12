<?php

date_default_timezone_set('UTC');

if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'wb'));
}

function ifset($value, $default = null)
{
    return isset($value) ? $value : $default;
}

function wa_lambda($args, $body)
{
    return eval('return function(' . $args . ') { ' . $body . ' };');
}

class FakePluginLocaleCatalog
{
    private static $messages = array();
    private static $plurals = array();

    public static function translate($msgid, $msgid_plural = null, $count = null, $locale = 'ru_RU')
    {
        self::load($locale);

        if ($msgid_plural !== null && $count !== null) {
            $index = self::pluralIndex($locale, (int) $count);
            if (isset(self::$plurals[$locale][$msgid][$index])) {
                return self::$plurals[$locale][$msgid][$index];
            }

            return (int) $count === 1 ? $msgid : $msgid_plural;
        }

        if (isset(self::$messages[$locale][$msgid])) {
            return self::$messages[$locale][$msgid];
        }

        return $msgid;
    }

    private static function load($locale)
    {
        if (isset(self::$messages[$locale])) {
            return;
        }

        self::$messages[$locale] = array();
        self::$plurals[$locale] = array();

        $path = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/locale/' . $locale . '/LC_MESSAGES/shop_masseditor.po';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $entry = array();
        foreach ($lines as $line) {
            if (trim($line) === '') {
                self::storeEntry($locale, $entry);
                $entry = array();
                continue;
            }

            if (strpos($line, 'msgid_plural ') === 0) {
                $entry['msgid_plural'] = self::decodePoString(substr($line, 13));
                continue;
            }

            if (preg_match('/^msgstr\\[(\\d+)\\]\\s+(".*")$/', $line, $matches)) {
                $entry['msgstr_plural'][(int) $matches[1]] = self::decodePoString($matches[2]);
                continue;
            }

            if (strpos($line, 'msgid ') === 0) {
                $entry['msgid'] = self::decodePoString(substr($line, 6));
                continue;
            }

            if (strpos($line, 'msgstr ') === 0) {
                $entry['msgstr'] = self::decodePoString(substr($line, 7));
            }
        }

        self::storeEntry($locale, $entry);
    }

    private static function storeEntry($locale, array $entry)
    {
        if (!isset($entry['msgid']) || $entry['msgid'] === '') {
            return;
        }

        if (isset($entry['msgid_plural'])) {
            self::$plurals[$locale][$entry['msgid']] = isset($entry['msgstr_plural']) ? $entry['msgstr_plural'] : array();
            return;
        }

        self::$messages[$locale][$entry['msgid']] = isset($entry['msgstr']) && $entry['msgstr'] !== ''
            ? $entry['msgstr']
            : $entry['msgid'];
    }

    private static function decodePoString($value)
    {
        $value = trim($value);
        if ($value === '""') {
            return '';
        }

        return stripcslashes(substr($value, 1, -1));
    }

    private static function pluralIndex($locale, $count)
    {
        if (strpos((string) $locale, 'ru') === 0) {
            $mod10 = $count % 10;
            $mod100 = $count % 100;
            if ($mod10 === 1 && $mod100 !== 11) {
                return 0;
            }
            if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
                return 1;
            }

            return 2;
        }

        return $count === 1 ? 0 : 1;
    }
}

if (!function_exists('_wp')) {
    function _wp($msgid, $msgid_plural = null, $count = null)
    {
        $system = wa();
        $locale = $system && method_exists($system, 'getLocale') ? $system->getLocale() : 'ru_RU';

        return FakePluginLocaleCatalog::translate($msgid, $msgid_plural, $count, $locale);
    }
}

class FakeQueryResult
{
    private $rows;
    private $field;

    public function __construct(array $rows = array(), $field = null)
    {
        $this->rows = $rows;
        $this->field = $field;
    }

    public function fetchAll($key = null)
    {
        if ($key === null) {
            return $this->rows;
        }

        $result = array();
        foreach ($this->rows as $row) {
            if (isset($row[$key])) {
                $result[$row[$key]] = $row;
            }
        }

        return $result;
    }

    public function fetchField()
    {
        return $this->field;
    }
}

class waModel
{
    public $queries = array();
    public $execs = array();
    public $responses = array();

    public function query($sql, $params = array())
    {
        $this->queries[] = array('sql' => $sql, 'params' => $params);
        foreach ($this->responses as $index => $response) {
            if (strpos($sql, $response['match']) !== false) {
                array_splice($this->responses, $index, 1);
                return $response['result'];
            }
        }

        return new FakeQueryResult();
    }

    public function exec($sql, $params = array())
    {
        $this->execs[] = array('sql' => $sql, 'params' => $params);
        return 1;
    }

    public function queueResponse($match, FakeQueryResult $result)
    {
        $this->responses[] = array('match' => $match, 'result' => $result);
    }
}

class FakeUser
{
    private $id;
    private $admin;

    public function __construct($id = 1, $admin = true)
    {
        $this->id = $id;
        $this->admin = $admin;
    }

    public function getId()
    {
        return $this->id;
    }

    public function isAdmin($app)
    {
        return $this->admin;
    }
}

class FakeView
{
    public $assigned = array();

    public function assign(array $data)
    {
        $this->assigned = array_merge($this->assigned, $data);
    }
}

class FakeWaSystem
{
    public $plugins = array();
    public $user;
    public $app_url = '/webasyst/shop/';
    public $locale = 'ru_RU';

    public function __construct()
    {
        $this->user = new FakeUser();
    }

    public function getPlugin($id)
    {
        return isset($this->plugins[$id]) ? $this->plugins[$id] : null;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getAppUrl($app)
    {
        return $this->app_url;
    }

    public function getLocale()
    {
        return $this->locale;
    }
}

$GLOBALS['fake_wa_system'] = new FakeWaSystem();

function wa($app = null)
{
    return $GLOBALS['fake_wa_system'];
}

class waViewAction
{
    public $view;

    public function __construct()
    {
        $this->view = new FakeView();
    }
}

class waLog
{
    public static $logs = array();

    public static function reset()
    {
        self::$logs = array();
    }

    public static function log($message, $file = null)
    {
        self::$logs[] = array(
            'message' => $message,
            'file' => $file,
        );
    }
}

class waRequest
{
    const TYPE_STRING_TRIM = 'string_trim';
    const TYPE_INT = 'int';
    const TYPE_ARRAY = 'array';

    public static $method = 'get';
    public static $get = array();
    public static $post = array();
    public static $request = array();

    public static function reset()
    {
        self::$method = 'get';
        self::$get = array();
        self::$post = array();
        self::$request = array();
    }

    public static function getMethod()
    {
        return self::$method;
    }

    public static function get($name, $default = null, $type = null)
    {
        return self::normalize(isset(self::$get[$name]) ? self::$get[$name] : $default, $type);
    }

    public static function post($name, $default = null, $type = null)
    {
        return self::normalize(isset(self::$post[$name]) ? self::$post[$name] : $default, $type);
    }

    public static function request($name, $default = null, $type = null)
    {
        $value = isset(self::$request[$name]) ? self::$request[$name] : null;
        if ($value === null && isset(self::$post[$name])) {
            $value = self::$post[$name];
        }
        if ($value === null && isset(self::$get[$name])) {
            $value = self::$get[$name];
        }

        return self::normalize($value !== null ? $value : $default, $type);
    }

    private static function normalize($value, $type)
    {
        if ($type === self::TYPE_STRING_TRIM) {
            return trim((string) $value);
        }
        if ($type === self::TYPE_INT) {
            return (int) $value;
        }
        if ($type === self::TYPE_ARRAY) {
            return is_array($value) ? $value : array();
        }

        return $value;
    }
}

class waContact
{
    public static $names = array();
    private $id;

    public function __construct($id)
    {
        if (!isset(self::$names[$id])) {
            throw new RuntimeException('Unknown contact');
        }
        $this->id = $id;
    }

    public function getName()
    {
        return self::$names[$this->id];
    }
}

class shopPlugin
{
    protected $settings = array();

    public function __construct(array $settings = array())
    {
        $this->settings = $settings;
    }

    public function getName()
    {
        return 'Mass Editor';
    }

    public function getId()
    {
        return 'masseditor';
    }

    public function getPluginStaticUrl()
    {
        return '/wa-apps/shop/plugins/masseditor/';
    }

    public function getSettings($name)
    {
        return isset($this->settings[$name]) ? $this->settings[$name] : null;
    }

    public function saveSettings(array $settings)
    {
        $this->settings = array_merge($this->settings, $settings);
    }
}

class shopProduct implements ArrayAccess
{
    public static $products = array();
    public static $saved = array();

    private $id;
    private $data = array();

    public function __construct($id)
    {
        $this->id = (int) $id;
        $seed = isset(self::$products[$this->id]) ? self::$products[$this->id] : array();
        $this->data = isset($seed['data']) ? $seed['data'] : array('id' => $this->id);
    }

    public static function reset()
    {
        self::$products = array();
        self::$saved = array();
    }

    public static function seed($id, array $data = array(), array $skus = array())
    {
        self::$products[(int) $id] = array(
            'data' => array_merge(array('id' => (int) $id), $data),
            'skus' => $skus,
        );
    }

    public function getSkus()
    {
        return isset(self::$products[$this->id]['skus']) ? self::$products[$this->id]['skus'] : array();
    }

    public function save()
    {
        self::$saved[$this->id] = $this->data;
        self::$products[$this->id]['data'] = $this->data;
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }
}

class shopProductTagsModel
{
    public static $calls = array();

    public static function reset()
    {
        self::$calls = array();
    }

    public function addTags($product_id, array $tags)
    {
        self::$calls[] = array('method' => 'addTags', 'product_id' => $product_id, 'tags' => $tags);
    }

    public function deleteTags($product_id, array $tags)
    {
        self::$calls[] = array('method' => 'deleteTags', 'product_id' => $product_id, 'tags' => $tags);
    }

    public function setData($product, array $tags)
    {
        self::$calls[] = array('method' => 'setData', 'product' => $product, 'tags' => $tags);
    }
}

class shopHelper
{
    public static $used_urls = array();

    public static function reset()
    {
        self::$used_urls = array();
    }

    public static function transliterate($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    public static function isProductUrlInUse(array $data)
    {
        return in_array($data['url'], self::$used_urls, true);
    }
}

require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/models/shopMasseditorPluginProduct.model.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/models/shopMasseditorPluginLog.model.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginI18nService.class.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginProductSelectionService.class.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginLogService.class.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginMassOperationService.class.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackend.action.php';
require_once __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/shopMasseditor.plugin.php';
