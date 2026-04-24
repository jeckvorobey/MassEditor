<?php

class shopMasseditorPluginProductSelectionService
{
    const PAGE_SIZE = 50;

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
                'SELECT p.id, p.name, p.status, p.price, p.compare_price, p.count, p.edit_datetime, p.sku_id
                 FROM shop_product p
                 WHERE p.id IN (' . $placeholders . ')',
                $ids
            )
            ->fetchAll('id');
    }

    public function getPage(array $raw_filters)
    {
        $filters = $this->normalizeFilters($raw_filters);
        $conditions = $this->buildConditions($filters);

        $total = (int) $this->product_model
            ->query(
                'SELECT COUNT(*) FROM shop_product p ' . $conditions['sql'],
                $conditions['params']
            )
            ->fetchField();

        // После подсчета нормализуем страницу, чтобы не уйти за последнюю доступную.
        $page = $this->normalizePage(isset($raw_filters['page']) ? $raw_filters['page'] : 1, $total);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $products = $this->product_model
            ->query(
                'SELECT p.id, p.name, p.status, p.price, p.compare_price, p.count, p.edit_datetime
                 FROM shop_product p ' . $conditions['sql'] . '
                 ORDER BY p.id DESC
                 LIMIT ' . (int) self::PAGE_SIZE . ' OFFSET ' . (int) $offset,
                $conditions['params']
            )
            ->fetchAll();

        return array(
            'products' => $products,
            'filters' => $filters,
            'pagination' => array(
                'page' => $page,
                'page_size' => self::PAGE_SIZE,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            ),
        );
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

        if (!in_array($status, array('all', 'published', 'hidden', 'unpublished'), true)) {
            $status = 'all';
        }

        return array(
            'query' => $query,
            'status' => $status,
        );
    }

    private function normalizePage($page, $total)
    {
        $page = max(1, (int) $page);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));

        return min($page, $pages);
    }

    private function buildConditions(array $filters)
    {
        $where = array();
        $params = array();

        if ($filters['query'] !== '') {
            $where[] = 'p.name LIKE s:query';
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

        return array(
            'sql' => $where ? 'WHERE ' . implode(' AND ', $where) : '',
            'params' => $params,
        );
    }
}
