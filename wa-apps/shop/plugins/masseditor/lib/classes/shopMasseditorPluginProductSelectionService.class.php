<?php

class shopMasseditorPluginProductSelectionService
{
    const DEFAULT_PAGE_SIZE = 50;

    /**
     * @var shopMasseditorPluginProductModel
     */
    private $product_model;

    public function __construct(shopMasseditorPluginProductModel $product_model = null)
    {
        $this->product_model = $product_model ?: new shopMasseditorPluginProductModel();
    }

    public function getByIds(array $ids)
    {
        if (!$ids) {
            return array();
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->product_model
            ->query(
                'SELECT p.id, p.name, p.status, p.price, p.compare_price, p.count, p.edit_datetime, p.sku_id, p.url, p.description, p.category_id
                 FROM shop_product p
                 WHERE p.id IN (' . $placeholders . ')',
                $ids
            )
            ->fetchAll('id');
    }

    public function getPage(array $raw_filters, $page_size = null)
    {
        $filters = $this->normalizeFilters($raw_filters);
        $conditions = $this->buildConditions($filters);
        $page_size = $this->normalizePageSize($page_size);

        $total = (int) $this->product_model
            ->query(
                'SELECT COUNT(DISTINCT p.id) FROM shop_product p ' . $conditions['joins'] . ' ' . $conditions['sql'],
                $conditions['params']
            )
            ->fetchField();

        // После подсчета нормализуем страницу, чтобы не уйти за последнюю доступную.
        $page = $this->normalizePage(isset($raw_filters['page']) ? $raw_filters['page'] : 1, $total, $page_size);
        $offset = ($page - 1) * $page_size;

        $products = $this->product_model
            ->query(
                'SELECT DISTINCT p.id, p.name, p.status, p.price, p.compare_price, p.count, p.edit_datetime, p.url, p.category_id,
                        ms.sku AS main_sku
                 FROM shop_product p
                 LEFT JOIN shop_product_skus ms ON ms.id = p.sku_id ' . $conditions['joins'] . ' ' . $conditions['sql'] . '
                 ORDER BY p.id DESC
                 LIMIT ' . (int) $page_size . ' OFFSET ' . (int) $offset,
                $conditions['params']
            )
            ->fetchAll();

        return array(
            'products' => $products,
            'filters' => $filters,
            'pagination' => array(
                'page' => $page,
                'page_size' => $page_size,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $page_size)),
            ),
        );
    }

    public function getCategories()
    {
        return $this->product_model
            ->query(
                'SELECT id, name
                 FROM shop_category
                 ORDER BY name ASC'
            )
            ->fetchAll();
    }

    private function normalizeFilters(array $raw_filters)
    {
        $query = '';
        if (isset($raw_filters['query'])) {
            $query = trim((string) $raw_filters['query']);
        }

        $status = 'all';
        if (isset($raw_filters['status'])) {
            $status = (string) $raw_filters['status'];
        }

        $category_id = 0;
        if (isset($raw_filters['category_id'])) {
            $category_id = max(0, (int) $raw_filters['category_id']);
        }

        if (!in_array($status, array('all', 'published', 'hidden', 'unpublished'), true)) {
            $status = 'all';
        }

        return array(
            'query' => $query,
            'status' => $status,
            'category_id' => $category_id,
        );
    }

    private function normalizePage($page, $total, $page_size)
    {
        $page = max(1, (int) $page);
        $pages = max(1, (int) ceil($total / $page_size));

        return min($page, $pages);
    }

    private function normalizePageSize($page_size)
    {
        $page_size = (int) $page_size;
        if ($page_size <= 0) {
            $page_size = self::DEFAULT_PAGE_SIZE;
        }

        return min(200, $page_size);
    }

    private function buildConditions(array $filters)
    {
        $where = array();
        $params = array();
        $joins = '';

        if ($filters['query'] !== '') {
            $joins .= '
                LEFT JOIN shop_product_skus s ON s.product_id = p.id
            ';
            $where[] = '(p.name LIKE s:query OR s.sku LIKE s:query)';
            $params['query'] = '%' . $filters['query'] . '%';
        }

        if ($filters['status'] === 'published') {
            $where[] = 'p.status = i:status';
            $params['status'] = 1;
        } elseif ($filters['status'] === 'hidden') {
            $where[] = 'p.status = i:status';
            $params['status'] = 0;
        } elseif ($filters['status'] === 'unpublished') {
            $where[] = 'p.status = i:status';
            $params['status'] = -1;
        }

        if ($filters['category_id'] > 0) {
            $joins .= '
                INNER JOIN shop_category_products cp ON cp.product_id = p.id
            ';
            $where[] = 'cp.category_id = i:category_id';
            $params['category_id'] = $filters['category_id'];
        }

        return array(
            'joins' => $joins,
            'sql' => $where ? 'WHERE ' . implode(' AND ', $where) : '',
            'params' => $params,
        );
    }
}
