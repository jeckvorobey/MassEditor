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
                'SELECT p.id, p.name, p.status, p.price, p.compare_price, p.count, p.edit_datetime, p.url, p.description, p.category_id,
                        ms.sku AS main_sku,
                        CASE
                            WHEN MAX(COALESCE(sku_all.available, 0)) = 1 THEN 1
                            ELSE 0
                        END AS availability,
                        COALESCE(
                            NULLIF(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR \', \'), \'\'),
                            pc.name,
                            \'-\'
                        ) AS category_names,
                        COALESCE(
                            NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR \', \'), \'\'),
                            \'-\'
                        ) AS tag_names
                 FROM shop_product p
                 LEFT JOIN shop_product_skus ms ON ms.id = p.sku_id
                 LEFT JOIN shop_product_skus sku_all ON sku_all.product_id = p.id
                 LEFT JOIN shop_category pc ON pc.id = p.category_id
                 LEFT JOIN shop_category_products cp_all ON cp_all.product_id = p.id
                 LEFT JOIN shop_category c ON c.id = cp_all.category_id
                 LEFT JOIN shop_product_tags pt ON pt.product_id = p.id
                 LEFT JOIN shop_tag t ON t.id = pt.tag_id ' . $conditions['joins'] . ' ' . $conditions['sql'] . '
                 GROUP BY p.id, p.name, p.status, p.price, p.compare_price, p.count, p.edit_datetime, p.url, p.description, p.category_id, ms.sku, pc.name
                 ORDER BY p.id DESC
                 LIMIT ' . (int) $page_size . ' OFFSET ' . (int) $offset,
                $conditions['params']
            )
            ->fetchAll();
        $products = $this->attachStockDetails($products);

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

    public function getIdsByFilters(array $raw_filters, $limit)
    {
        $filters = $this->normalizeFilters($raw_filters);
        $conditions = $this->buildConditions($filters);
        $limit = max(1, min(1001, (int) $limit));

        $rows = $this->product_model
            ->query(
                'SELECT DISTINCT p.id
                 FROM shop_product p ' . $conditions['joins'] . ' ' . $conditions['sql'] . '
                 ORDER BY p.id DESC
                 LIMIT ' . (int) $limit,
                $conditions['params']
            )
            ->fetchAll();

        $ids = array();
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function getSearchSuggestions(array $raw_filters, $query, $limit = 10)
    {
        $query = trim((string) $query);
        $limit = max(1, min(20, (int) $limit));
        if ($query === '') {
            return array();
        }

        $filters = $this->normalizeFilters($raw_filters);
        $filters['query'] = '';
        $conditions = $this->buildConditions($filters);
        $search = $this->buildSearchCondition($query);
        $sql_limit = $limit * 10;

        $rows = $this->product_model
            ->query(
                'SELECT DISTINCT p.id, p.name, p.url, p.summary, p.description,
                        s.sku,
                        t_search.name AS tag_name,
                        c_search.name AS category_name
                 FROM shop_product p
                 ' . $search['joins'] . '
                 ' . $conditions['joins'] . ' ' . $conditions['sql'] . '
                 ' . ($conditions['sql'] === '' ? 'WHERE ' : 'AND ') . $search['sql'] . '
                 ORDER BY p.id DESC
                 LIMIT ' . (int) $sql_limit,
                array_merge($conditions['params'], $search['params'])
            )
            ->fetchAll();

        return $this->extractSuggestions($rows, $query, $limit);
    }

    public function getStocks()
    {
        return $this->product_model
            ->query(
                'SELECT id, name
                 FROM shop_stock
                 ORDER BY sort ASC, id ASC'
            )
            ->fetchAll();
    }

    private function attachStockDetails(array $products)
    {
        if (!$products) {
            return $products;
        }

        $stocks = $this->getStocks();
        foreach ($products as &$product) {
            $product['stock_details'] = array();
        }
        unset($product);

        if (!$stocks) {
            return $products;
        }

        $product_ids = array();
        foreach ($products as $product) {
            if (isset($product['id']) && (int) $product['id'] > 0) {
                $product_ids[] = (int) $product['id'];
            }
        }
        if (!$product_ids) {
            return $products;
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $rows = $this->product_model
            ->query(
                'SELECT sku.product_id,
                        ps.stock_id,
                        SUM(CASE WHEN ps.count IS NULL THEN 0 ELSE ps.count END) AS count,
                        MAX(CASE WHEN ps.count IS NULL THEN 1 ELSE 0 END) AS has_infinite
                 FROM shop_product_skus sku
                 INNER JOIN shop_product_stocks ps ON ps.sku_id = sku.id
                 WHERE sku.product_id IN (' . $placeholders . ')
                 GROUP BY sku.product_id, ps.stock_id',
                $product_ids
            )
            ->fetchAll();

        $stock_counts = array();
        $products_with_stock_details = array();
        foreach ($rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $stock_id = isset($row['stock_id']) ? (int) $row['stock_id'] : 0;
            if ($product_id <= 0 || $stock_id <= 0) {
                continue;
            }
            $products_with_stock_details[$product_id] = true;
            $has_infinite = !empty($row['has_infinite'])
                || (array_key_exists('count', $row) && $row['count'] === null);
            $stock_counts[$product_id][$stock_id] = $has_infinite
                ? null
                : (float) $row['count'];
        }

        foreach ($products as &$product) {
            $product_id = isset($product['id']) ? (int) $product['id'] : 0;
            if (empty($products_with_stock_details[$product_id])) {
                continue;
            }
            foreach ($stocks as $stock) {
                $stock_id = isset($stock['id']) ? (int) $stock['id'] : 0;
                if ($stock_id <= 0) {
                    continue;
                }
                $count = isset($stock_counts[$product_id]) && array_key_exists($stock_id, $stock_counts[$product_id])
                    ? $stock_counts[$product_id][$stock_id]
                    : 0.0;
                $product['stock_details'][] = array(
                    'stock_id' => $stock_id,
                    'stock_name' => isset($stock['name']) ? (string) $stock['name'] : '',
                    'count' => $count,
                    'count_view' => $this->formatStockCount($count),
                );
            }
        }
        unset($product);

        return $products;
    }

    private function formatStockCount($value)
    {
        if ($value === null) {
            return '∞';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    public function getEditableFeatures()
    {
        return $this->product_model
            ->query(
                'SELECT id, name, type, selectable, multiple
                 FROM shop_feature
                 WHERE parent_id IS NULL OR parent_id = 0
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

        $availability = 'all';
        if (isset($raw_filters['availability'])) {
            $availability = (string) $raw_filters['availability'];
        }

        if (!in_array($status, array('all', 'published', 'hidden', 'unpublished'), true)) {
            $status = 'all';
        }

        if (!in_array($availability, array('all', 'available', 'unavailable'), true)) {
            $availability = 'all';
        }

        return array(
            'query' => $query,
            'status' => $status,
            'availability' => $availability,
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
            $search = $this->buildSearchCondition($filters['query']);
            $joins .= $search['joins'];
            $where[] = $search['sql'];
            $params = array_merge($params, $search['params']);
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

        if ($filters['availability'] === 'available') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM shop_product_skus sa
                WHERE sa.product_id = p.id AND sa.available = 1
            )';
        } elseif ($filters['availability'] === 'unavailable') {
            $where[] = 'NOT EXISTS (
                SELECT 1
                FROM shop_product_skus sa
                WHERE sa.product_id = p.id AND sa.available = 1
            )';
        }

        if ($filters['category_id'] > 0) {
            $joins .= '
                INNER JOIN shop_category_products cpf ON cpf.product_id = p.id
            ';
            $where[] = 'cpf.category_id = i:category_id';
            $params['category_id'] = $filters['category_id'];
        }

        return array(
            'joins' => $joins,
            'sql' => $where ? 'WHERE ' . implode(' AND ', $where) : '',
            'params' => $params,
        );
    }

    private function buildSearchCondition($query)
    {
        return array(
            'joins' => '
                LEFT JOIN shop_product_skus s ON s.product_id = p.id
                LEFT JOIN shop_product_tags pt_search ON pt_search.product_id = p.id
                LEFT JOIN shop_tag t_search ON t_search.id = pt_search.tag_id
                LEFT JOIN shop_category_products cp_search ON cp_search.product_id = p.id
                LEFT JOIN shop_category c_search ON c_search.id = cp_search.category_id
            ',
            'sql' => '(p.name LIKE s:query
                OR p.url LIKE s:query
                OR p.summary LIKE s:query
                OR p.description LIKE s:query
                OR s.sku LIKE s:query
                OR t_search.name LIKE s:query
                OR c_search.name LIKE s:query)',
            'params' => array(
                'query' => '%' . trim((string) $query) . '%',
            ),
        );
    }

    private function extractSuggestions(array $rows, $query, $limit)
    {
        $suggestions = array();
        $seen = array();
        $fields = array('sku', 'name', 'url', 'category_name', 'tag_name', 'summary', 'description');

        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if (!isset($row[$field])) {
                    continue;
                }
                $value = $this->normalizeSuggestionValue($row[$field]);
                if ($value === '' || !$this->containsText($value, $query)) {
                    continue;
                }

                $key = $this->lowerText($value);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $suggestions[] = $value;
                if (count($suggestions) >= $limit) {
                    return $suggestions;
                }
            }
        }

        return $suggestions;
    }

    private function containsText($value, $query)
    {
        if (function_exists('mb_strtolower') && function_exists('mb_strpos')) {
            return mb_strpos(
                mb_strtolower((string) $value, 'UTF-8'),
                mb_strtolower((string) $query, 'UTF-8'),
                0,
                'UTF-8'
            ) !== false;
        }

        return stripos((string) $value, (string) $query) !== false;
    }

    private function lowerText($value)
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower((string) $value, 'UTF-8');
        }

        return strtolower((string) $value);
    }

    private function normalizeSuggestionValue($value)
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > 120) {
            return mb_substr($value, 0, 117, 'UTF-8') . '...';
        }
        if (!function_exists('mb_strlen') && strlen($value) > 120) {
            return substr($value, 0, 117) . '...';
        }

        return $value;
    }
}
