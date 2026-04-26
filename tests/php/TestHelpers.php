<?php

trait InvokesPrivateMethods
{
    protected function invokePrivate($object, $method, array $args = array())
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}

class FakeSelectionService extends shopMasseditorPluginProductSelectionService
{
    public $products = array();
    public $pages = array();
    public $categories = array();

    public function __construct()
    {
    }

    public function getByIds(array $ids)
    {
        return $this->products;
    }

    public function getPage(array $raw_filters, $page_size = null)
    {
        if ($this->pages) {
            return array_shift($this->pages);
        }

        return parent::getPage($raw_filters, $page_size);
    }

    public function getCategories()
    {
        return $this->categories;
    }
}

class FakeLogModel extends shopMasseditorPluginLogModel
{
    public $inserted = array();

    public function insert($row)
    {
        $this->inserted[] = $row;
        return count($this->inserted);
    }
}

class FakeLogService extends shopMasseditorPluginLogService
{
    public $logged = array();
    public $latest = array();
    public $pages = array();

    public function __construct()
    {
    }

    public function log($action_type, $entity_count, $description = null, $user_id = null)
    {
        $this->logged[] = compact('action_type', 'entity_count', 'description', 'user_id');
        return count($this->logged);
    }

    public function getLatest($limit = 10)
    {
        return $this->latest;
    }

    public function getPage($page = 1, $page_size = 20)
    {
        return $this->pages ? array_shift($this->pages) : array(
            'logs' => array(),
            'pagination' => array('page' => 1, 'page_size' => $page_size, 'total' => 0, 'pages' => 1),
        );
    }

    public function purgeOlderThanDays($days)
    {
        return 0;
    }
}
